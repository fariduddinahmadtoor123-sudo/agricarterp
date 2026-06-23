<div
    class="agricart-pt-page agricart-pp-worksheet"
    x-data="{
        printStickers() {
            window.requestAnimationFrame(() => {
                window.setTimeout(() => window.print(), 150);
            });
        },
    }"
    x-on:agricart-pt-print.window="printStickers()"
>
    <div class="agricart-pp-worksheet__header agricart-pt-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\PurchasingInventory\Overview::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Purchasing</span>
            </a>
            <span class="agricart-pp-worksheet__header-title">Price tag printing</span>
        </div>

        <div class="agricart-pp-worksheet__header-right">
            <span class="agricart-pp-worksheet__item-count">
                {{ $stickerCount }} {{ $stickerCount === 1 ? 'sticker' : 'stickers' }}
            </span>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="printTags">
                Print Stickers
            </button>
        </div>
    </div>

    <p class="agricart-pt-header__meta agricart-pt-screen-only">
        Add products manually or load invoice — sale digits left · purchase code right
    </p>

    <section class="agricart-pt-panel agricart-pt-screen-only">
        <div class="agricart-pt-panel__row">
            <label class="agricart-pt-field agricart-pt-field--grow">
                <span class="agricart-pt-field__label">Add Product</span>
                <div class="agricart-pp-inline-search agricart-pp-inline-search--product">
                    <input
                        type="text"
                        class="agricart-pp-inline-search__input"
                        wire:model.live.debounce.300ms="productSearch"
                        wire:keydown.enter.prevent="addProductFromSearch"
                        placeholder="SKU, barcode, or name..."
                        autocomplete="off"
                    />
                    @if (count($searchResults) > 0)
                        <ul class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--product" role="listbox">
                            @foreach ($searchResults as $result)
                                <li wire:key="pt-search-{{ $result['id'] }}">
                                    <button type="button" class="agricart-pp-inline-search__option agricart-pp-inline-search__option--product" wire:click="selectSearchResult({{ (int) $result['id'] }})">
                                        @if (filled($result['thumbnail_url'] ?? null))
                                            <img src="{{ $result['thumbnail_url'] }}" alt="" class="agricart-pp-inline-search__thumb" />
                                        @endif
                                        <span>
                                            <strong>{{ $result['display_name'] }}</strong>
                                            <small>{{ $result['barcode'] }}</small>
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </label>

            <label class="agricart-pt-field agricart-pt-field--invoice">
                <span class="agricart-pt-field__label">Purchase Invoice</span>
                <input
                    type="text"
                    class="agricart-pt-field__input"
                    list="agricart-pt-invoice-list"
                    wire:model.blur="purchaseInvoiceNumber"
                    placeholder="PU-YYYYMMDD-XXXX"
                />
                <datalist id="agricart-pt-invoice-list">
                    @foreach ($purchaseInvoiceOptions as $option)
                        <option value="{{ $option['purchase_number'] }}">{{ $option['label'] }}</option>
                    @endforeach
                </datalist>
            </label>

            <div class="agricart-pt-invoice-actions">
                <button type="button" class="agricart-pp-worksheet__btn" wire:click="loadPurchaseInvoice">Load Invoice</button>
                <button type="button" class="agricart-pp-worksheet__btn" wire:click="mergePurchaseInvoice">Merge Invoice</button>
                <button type="button" class="agricart-pt-clear" wire:click="clearQueue" wire:confirm="Clear the entire sticker queue?">Clear queue</button>
            </div>
        </div>

        <div class="agricart-pt-panel__row agricart-pt-panel__row--options">
            <div class="agricart-pt-options">
                <span class="agricart-pt-options__label">Print fields</span>
                <div class="agricart-pt-options__checks">
                    @foreach ($printFieldLabels as $key => $label)
                        <label class="agricart-pt-check" wire:key="pt-field-{{ $key }}">
                            <input
                                type="checkbox"
                                @checked($printFields[$key] ?? false)
                                wire:click="togglePrintField('{{ $key }}')"
                            />
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="agricart-pt-scan">
                <span class="agricart-pt-options__label">Scan code</span>
                <div class="agricart-pp-worksheet__lang-group">
                    @foreach ($scanModes as $key => $label)
                        <button
                            type="button"
                            @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $scanMode === $key])
                            wire:click="setScanMode('{{ $key }}')"
                        >{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="agricart-pt-queue agricart-pt-screen-only">
        <table class="agricart-pt-table">
            <thead>
                <tr>
                    <th class="agricart-pt-col-sr">#</th>
                    <th class="agricart-pt-col-preview">Preview</th>
                    <th>Product</th>
                    <th class="agricart-pt-col-pqty">Purch Qty</th>
                    <th class="agricart-pt-col-qty">Qty</th>
                    <th class="agricart-pt-col-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($queueLines as $index => $line)
                    <tr @class(['agricart-pt-row--disabled' => $line['disabled'] ?? false]) wire:key="pt-line-{{ $line['line_id'] }}">
                        <td class="agricart-pt-col-sr">{{ $index + 1 }}</td>
                        <td class="agricart-pt-col-preview">
                            @include('filament.purchasing-inventory.price-tag-sticker', ['sticker' => $line['sticker']])
                        </td>
                        <td>
                            <div class="agricart-pt-product-name">{{ $line['name_en'] }}</div>
                            @if (filled($line['name_ur'] ?? ''))
                                <div class="agricart-pt-product-ur">{{ $line['name_ur'] }}</div>
                            @endif
                            <div class="agricart-pt-product-sku">{{ $line['sku'] }}</div>
                            @if (filled($line['source_invoice'] ?? ''))
                                <div class="agricart-pt-product-src">{{ $line['source_invoice'] }}</div>
                            @endif
                        </td>
                        <td class="agricart-pt-col-pqty">{{ filled($line['purchase_qty'] ?? '') ? $line['purchase_qty'] : '—' }}</td>
                        <td class="agricart-pt-col-qty">
                            <div class="agricart-pt-qty">
                                <button type="button" class="agricart-pt-qty__btn" wire:click="decrementPrintQty('{{ $line['line_id'] }}')">−</button>
                                <input
                                    type="number"
                                    min="1"
                                    class="agricart-pt-qty__input"
                                    wire:model.blur="queueLines.{{ $index }}.print_qty"
                                />
                                <button type="button" class="agricart-pt-qty__btn" wire:click="incrementPrintQty('{{ $line['line_id'] }}')">+</button>
                            </div>
                        </td>
                        <td class="agricart-pt-col-actions">
                            <button type="button" class="agricart-pt-action" wire:click="toggleLineDisabled('{{ $line['line_id'] }}')">
                                {{ ($line['disabled'] ?? false) ? 'Enable' : 'Disable' }}
                            </button>
                            <button type="button" class="agricart-pt-action agricart-pt-action--danger" wire:click="removeLine('{{ $line['line_id'] }}')">Cancel</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="agricart-pt-empty">Sticker queue is empty. Add a product or load a purchase invoice.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="agricart-pt-print">
        @foreach ($queueLines as $line)
            @if (! ($line['disabled'] ?? false))
                @for ($copy = 0; $copy < (int) ($line['print_qty'] ?? 1); $copy++)
                    <div class="agricart-pt-print-item" wire:key="pt-print-{{ $line['line_id'] }}-{{ $copy }}">
                        @include('filament.purchasing-inventory.price-tag-sticker', ['sticker' => $line['sticker']])
                    </div>
                @endfor
            @endif
        @endforeach
    </div>
</div>
