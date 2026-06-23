<div @class([
    'agricart-pu-worksheet agricart-pp-worksheet',
    'agricart-pu-paper-a4' => $printPaperSize === 'a4',
    'agricart-pu-paper-compact' => $printPaperSize === 'compact',
])>
    <div class="agricart-pp-worksheet__header agricart-pu-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\PurchasingInventory\Purchases::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Purchases</span>
            </a>
            <span class="agricart-pp-worksheet__header-title">
                {{ filled($sheetTitle) ? $sheetTitle : ($isNewPurchase ? 'New Purchase' : $purchaseNumber) }}
            </span>
            @if (filled($purchaseNumber))
                <span class="agricart-pp-worksheet__header-number">{{ $purchaseNumber }}</span>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-center">
            <input type="date" class="agricart-pp-worksheet__date" wire:model.blur="sheetDate" />
            <input type="text" class="agricart-pp-worksheet__title-input" wire:model.blur="sheetTitle" placeholder="Optional title" />
            <select class="agricart-pu-worksheet__paper-select" wire:model.live="printPaperSize" title="Print paper size">
                @foreach ($printPaperSizes as $key => $label)
                    <option value="{{ $key }}">Print: {{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="agricart-pp-worksheet__header-right">
            @php $sheetStatus = (string) ($sheet['status'] ?? 'draft'); @endphp
            <span @class([
                'agricart-pp-worksheet__status',
                'agricart-pp-worksheet__status--saved' => $sheetStatus === 'saved',
                'agricart-pp-worksheet__status--draft' => $sheetStatus !== 'saved',
            ])>{{ $sheetStatus === 'saved' ? 'Saved' : 'Draft' }}</span>
            <span class="agricart-pp-worksheet__item-count">{{ $itemCount }} {{ $itemCount === 1 ? 'Item' : 'Items' }}</span>
            <button type="button" class="agricart-pp-worksheet__btn" onclick="window.print()">Print</button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard" wire:click="discardPurchase" wire:confirm="Discard this purchase invoice?">Discard</button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="savePurchase">Save Invoice</button>
        </div>
    </div>

    {{-- Supplier & store (set when creating invoice) --}}
    <div class="agricart-pu-worksheet__meta agricart-pu-screen-only">
        <label class="agricart-pu-worksheet__meta-field">
            <span class="agricart-pu-worksheet__meta-label">Supplier</span>
            <select class="agricart-pu-worksheet__meta-select" wire:model.live="supplierId">
                <option value="">Select supplier...</option>
                @foreach ($supplierOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label class="agricart-pu-worksheet__meta-field">
            <span class="agricart-pu-worksheet__meta-label">Store</span>
            @if (config('purchasing-inventory.single_store_mode', false))
                <span class="agricart-pu-worksheet__meta-readonly">{{ $sheet['store_name'] ?? '' }}</span>
            @else
                <select class="agricart-pu-worksheet__meta-select" wire:model.live="storeKey">
                    @foreach ($storeOptions as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </label>
    </div>

    <div class="agricart-pu-worksheet__import agricart-pu-screen-only">
        <div class="agricart-pu-worksheet__import-group">
            <span class="agricart-pu-worksheet__import-label">Load Planning</span>
            <select class="agricart-pu-worksheet__import-select" wire:model="linkPlanningId">
                <option value="">Select planning sheet...</option>
                @foreach ($planningOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="importPlanningSheet">Load</button>
        </div>
        <div class="agricart-pu-worksheet__import-group">
            <span class="agricart-pu-worksheet__import-label">Load Quotation</span>
            <select class="agricart-pu-worksheet__import-select" wire:model="linkQuotationId">
                <option value="">Select quotation...</option>
                @foreach ($quotationOptions as $option)
                    <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                @endforeach
            </select>
            <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="importQuotationSheet">Load</button>
        </div>
        <div class="agricart-pu-worksheet__invoice-upload">
            <span class="agricart-pu-worksheet__import-label">Supplier Invoice Photo</span>
            <input type="file" accept="image/*" wire:model="invoiceImageUpload" class="agricart-pu-worksheet__file-input" />
            @if ($invoiceImageUrl)
                <a href="{{ $invoiceImageUrl }}" target="_blank" class="agricart-pu-worksheet__image-link">View</a>
                <button type="button" class="agricart-pu-worksheet__image-remove" wire:click="removeInvoiceImage">&times;</button>
            @endif
            <div wire:loading wire:target="invoiceImageUpload" class="agricart-pu-worksheet__uploading">Uploading...</div>
        </div>
    </div>

    {{-- Toolbar --}}
    <div class="agricart-pp-worksheet__toolbar agricart-pu-screen-only">
        <div class="agricart-pp-worksheet__toolbar-row agricart-pp-worksheet__toolbar-row--category">
            <span class="agricart-pp-worksheet__load-label">LOAD</span>
            <div class="agricart-pp-worksheet__category-load">
                <div class="agricart-pp-inline-search agricart-pp-inline-search--category">
                    <input type="text" class="agricart-pp-inline-search__input" wire:model.live.debounce.250ms="categorySearch" wire:focus="focusCategorySearch" placeholder="Search category..." autocomplete="off" />
                    @if (filled($selectedCategoryLabel))
                        <button type="button" class="agricart-pp-inline-search__clear" wire:click="clearCategorySelection">&times;</button>
                    @endif
                    @if (count($categorySearchResults) > 0)
                        <ul class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--category" role="listbox">
                            @foreach ($categorySearchResults as $category)
                                <li wire:key="pu-cat-{{ $category['id'] }}">
                                    <button type="button" class="agricart-pp-inline-search__option" wire:click="selectCategoryForLoad({{ (int) $category['id'] }})">
                                        <span class="agricart-pp-inline-search__option-label">{{ $category['name'] }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
            <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadByCategory">Load</button>
        </div>
        <div class="agricart-pp-worksheet__toolbar-row agricart-pp-worksheet__toolbar-row--actions">
            <div class="agricart-pp-worksheet__quick-load">
                <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadAllProducts">All Products</button>
                <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadLowStock">Low Stock</button>
                <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadOutOfStock">Out Of Stock</button>
            </div>
            <div class="agricart-pp-worksheet__lang-group">
                <span class="agricart-pp-worksheet__lang-label">LANG</span>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'en']) wire:click="setNameLang('en')">EN</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'ur']) wire:click="setNameLang('ur')">UR</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'both']) wire:click="setNameLang('both')">Both</button>
            </div>
        </div>
    </div>

    {{-- Print header (visible only when printing) --}}
    <div class="agricart-pu-print-header agricart-pu-print-only">
        <h2>Purchase Invoice — {{ $purchaseNumber }}</h2>
        <p>{{ $sheet['supplier_name'] ?? '' }} · {{ $sheet['store_name'] ?? '' }} · {{ $sheetDate }}</p>
    </div>

    <div class="agricart-pp-worksheet__grid-wrap agricart-pu-worksheet__grid-wrap">
        <div class="agricart-pp-worksheet__search-bar agricart-pu-screen-only">
            <span class="agricart-pp-worksheet__search-bar-plus">+</span>
            <div class="agricart-pp-inline-search agricart-pp-inline-search--product">
                <input type="text" class="agricart-pp-inline-search__input" wire:model.live.debounce.300ms="productSearch" wire:keydown.enter.prevent="addProductFromSearch" placeholder="Barcode, SKU, English or Urdu name..." autocomplete="off" autofocus />
                @if (count($searchResults) > 0)
                    <ul class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--product" role="listbox">
                        @foreach ($searchResults as $result)
                            <li>
                                <button type="button" class="agricart-pp-inline-search__option agricart-pp-inline-search__option--product" wire:click="selectSearchResult({{ (int) $result['id'] }})">
                                    @if (filled($result['thumbnail_url'] ?? null))
                                        <img src="{{ $result['thumbnail_url'] }}" alt="" class="agricart-pp-inline-search__thumb" />
                                    @endif
                                    <span><strong>{{ $result['display_name'] }}</strong><small>{{ $result['barcode'] }}</small></span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="agricart-pp-worksheet__grid-scroll agricart-pu-worksheet__grid-scroll">
            <table class="agricart-pp-worksheet__grid agricart-pu-worksheet__grid">
                <thead>
                    {{-- Row 1: section groups --}}
                    <tr class="agricart-pu-worksheet__head-group agricart-pu-screen-only">
                        <th colspan="3" class="agricart-pu-worksheet__group-label">Product</th>
                        <th colspan="5" class="agricart-pu-worksheet__group-label">Quantities</th>
                        <th colspan="4" class="agricart-pu-worksheet__group-label">Purchase Rates</th>
                        <th colspan="6" class="agricart-pu-worksheet__group-label">Store Pricing Tiers</th>
                        <th colspan="2" class="agricart-pu-worksheet__group-label">Summary</th>
                    </tr>
                    {{-- Row 2: tier sub-groups --}}
                    <tr class="agricart-pu-worksheet__head-subgroup agricart-pu-screen-only">
                        <th colspan="3"></th>
                        <th colspan="5"></th>
                        <th colspan="4"></th>
                        <th colspan="2" class="agricart-pu-worksheet__subgroup-label">Wholesale</th>
                        <th colspan="2" class="agricart-pu-worksheet__subgroup-label">Super Wholesale</th>
                        <th colspan="2" class="agricart-pu-worksheet__subgroup-label">Distributor</th>
                        <th colspan="2"></th>
                    </tr>
                    {{-- Row 3: full column labels (wrap to 2 lines) --}}
                    <tr class="agricart-pu-worksheet__head-columns">
                        <th class="agricart-pu-sticky-col agricart-pu-th agricart-pu-th--narrow agricart-pu-col-sr"><span class="agricart-pu-th__text">No.</span></th>
                        <th class="agricart-pu-sticky-col agricart-pu-th agricart-pu-th--narrow agricart-pu-col-picture agricart-pu-screen-only"><span class="agricart-pu-th__text">Picture</span></th>
                        <th class="agricart-pu-sticky-col agricart-pu-col-name agricart-pu-th"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Product Name</span></th>
                        <th class="agricart-pu-th agricart-pu-col-qty agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Required Qty</span></th>
                        <th class="agricart-pu-th agricart-pu-col-qty agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Alert Qty</span></th>
                        <th class="agricart-pu-th agricart-pu-col-qty"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Purchase Qty</span></th>
                        <th class="agricart-pu-th agricart-pu-col-qty agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Received Qty</span></th>
                        <th class="agricart-pu-th agricart-pu-col-qty agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Damaged Qty</span></th>
                        <th class="agricart-pu-th agricart-pu-col-rate agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Previous Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-rate"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Purchase Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-rate"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Landing Cost</span></th>
                        <th class="agricart-pu-th agricart-pu-col-rate agricart-pu-col-rate-wide agricart-pu-screen-only"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Sale Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-pct agricart-pu-screen-only"><span class="agricart-pu-th__text">Percent</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-rate agricart-pu-screen-only"><span class="agricart-pu-th__text">Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-pct agricart-pu-screen-only"><span class="agricart-pu-th__text">Percent</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-rate agricart-pu-screen-only"><span class="agricart-pu-th__text">Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-pct agricart-pu-screen-only"><span class="agricart-pu-th__text">Percent</span></th>
                        <th class="agricart-pu-th agricart-pu-col-tier-rate agricart-pu-screen-only"><span class="agricart-pu-th__text">Rate</span></th>
                        <th class="agricart-pu-th agricart-pu-col-total"><span class="agricart-pu-th__text agricart-pu-th__text--wrap">Line Total</span></th>
                        <th class="agricart-pu-th agricart-pu-screen-only"><span class="agricart-pu-th__text"></span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $index => $row)
                        @php $lineTotal = \App\Services\PurchasingInventory\PurchaseLineBuilder::lineTotal($row); @endphp
                        <tr wire:key="pu-line-{{ $row['line_id'] }}">
                            <td class="agricart-pp-worksheet__col-sr agricart-pu-sticky-col agricart-pu-col-sr">{{ $index + 1 }}</td>
                            <td class="agricart-pp-worksheet__col-thumb agricart-pu-sticky-col agricart-pu-col-picture agricart-pu-screen-only">
                                @if (filled($row['thumbnail_url'] ?? null))
                                    <img src="{{ $row['thumbnail_url'] }}" alt="" class="agricart-pp-worksheet__thumb agricart-product-table-image" />
                                @endif
                            </td>
                            <td class="agricart-pp-worksheet__col-name agricart-pu-sticky-col agricart-pu-col-name">
                                @php $displayName = \App\Services\PurchasingInventory\PurchaseLineBuilder::displayName($row, $nameLang); @endphp
                                @if (filled($displayName))<div class="agricart-pp-worksheet__product-name">{{ $displayName }}</div>@endif
                                <div class="agricart-pp-worksheet__product-sku">{{ $row['sku'] ?? $row['barcode'] ?? '' }}</div>
                            </td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.required_qty" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.alert_qty" /></td>
                            <td class="agricart-pp-worksheet__col-num"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.purchase_qty" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.received_qty" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.damaged_qty" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.previous_rate" /></td>
                            <td class="agricart-pp-worksheet__col-num"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.purchase_rate" wire:change="recalculateRowTiers({{ $index }})" /></td>
                            <td class="agricart-pp-worksheet__col-num"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.landing_cost" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-rate-wide agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.sale_rate" wire:change="recalculateRowTiers({{ $index }})" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-pct agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input agricart-pu-worksheet__cell-input--pct" wire:model.blur="rows.{{ $index }}.wholesale_pct" wire:change="recalculateRowTiers({{ $index }})" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-rate agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.wholesale_rate" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-pct agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input agricart-pu-worksheet__cell-input--pct" wire:model.blur="rows.{{ $index }}.super_wholesale_pct" wire:change="recalculateRowTiers({{ $index }})" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-rate agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.super_wholesale_rate" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-pct agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input agricart-pu-worksheet__cell-input--pct" wire:model.blur="rows.{{ $index }}.distributor_pct" wire:change="recalculateRowTiers({{ $index }})" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-tier-rate agricart-pu-screen-only"><input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.distributor_rate" /></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-col-total"><span class="agricart-pu-worksheet__line-total">{{ \App\Services\PurchasingInventory\PurchaseLineBuilder::formatAmount($lineTotal) }}</span></td>
                            <td class="agricart-pp-worksheet__col-action agricart-pu-screen-only">
                                <button type="button" class="agricart-pp-worksheet__remove" wire:click="removeLine('{{ $row['line_id'] }}')">&times;</button>
                            </td>
                        </tr>
                    @endforeach

                    @php $placeholderCount = max(0, $minVisualRows - count($rows)); @endphp
                    @for ($p = 0; $p < $placeholderCount; $p++)
                        <tr class="agricart-pp-worksheet__placeholder-row" wire:key="pu-ph-{{ $p }}">
                            <td class="agricart-pu-sticky-col agricart-pu-col-sr">{{ count($rows) + $p + 1 }}</td>
                            <td class="agricart-pu-sticky-col agricart-pu-col-picture agricart-pu-screen-only"></td>
                            <td class="agricart-pu-sticky-col agricart-pu-col-name">
                                @if (count($rows) === 0 && $p === 0)
                                    <span class="agricart-pp-worksheet__placeholder-hint">Search above to add products.</span>
                                @endif
                            </td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num"></td>
                            <td class="agricart-pp-worksheet__col-num"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only"></td>
                            <td class="agricart-pu-col-total"></td>
                            <td class="agricart-pu-screen-only"></td>
                        </tr>
                    @endfor
                </tbody>
                <tfoot>
                    <tr class="agricart-pu-worksheet__grand-total-row">
                        <td colspan="18" class="agricart-pu-worksheet__grand-total-label agricart-pu-screen-only">Invoice Total</td>
                        <td colspan="3" class="agricart-pu-worksheet__grand-total-label agricart-pu-print-only">Invoice Total</td>
                        <td class="agricart-pu-col-total"><span class="agricart-pu-worksheet__grand-total">{{ \App\Services\PurchasingInventory\PurchaseLineBuilder::formatAmount($invoiceTotal) ?: '0.00' }}</span></td>
                        <td class="agricart-pu-screen-only"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="agricart-pu-worksheet__notes agricart-pu-screen-only">
        <label class="agricart-pu-worksheet__notes-label" for="pu-notes">Notes</label>
        <textarea id="pu-notes" class="agricart-pu-worksheet__notes-input" wire:model.blur="notes" rows="2" placeholder="Optional notes for this purchase invoice..."></textarea>
    </div>

    <div class="agricart-pu-worksheet__payment agricart-pu-screen-only">
        <div class="agricart-pu-worksheet__payment-card">
            <span class="agricart-pu-worksheet__payment-label">Invoice Total</span>
            <strong class="agricart-pu-worksheet__payment-value">{{ \App\Services\PurchasingInventory\PurchaseLineBuilder::formatAmount($invoiceTotal) ?: '0.00' }}</strong>
        </div>
        <div class="agricart-pu-worksheet__payment-card">
            <span class="agricart-pu-worksheet__payment-label">{{ $supplierBalance['label'] }}</span>
            <strong class="agricart-pu-worksheet__payment-value">{{ $supplierBalance['formatted'] }}</strong>
        </div>
        <label class="agricart-pu-worksheet__payment-field">
            <span class="agricart-pu-worksheet__payment-label">Payment Now</span>
            <input type="text" class="agricart-pu-worksheet__payment-input" wire:model.blur="paymentAmount" placeholder="0.00" />
        </label>
        <label class="agricart-pu-worksheet__payment-field agricart-pu-worksheet__payment-field--wide">
            <span class="agricart-pu-worksheet__payment-label">Payment Notes</span>
            <input type="text" class="agricart-pu-worksheet__payment-input" wire:model.blur="paymentNotes" placeholder="Cheque, cash, partial payment note..." />
        </label>
    </div>

    {{-- Invoice statuses — filled after products are added --}}
    <div class="agricart-pu-worksheet__post-invoice agricart-pu-screen-only">
        <h3 class="agricart-pu-worksheet__post-invoice-title">Invoice Status &amp; Follow-up</h3>
        <div class="agricart-pu-worksheet__post-invoice-grid">
            <label class="agricart-pu-worksheet__meta-field">
                <span class="agricart-pu-worksheet__meta-label">Payment Status</span>
                <select class="agricart-pu-worksheet__meta-select" wire:model.live="invoicePaymentStatus">
                    @foreach ($invoicePaymentStatuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="agricart-pu-worksheet__meta-field">
                <span class="agricart-pu-worksheet__meta-label">Goods Receipt Status</span>
                <select class="agricart-pu-worksheet__meta-select" wire:model.live="goodsReceiptStatus">
                    @foreach ($goodsReceiptStatuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if (($sheet['status'] ?? 'draft') === 'saved')
                <div class="agricart-pu-worksheet__meta-field">
                    <span class="agricart-pu-worksheet__meta-label">Goods Receipt</span>
                    <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="processGoodsReceipt">Process GR</button>
                </div>
            @endif
            <label class="agricart-pu-worksheet__meta-field">
                <span class="agricart-pu-worksheet__meta-label">Dispute Status</span>
                <select class="agricart-pu-worksheet__meta-select" wire:model.live="disputeStatus">
                    @foreach ($disputeStatuses as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="agricart-pu-worksheet__meta-field agricart-pu-worksheet__meta-field--wide">
                <span class="agricart-pu-worksheet__meta-label">Dispute Notes</span>
                <input type="text" class="agricart-pu-worksheet__meta-select" wire:model.blur="disputeNotes" placeholder="Damaged goods, short delivery, return, or other issue..." />
            </label>
        </div>
    </div>
</div>
