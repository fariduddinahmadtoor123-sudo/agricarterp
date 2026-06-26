<link rel="stylesheet" href="{{ asset('css/pos-sale-worksheet.css') }}?v={{ @filemtime(public_path('css/pos-sale-worksheet.css')) ?: 1 }}" />

<div
    @class([
        'agricart-pos-worksheet agricart-sales-quotation-worksheet agricart-pp-worksheet agricart-pu-worksheet',
        'agricart-pos-paper-80mm' => $printPaperSize === '80mm',
        'agricart-pos-paper-58mm' => $printPaperSize === '58mm',
        'agricart-pos-paper-a4' => $printPaperSize === 'a4',
    ])
    x-data
    x-on:pos-focus-sidebar-search.window="$nextTick(() => $refs.sidebarSearch?.focus())"
>
    <div class="agricart-pp-worksheet__header agricart-pu-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\SalesPos\SalesQuotations::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Sales Quotations</span>
            </a>
            @if (filled($quotationNumber))
                <span class="agricart-pp-worksheet__header-number">{{ $quotationNumber }}</span>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-center">
            <input type="date" class="agricart-pp-worksheet__date" wire:model.blur="quotationDate" @disabled(! $isEditable) />
            <div class="agricart-pp-worksheet__lang-group">
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'en']) wire:click="setNameLang('en')">EN</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'ur']) wire:click="setNameLang('ur')">UR</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'both']) wire:click="setNameLang('both')">Both</button>
            </div>
            <select class="agricart-pu-worksheet__paper-select" wire:model.live="printPaperSize" title="Print paper size">
                @foreach ($printPaperSizes as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="agricart-pp-worksheet__header-right">
            @php $sheetStatus = (string) ($sheet['status'] ?? 'draft'); @endphp
            <span @class([
                'agricart-pp-worksheet__status',
                'agricart-pos-worksheet__status--completed' => $sheetStatus === 'finalized',
                'agricart-pos-worksheet__status--held' => $sheetStatus === 'held',
            ])>{{ config('sales-pos.quotation_statuses.' . $sheetStatus, ucfirst($sheetStatus)) }}</span>
            <span class="agricart-pp-worksheet__item-count">{{ $itemCount }} {{ $itemCount === 1 ? 'Item' : 'Items' }}</span>
            <button type="button" class="agricart-pp-worksheet__btn" onclick="window.print()">Print</button>
            @if ($isEditable)
                <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard" wire:click="discardQuotation" wire:confirm="Discard this quotation?">Discard</button>
                <button type="button" class="agricart-pp-worksheet__btn" wire:click="holdQuotation">Hold</button>
                <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="finalizeQuotation">Finalize</button>
            @else
                <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="startNewQuotation">New Quotation</button>
            @endif
        </div>
    </div>

    @if (count($heldQuotations) > 0 && $isEditable)
        <div class="agricart-pos-worksheet__held-bar agricart-pu-screen-only">
            <span class="agricart-pos-worksheet__held-label">Load Held Quotation</span>
            <select class="agricart-pu-worksheet__meta-select" wire:model="loadHeldQuotationId">
                <option value="">Select held quotation...</option>
                @foreach ($heldQuotations as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadHeldQuotation">Load</button>
        </div>
    @endif

    <div class="agricart-pos-body">
        @if ($isEditable)
            <aside class="agricart-pos-sidebar agricart-pu-screen-only">
                <div class="agricart-pos-sidebar__head">
                    <h2 class="agricart-pos-sidebar__title">Add Product</h2>
                    <p class="agricart-pos-sidebar__hint">Search by SKU, barcode, English or Urdu name</p>
                </div>

                <div class="agricart-pos-sidebar__search">
                    <span class="agricart-pp-worksheet__search-bar-plus">+</span>
                    <input
                        x-ref="sidebarSearch"
                        type="text"
                        class="agricart-pos-sidebar__input"
                        wire:model.live.debounce.300ms="productSearch"
                        wire:keydown.enter.prevent="addProductFromSearch"
                        placeholder="Search product..."
                        autocomplete="off"
                        autofocus
                    />
                </div>

                <div class="agricart-pos-sidebar__results" wire:loading.class="agricart-pos-sidebar__results--loading" wire:target="productSearch,addProductFromSearch,selectSearchResult">
                    @if (trim($productSearch) === '')
                        <p class="agricart-pos-sidebar__empty">Type at least {{ $productSearchMinChars }} characters (name, SKU or barcode).</p>
                    @elseif (mb_strlen(trim($productSearch)) < $productSearchMinChars)
                        <p class="agricart-pos-sidebar__empty">Keep typing — minimum {{ $productSearchMinChars }} characters.</p>
                    @elseif (count($searchResults) === 0)
                        <p class="agricart-pos-sidebar__empty">No products found for “{{ $productSearch }}”.</p>
                    @else
                        <ul class="agricart-pos-sidebar__list" role="listbox">
                            @foreach ($searchResults as $result)
                                <li wire:key="pos-sidebar-{{ $result['id'] }}">
                                    <button type="button" class="agricart-pos-sidebar__item" wire:click="selectSearchResult({{ (int) $result['id'] }})">
                                        @if (filled($result['thumbnail_url'] ?? null))
                                            <img src="{{ $result['thumbnail_url'] }}" alt="" class="agricart-pos-sidebar__thumb" />
                                        @else
                                            <span class="agricart-pos-sidebar__thumb agricart-pos-sidebar__thumb--empty"></span>
                                        @endif
                                        <span class="agricart-pos-sidebar__item-text">
                                            <strong>{{ \App\Services\SalesPos\PosSaleLineBuilder::displayName($result, $nameLang) }}</strong>
                                            <small>{{ $result['product_number'] }} · Stock: {{ $result['on_hand'] ?: '0' }} · {{ $currencyCode }} {{ $result['sale_rate'] ?: '0' }}</small>
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        @endif

        <div class="agricart-pos-main">
            <div @class([
                'agricart-pos-invoice',
                'agricart-pos-invoice--rtl' => $nameLang === 'ur',
            ])>
                <div @class([
                    'agricart-pos-screen-bar',
                    'agricart-pos-screen-bar--rtl' => $nameLang === 'ur',
                    'agricart-pu-screen-only',
                ])>
                    <div class="agricart-pos-screen-bar__customer">
                        <label class="agricart-pos-screen-bar__label" for="pos-customer-search">
                            {{ $nameLang === 'ur' ? 'گاہک' : 'Customer' }}
                            <span class="agricart-pos-invoice__required">*</span>
                        </label>
                        <div class="agricart-pos-screen-bar__customer-field">
                            <input
                                id="pos-customer-search"
                                type="text"
                                class="agricart-pos-invoice__customer-input"
                                wire:model.live.debounce.300ms="customerSearch"
                                placeholder="{{ $nameLang === 'ur' ? 'نام یا فون تلاش کریں...' : 'Search name or phone...' }}"
                                autocomplete="off"
                                @disabled(! $isEditable)
                            />
                            <a href="{{ \App\Filament\Pages\Contacts\Customers::getUrl() }}" class="agricart-pos-invoice__customer-add" target="_blank" title="Add customer">+</a>
                            @if ($showCustomerDropdown && filled($customerSearch) && count($customerSearchResults) > 0 && $isEditable)
                                <ul class="agricart-pos-invoice__customer-dropdown" role="listbox">
                                    @foreach ($customerSearchResults as $id => $label)
                                        <li wire:key="pos-customer-{{ $id }}">
                                            <button type="button" class="agricart-pos-invoice__customer-option" wire:click="selectCustomer({{ $id }})">{{ $label }}</button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                    @if (filled($sheet['customer_name'] ?? null))
                        <p class="agricart-pos-screen-bar__selected">
                            {{ $sheet['customer_name'] }}
                            @if (filled($sheet['customer_mobile'] ?? null))
                                <span class="agricart-pos-screen-bar__selected-meta">{{ $sheet['customer_mobile'] }}</span>
                            @endif
                        </p>
                    @endif
                </div>

                <div @class([
                    'agricart-pos-inv-header',
                    'agricart-pos-inv-header--rtl' => $nameLang === 'ur',
                    'agricart-pu-print-only',
                ])>
                    <div class="agricart-pos-inv-header__top">
                        @php
                            $primaryName = ($nameLang === 'ur' && filled($companyNameUr)) ? $companyNameUr : $companyNameEn;
                            $secondaryName = ($nameLang === 'ur') ? $companyNameEn : $companyNameUr;
                            $displayAddress = ($nameLang === 'ur' && filled($companyAddressUr)) ? $companyAddressUr : $companyAddressEn;
                        @endphp

                        <div class="agricart-pos-inv-header__masthead">
                            <div class="agricart-pos-inv-header__masthead-brand">
                                <h1 class="agricart-pos-inv-header__store-name">{{ $primaryName }}</h1>
                                @if (filled($secondaryName) && $nameLang !== 'both')
                                    <p class="agricart-pos-inv-header__store-sub">{{ $secondaryName }}</p>
                                @elseif ($nameLang === 'both' && filled($companyNameUr))
                                    <p class="agricart-pos-inv-header__store-sub">{{ $companyNameUr }}</p>
                                @endif
                            </div>
                            <div class="agricart-pos-inv-header__document">
                                <h2 class="agricart-pos-inv-header__doc-title">
                                    {{ $nameLang === 'ur' ? 'سیل کوٹیشن' : 'Sales Quotation' }}
                                </h2>
                                <dl class="agricart-pos-inv-header__doc-meta">
                                    <div class="agricart-pos-inv-header__doc-meta-row">
                                        <dt>{{ $nameLang === 'ur' ? 'تاریخ' : 'Date' }}</dt>
                                        <dd>{{ $quotationDate }}</dd>
                                    </div>
                                    <div class="agricart-pos-inv-header__doc-meta-row">
                                        <dt>{{ $nameLang === 'ur' ? 'کوٹیشن نمبر' : 'Quotation #' }}</dt>
                                        <dd>{{ $quotationNumber }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="agricart-pos-inv-header__main">
                            <div class="agricart-pos-inv-header__seller">
                                <div class="agricart-pos-inv-header__seller-body">
                                    <div class="agricart-pos-inv-header__logo-box">
                                        @if (filled($companyLogoUrl))
                                            <img src="{{ $companyLogoUrl }}" alt="{{ $primaryName }}" class="agricart-pos-inv-header__logo" />
                                        @else
                                            <div class="agricart-pos-inv-header__logo-fallback" aria-hidden="true">
                                                {{ mb_strtoupper(mb_substr($primaryName, 0, 1)) }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="agricart-pos-inv-header__contact">
                                        @if (filled($displayAddress))
                                            <p class="agricart-pos-inv-header__line">{{ $displayAddress }}</p>
                                        @endif
                                        @foreach ($companyPhones as $phone)
                                            @if (filled($phone['phone_number'] ?? null))
                                                <p class="agricart-pos-inv-header__line">
                                                    @if (filled($phone['contact_person'] ?? null))
                                                        <span class="agricart-pos-inv-header__label">{{ $phone['contact_person'] }}:</span>
                                                    @endif
                                                    {{ $phone['phone_number'] }}
                                                </p>
                                            @endif
                                        @endforeach
                                        @foreach ($companyEmails as $email)
                                            @if (filled($email))
                                                <p class="agricart-pos-inv-header__line">{{ $email }}</p>
                                            @endif
                                        @endforeach
                                        @if (filled($companyWebsiteUrl))
                                            <p class="agricart-pos-inv-header__line">{{ $companyWebsiteUrl }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="agricart-pos-inv-header__billto-print">
                        <strong>{{ $nameLang === 'ur' ? 'گاہک:' : 'Bill To:' }}</strong>
                        {{ $sheet['customer_name'] ?? config('sales-pos.walk_in_customer_label') }}
                        @if (filled($sheet['customer_mobile'] ?? null)) · {{ $sheet['customer_mobile'] }} @endif
                    </div>
                </div>

                <div class="agricart-pos-invoice__grid-panel">
                <div class="agricart-pp-worksheet__grid-wrap agricart-pu-worksheet__grid-wrap agricart-pos-invoice__grid-wrap">
                    <div class="agricart-pp-worksheet__grid-scroll agricart-pu-worksheet__grid-scroll">
                        <table class="agricart-pp-worksheet__grid agricart-pu-worksheet__grid agricart-pos-invoice__grid">
                            <thead>
                                <tr>
                                    <th class="agricart-pp-worksheet__col-sr agricart-pu-th"><span class="agricart-pu-th__text">Sr #</span></th>
                                    <th class="agricart-pp-worksheet__col-thumb agricart-pu-th"><span class="agricart-pu-th__text">Product Image</span></th>
                                    <th class="agricart-pp-worksheet__col-name agricart-pu-th"><span class="agricart-pu-th__text">Product Name</span></th>
                                    <th class="agricart-pos-invoice__col-attributes agricart-pu-th"><span class="agricart-pu-th__text">Product Attributes</span></th>
                                    <th class="agricart-pos-invoice__col-unit agricart-pu-th"><span class="agricart-pu-th__text">Meas. Unit</span></th>
                                    <th class="agricart-pp-worksheet__col-num agricart-pu-th"><span class="agricart-pu-th__text">Sale Qty</span></th>
                                    <th class="agricart-pp-worksheet__col-num agricart-pu-th"><span class="agricart-pu-th__text">Unit Rate</span></th>
                                    <th class="agricart-pp-worksheet__col-num agricart-pu-th"><span class="agricart-pu-th__text">Line Total</span></th>
                                    <th class="agricart-pp-worksheet__col-action agricart-pu-screen-only"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $index => $row)
                                    <tr wire:key="pos-line-{{ $row['line_id'] }}">
                                        <td class="agricart-pp-worksheet__col-sr">{{ $index + 1 }}</td>
                                        <td class="agricart-pp-worksheet__col-thumb">
                                            @if (filled($row['thumbnail_url'] ?? null))
                                                <img src="{{ $row['thumbnail_url'] }}" alt="" class="agricart-pp-worksheet__thumb agricart-product-table-image" />
                                            @endif
                                        </td>
                                        <td class="agricart-pp-worksheet__col-name">
                                            <div class="agricart-pp-worksheet__product-name">{{ \App\Services\SalesPos\PosSaleLineBuilder::displayName($row, $nameLang) }}</div>
                                            <div class="agricart-pp-worksheet__product-sku">{{ $row['product_number'] ?? '' }}@if (filled($row['brand_name'] ?? null)) · {{ $row['brand_name'] }} @endif</div>
                                        </td>
                                        <td class="agricart-pos-invoice__attributes agricart-pos-invoice__col-attributes">{{ $row['attributes_label'] ?? ($row['brand_name'] ?? '') }}</td>
                                        <td class="agricart-pos-invoice__col-unit">{{ $row['unit_label'] ?? '' }}</td>
                                        <td class="agricart-pp-worksheet__col-num">
                                            @if ($isEditable)
                                                <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.qty" />
                                            @else
                                                {{ $row['qty'] ?? '' }}
                                            @endif
                                        </td>
                                        <td class="agricart-pp-worksheet__col-num">
                                            @if ($isEditable)
                                                <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.unit_price" />
                                            @else
                                                {{ $row['unit_price'] ?? '' }}
                                            @endif
                                        </td>
                                        <td class="agricart-pp-worksheet__col-num">
                                            <span class="agricart-pu-worksheet__line-total">{{ $row['line_total'] ?? '' }}</span>
                                        </td>
                                        <td class="agricart-pp-worksheet__col-action agricart-pu-screen-only">
                                            @if ($isEditable)
                                                <button type="button" class="agricart-pp-worksheet__remove" wire:click="removeRow('{{ $row['line_id'] }}')" title="Remove">&times;</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="agricart-pos-invoice__empty-row">
                                        <td colspan="9" class="agricart-pos-invoice__empty-message">
                                            @if ($isEditable)
                                                Search a product in the left panel to add it to this quotation.
                                            @else
                                                No products on this quotation.
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="agricart-pu-worksheet__grand-total-row">
                                    <td colspan="7" class="agricart-pu-worksheet__grand-total-label">{{ $quotationTotalLabel }}</td>
                                    <td class="agricart-pp-worksheet__col-num">
                                        <span class="agricart-pu-worksheet__grand-total">{{ $currencyCode }} {{ $total ?: '0' }}</span>
                                    </td>
                                    <td class="agricart-pu-screen-only"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                </div>

                <div class="agricart-pos-thermal-receipt agricart-pu-print-only">
                    @foreach ($rows as $index => $row)
                        <article class="agricart-pos-thermal-item" wire:key="pos-thermal-{{ $row['line_id'] }}">
                            <div class="agricart-pos-thermal-item__primary">
                                <span class="agricart-pos-thermal-item__sr">{{ $index + 1 }}</span>
                                @if (filled($row['thumbnail_url'] ?? null))
                                    <img src="{{ $row['thumbnail_url'] }}" alt="" class="agricart-pos-thermal-item__thumb" />
                                @endif
                                <div class="agricart-pos-thermal-item__info">
                                    <p class="agricart-pos-thermal-item__name">{{ \App\Services\SalesPos\PosSaleLineBuilder::displayName($row, $nameLang) }}</p>
                                    <p class="agricart-pos-thermal-item__sku">{{ $row['product_number'] ?? '' }}</p>
                                </div>
                            </div>
                            <div class="agricart-pos-thermal-item__amounts">
                                <span><em>{{ $nameLang === 'ur' ? 'مقدار' : 'Qty' }}</em> {{ $row['qty'] ?? '' }}</span>
                                <span><em>{{ $nameLang === 'ur' ? 'ریٹ' : 'Rate' }}</em> {{ $row['unit_price'] ?? '' }}</span>
                                <span><em>{{ $nameLang === 'ur' ? 'کل' : 'Total' }}</em> {{ $row['line_total'] ?? '' }}</span>
                            </div>
                        </article>
                    @endforeach
                    <div class="agricart-pos-thermal-total">
                        <span>{{ $quotationTotalLabel }}</span>
                        <strong>{{ $currencyCode }} {{ $total ?: '0' }}</strong>
                    </div>
                </div>

                @if (count($saleControls) > 0)
                    <div class="agricart-pos-controls">
                        <div class="agricart-pos-controls__header">
                            <h3 class="agricart-pos-controls__title">Product Controls</h3>
                            <label class="agricart-pos-controls__print-toggle agricart-pu-screen-only">
                                <input type="checkbox" wire:model.live="printControls" @disabled(! $isEditable) />
                                <span>Print controls on invoice</span>
                            </label>
                        </div>
                        <div class="agricart-pos-controls__list">
                            @foreach ($saleControls as $controlGroup)
                                <div class="agricart-pos-controls__group" wire:key="pos-controls-{{ md5($controlGroup['product_number'] . $controlGroup['product_name']) }}">
                                    <div class="agricart-pos-controls__product">
                                        {{ $controlGroup['product_name'] }}
                                        @if (filled($controlGroup['product_number']))
                                            <small>{{ $controlGroup['product_number'] }}</small>
                                        @endif
                                    </div>
                                    <ul class="agricart-pos-controls__items">
                                        @foreach ($controlGroup['controls'] as $controlLabel)
                                            <li wire:key="pos-control-{{ md5($controlGroup['product_number'] . $controlLabel) }}">{{ $controlLabel }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($printControls && count($saleControls) > 0)
                    <div class="agricart-pos-controls__print-block agricart-pu-print-only">
                        <strong>Product Controls</strong>
                        @foreach ($saleControls as $controlGroup)
                            <p><strong>{{ $controlGroup['product_name'] }}</strong></p>
                            <ul>
                                @foreach ($controlGroup['controls'] as $controlLabel)
                                    <li>{{ $controlLabel }}</li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>
                @endif

                @if (filled($notes))
                    <p class="agricart-pos-invoice__notes-print agricart-pu-print-only"><strong>Notes:</strong> {{ $notes }}</p>
                @endif

                <div class="agricart-pos-inv-footer agricart-pu-print-only">
                    <table class="agricart-pos-inv-footer__table">
                        <tbody>
                            <tr>
                                <th>{{ $quotationAmountLabel }}</th>
                                <td>{{ $currencyCode }} {{ $total ?: '0' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="agricart-pos-footer-bar agricart-pu-screen-only">
        <label class="agricart-pos-footer-bar__field agricart-pos-footer-bar__field--notes">
            <span class="agricart-pu-worksheet__meta-label">Notes</span>
            <input type="text" class="agricart-pos-footer-bar__input" wire:model.blur="notes" placeholder="Optional notes..." @disabled(! $isEditable) />
        </label>
        @if ($isEditable)
            <label class="agricart-pos-footer-bar__field">
                <span class="agricart-pu-worksheet__meta-label">Hold Label</span>
                <input type="text" class="agricart-pos-footer-bar__input" wire:model.blur="heldLabel" placeholder="Optional hold note..." />
            </label>
        @endif
        <div class="agricart-pos-footer-bar__change">
            {{ $quotationTotalLabel }}: <strong>{{ $currencyCode }} {{ $total ?: '0' }}</strong>
        </div>
    </div>
</div>
