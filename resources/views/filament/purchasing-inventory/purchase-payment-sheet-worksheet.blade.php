<div
    class="agricart-pps-worksheet agricart-pp-worksheet"
    x-data
    x-on:agricart-pps-print.window="window.print()"
>
    <div class="agricart-pp-worksheet__header agricart-pps-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\PurchasingInventory\PurchasePaymentSheet::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Payment Sheets</span>
            </a>
            <span class="agricart-pp-worksheet__header-title">
                {{ filled($sheetTitle) ? $sheetTitle : ($isNewSheet ? 'New Payment Sheet' : $sheetNumber) }}
            </span>
            @if (filled($sheetNumber))
                <span class="agricart-pp-worksheet__header-number">{{ $sheetNumber }}</span>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-center agricart-pps-header-meta">
            <label class="agricart-pps-header-date">
                <span class="agricart-pps-header-date__label">Sheet Date</span>
                <input type="date" class="agricart-pp-worksheet__date" wire:model.blur="sheetDate" />
            </label>
            <input
                type="text"
                class="agricart-pp-worksheet__title-input"
                wire:model.blur="purchaserName"
                placeholder="Purchaser name"
            />
            <input
                type="text"
                class="agricart-pp-worksheet__title-input agricart-pps-title-input"
                wire:model.blur="sheetTitle"
                placeholder="Optional title"
            />
        </div>

        <div class="agricart-pp-worksheet__header-right">
            @php
                $sheetStatus = (string) ($sheet['status'] ?? 'draft');
            @endphp
            <span @class([
                'agricart-pp-worksheet__status',
                'agricart-pp-worksheet__status--saved' => $sheetStatus === 'saved',
                'agricart-pp-worksheet__status--draft' => $sheetStatus !== 'saved',
            ])>
                {{ $sheetStatus === 'saved' ? 'Saved' : 'Draft' }}
            </span>
            <span class="agricart-pp-worksheet__item-count">{{ $vendorCount }} bills</span>
            <button type="button" class="agricart-pp-worksheet__btn" wire:click="printSheet">Print</button>
            <button
                type="button"
                class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard"
                wire:click="discardSheet"
                wire:confirm="Discard this payment sheet? All entries will be removed."
            >
                Discard
            </button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="saveSheet">
                Save Sheet
            </button>
        </div>
    </div>

    <div class="agricart-pps-summary agricart-pps-screen-only">
        <div class="agricart-pps-summary__item">
            <span class="agricart-pps-summary__label">Vendor Bills</span>
            <strong>{{ $vendorCount }}</strong>
        </div>
        <div class="agricart-pps-summary__item">
            <span class="agricart-pps-summary__label">Vendor Payments</span>
            <strong>{{ $vendorTotal }}</strong>
        </div>
        <div class="agricart-pps-summary__item">
            <span class="agricart-pps-summary__label">Payment Sources</span>
            <strong>{{ $sourceCount }}</strong>
        </div>
        <div class="agricart-pps-summary__item">
            <span class="agricart-pps-summary__label">Money Received</span>
            <strong>{{ $sourceTotal }}</strong>
        </div>
        <div class="agricart-pps-summary__item agricart-pps-summary__item--balance">
            <span class="agricart-pps-summary__label">Balance</span>
            <strong>{{ $balance }}</strong>
        </div>
    </div>

    <section class="agricart-pps-section agricart-pps-screen-only">
        <div class="agricart-pps-section__head">
            <div>
                <h2 class="agricart-pps-section__title">Vendor Payments</h2>
                <p class="agricart-pps-section__hint">Search a supplier below or type any vendor name (hotel, transport, etc.). One date applies to the whole sheet.</p>
            </div>
            <button type="button" class="agricart-pps-add-row" wire:click="addVendorRow">+ Add Row</button>
        </div>

        <div class="agricart-pps-quick-supplier">
            <span class="agricart-pps-quick-supplier__label">Add Supplier</span>
            <div class="agricart-pp-inline-search agricart-pps-quick-supplier__search">
                <input
                    type="text"
                    class="agricart-pp-inline-search__input"
                    wire:model.live.debounce.250ms="quickSupplierSearch"
                    placeholder="Search supplier by name, code or city..."
                    autocomplete="off"
                />

                @if (count($quickSupplierResults) > 0)
                    <ul
                        class="agricart-pp-inline-search__dropdown"
                        role="listbox"
                        wire:key="pps-supplier-results-{{ count($quickSupplierResults) }}-{{ md5($quickSupplierSearch) }}"
                    >
                        @foreach ($quickSupplierResults as $supplier)
                            <li wire:key="pps-supplier-option-{{ $supplier['id'] }}">
                                <button
                                    type="button"
                                    class="agricart-pp-inline-search__option"
                                    wire:click="addSupplierFromSearch({{ (int) $supplier['id'] }})"
                                >
                                    <span class="agricart-pp-inline-search__option-label">{{ $supplier['label'] }}</span>
                                    @if (filled($supplier['city'] ?? null))
                                        <span class="agricart-pp-inline-search__option-hint">{{ $supplier['city'] }}</span>
                                    @endif
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="agricart-pps-grid-scroll">
            <table class="agricart-pps-grid">
                <thead>
                    <tr>
                        <th class="agricart-pps-col-sr">No.</th>
                        <th class="agricart-pps-col-name">Vendor / Supplier</th>
                        <th class="agricart-pps-col-payment">Payment</th>
                        <th class="agricart-pps-col-check">Invoice OK</th>
                        <th class="agricart-pps-col-check">Dispute</th>
                        <th class="agricart-pps-col-action"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vendorLines as $index => $line)
                        <tr wire:key="pps-vendor-{{ $index }}">
                            <td class="agricart-pps-col-sr">{{ $line['serial'] }}</td>
                            <td>
                                <input
                                    type="text"
                                    class="agricart-pps-cell-input"
                                    wire:model.blur="vendorLines.{{ $index }}.vendor_name"
                                    placeholder="Supplier or vendor name"
                                />
                            </td>
                            <td>
                                <input
                                    type="text"
                                    inputmode="decimal"
                                    class="agricart-pps-cell-input agricart-pps-cell-input--num"
                                    wire:model.blur="vendorLines.{{ $index }}.payment"
                                    placeholder="0"
                                />
                            </td>
                            <td class="agricart-pps-col-check">
                                <input
                                    type="checkbox"
                                    class="agricart-pps-checkbox"
                                    @checked($line['invoice_ok'] ?? false)
                                    wire:click="toggleVendorInvoiceOk({{ $index }})"
                                />
                            </td>
                            <td class="agricart-pps-col-check">
                                <input
                                    type="checkbox"
                                    class="agricart-pps-checkbox"
                                    @checked($line['invoice_dispute'] ?? false)
                                    wire:click="toggleVendorInvoiceDispute({{ $index }})"
                                />
                            </td>
                            <td class="agricart-pps-col-action">
                                <button
                                    type="button"
                                    class="agricart-pps-remove-row"
                                    wire:click="removeVendorRow({{ $index }})"
                                    title="Clear row"
                                >&times;</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="agricart-pps-section agricart-pps-screen-only">
        <div class="agricart-pps-section__head">
            <div>
                <h2 class="agricart-pps-section__title">Payment Sources</h2>
                <p class="agricart-pps-section__hint">Where money came from — shop cash, bank, credit from a party, etc. Add as many rows as you need.</p>
            </div>
            <button type="button" class="agricart-pps-add-row" wire:click="addPaymentSourceRow">+ Add Row</button>
        </div>

        <table class="agricart-pps-grid agricart-pps-grid--compact">
            <thead>
                <tr>
                    <th class="agricart-pps-col-sr">No.</th>
                    <th>Payment Source</th>
                    <th class="agricart-pps-col-payment">Amount</th>
                    <th class="agricart-pps-col-action"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paymentSources as $index => $line)
                    <tr wire:key="pps-source-{{ $index }}">
                        <td class="agricart-pps-col-sr">{{ $index + 1 }}</td>
                        <td>
                            <input
                                type="text"
                                class="agricart-pps-cell-input"
                                wire:model.blur="paymentSources.{{ $index }}.source"
                                placeholder="e.g. Shop cash, Bank, Lahore party"
                            />
                        </td>
                        <td>
                            <input
                                type="text"
                                inputmode="decimal"
                                class="agricart-pps-cell-input agricart-pps-cell-input--num"
                                wire:model.blur="paymentSources.{{ $index }}.amount"
                                placeholder="0"
                            />
                        </td>
                        <td class="agricart-pps-col-action">
                            <button
                                type="button"
                                class="agricart-pps-remove-row"
                                wire:click="removePaymentSourceRow({{ $index }})"
                                title="Clear row"
                            >&times;</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <section class="agricart-pps-section agricart-pps-screen-only">
        <label class="agricart-pps-notes">
            <span class="agricart-pps-section__title">Notes</span>
            <textarea class="agricart-pps-notes__input" wire:model.blur="notes" rows="2" placeholder="Optional notes for this purchase trip"></textarea>
        </label>
        <p class="agricart-pps-section__hint agricart-pps-section__hint--footer">Invoice disputes are recorded on the purchase invoice. This sheet is for printing your cash summary — OK / Dispute boxes print blank for manual tick in the field.</p>
    </section>

    <div class="agricart-pps-print">
        <div class="agricart-pps-print__header">
            <div>
                <h1 class="agricart-pps-print__title">Purchase Payment Sheet</h1>
                <p class="agricart-pps-print__meta">
                    <span>{{ $sheetNumber }}</span>
                    @if (filled($sheetTitle))
                        <span> — {{ $sheetTitle }}</span>
                    @endif
                </p>
            </div>
            <div class="agricart-pps-print__meta agricart-pps-print__meta--right">
                <div><strong>Date:</strong> {{ \Illuminate\Support\Carbon::parse($sheetDate)->format('d M Y') }}</div>
                @if (filled($purchaserName))
                    <div><strong>Purchaser:</strong> {{ $purchaserName }}</div>
                @endif
            </div>
        </div>

        <table class="agricart-pps-print-table agricart-pps-print-table--vendors">
            <thead>
                <tr>
                    <th class="agricart-pps-print-th-sr">No.</th>
                    <th>Vendor / Supplier</th>
                    <th class="agricart-pps-print-th-payment">Payment</th>
                    <th class="agricart-pps-print-th-check">OK</th>
                    <th class="agricart-pps-print-th-check">Dispute</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($printVendorLines as $line)
                    <tr>
                        <td>{{ $line['serial'] }}</td>
                        <td>{{ $line['vendor_name'] }}</td>
                        <td class="agricart-pps-print-td-num">{{ filled($line['payment'] ?? '') ? $line['payment'] : '' }}</td>
                        <td class="agricart-pps-print-td-check"><span class="agricart-pps-print-box"></span></td>
                        <td class="agricart-pps-print-td-check"><span class="agricart-pps-print-box"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="agricart-pps-print-block">
            <h3 class="agricart-pps-print-block__title">Payment Sources</h3>
            <table class="agricart-pps-print-table">
                <thead>
                    <tr>
                        <th class="agricart-pps-print-th-sr">No.</th>
                        <th>Source</th>
                        <th class="agricart-pps-print-th-payment">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($printPaymentSources as $index => $line)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $line['source'] }}</td>
                            <td class="agricart-pps-print-td-num">{{ $line['amount'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="agricart-pps-print-empty">No payment sources entered</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="agricart-pps-print-totals">
            <div class="agricart-pps-print-totals__cell">
                <span class="agricart-pps-print-totals__label">Total Bills</span>
                <strong>{{ $vendorCount }}</strong>
            </div>
            <div class="agricart-pps-print-totals__cell">
                <span class="agricart-pps-print-totals__label">Vendor Expense</span>
                <strong>{{ $vendorTotal }}</strong>
            </div>
            <div class="agricart-pps-print-totals__cell">
                <span class="agricart-pps-print-totals__label">Money Received</span>
                <strong>{{ $sourceTotal }}</strong>
            </div>
            <div class="agricart-pps-print-totals__cell">
                <span class="agricart-pps-print-totals__label">Balance</span>
                <strong>{{ $balance }}</strong>
            </div>
        </div>

        @if (filled($notes))
            <p class="agricart-pps-print-notes"><strong>Notes:</strong> {{ $notes }}</p>
        @endif
    </div>
</div>
