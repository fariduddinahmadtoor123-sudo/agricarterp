<link rel="stylesheet" href="{{ asset('css/pos-sale-worksheet.css') }}?v={{ @filemtime(public_path('css/pos-sale-worksheet.css')) ?: 1 }}" />

<div
    @class([
        'agricart-pos-worksheet agricart-sales-return-worksheet agricart-pp-worksheet agricart-pu-worksheet',
        'agricart-pos-paper-80mm' => $printPaperSize === '80mm',
        'agricart-pos-paper-58mm' => $printPaperSize === '58mm',
        'agricart-pos-paper-a4' => $printPaperSize === 'a4',
    ])
>
    <div class="agricart-pp-worksheet__header agricart-pu-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\SalesPos\SalesReturns::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Sales Returns</span>
            </a>
            @if (filled($returnNumber))
                <span class="agricart-pp-worksheet__header-number">{{ $returnNumber }}</span>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-center">
            <input type="date" class="agricart-pp-worksheet__date" wire:model.blur="returnDate" @disabled(! $isEditable) />
            <div class="agricart-pp-worksheet__lang-group">
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'en']) wire:click="setNameLang('en')" @disabled(! $isEditable)>EN</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'ur']) wire:click="setNameLang('ur')" @disabled(! $isEditable)>UR</button>
                <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'both']) wire:click="setNameLang('both')" @disabled(! $isEditable)>Both</button>
            </div>
            @if ($saleLoaded)
                <select class="agricart-pu-worksheet__paper-select" wire:model.live="printPaperSize" title="Print paper size">
                    @foreach ($printPaperSizes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-right">
            @php $sheetStatus = (string) ($sheet['status'] ?? 'draft'); @endphp
            <span @class([
                'agricart-pp-worksheet__status',
                'agricart-pos-worksheet__status--completed' => $sheetStatus === 'completed',
            ])>{{ config('sales-pos.return_statuses.' . $sheetStatus, ucfirst($sheetStatus)) }}</span>
            <span class="agricart-pp-worksheet__item-count">{{ $itemCount }} {{ $itemCount === 1 ? 'Item' : 'Items' }}</span>
            @if ($saleLoaded)
                <button type="button" class="agricart-pp-worksheet__btn" onclick="window.print()">Print</button>
            @endif
            @if ($isEditable)
                <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard" wire:click="discardReturn" wire:confirm="Discard this return?">Discard</button>
                @if ($saleLoaded)
                    <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="completeReturn">Complete Return</button>
                @endif
            @else
                <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="startNewReturn">New Return</button>
            @endif
        </div>
    </div>

    <div class="agricart-pos-main">
        <div @class([
            'agricart-pos-invoice',
            'agricart-pos-invoice--rtl' => $nameLang === 'ur',
        ])>
            @if ($isEditable && ! $saleLoaded)
                <div class="agricart-pos-return-load agricart-pu-screen-only">
                    <label class="agricart-pos-return-load__label" for="load-sale-number">Load POS Sale Invoice #</label>
                    <div class="agricart-pos-return-load__row">
                        <input
                            id="load-sale-number"
                            type="text"
                            class="agricart-pos-invoice__customer-input"
                            wire:model="loadSaleNumber"
                            wire:keydown.enter.prevent="loadSale"
                            placeholder="PS-YYYYMMDD-####"
                            autocomplete="off"
                        />
                        <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="loadSale">Load Sale</button>
                        <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard" wire:click="discardReturn" wire:confirm="Discard this return?">Discard</button>
                    </div>
                    <p class="agricart-pos-return-load__hint">Enter a completed POS sale number. The original invoice stays unchanged; each return is saved separately.</p>
                </div>
            @endif

            @if ($saleLoaded)
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
                            $printRefundAmount = filled($refundAmount) ? $refundAmount : ($sheet['refund_amount'] ?? $total);
                            $printCreditAmount = filled($creditAmount) ? $creditAmount : ($sheet['credit_amount'] ?? '');
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
                                    {{ $nameLang === 'ur' ? 'سیل ریٹرن' : 'Sales Return' }}
                                </h2>
                                <dl class="agricart-pos-inv-header__doc-meta">
                                    <div class="agricart-pos-inv-header__doc-meta-row">
                                        <dt>{{ $nameLang === 'ur' ? 'تاریخ' : 'Date' }}</dt>
                                        <dd>{{ $returnDate }}</dd>
                                    </div>
                                    <div class="agricart-pos-inv-header__doc-meta-row">
                                        <dt>{{ $nameLang === 'ur' ? 'ریٹرن نمبر' : 'Return #' }}</dt>
                                        <dd>{{ $returnNumber }}</dd>
                                    </div>
                                    <div class="agricart-pos-inv-header__doc-meta-row">
                                        <dt>{{ $nameLang === 'ur' ? 'اصل انوائس' : 'Sale Invoice' }}</dt>
                                        <dd>{{ $sheet['sale_number'] ?? '' }}</dd>
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
                                                <p class="agricart-pos-inv-header__line">{{ $phone['phone_number'] }}</p>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="agricart-pos-inv-header__billto-print">
                        <strong>{{ $nameLang === 'ur' ? 'گاہک:' : 'Customer:' }}</strong>
                        {{ $sheet['customer_name'] ?? '' }}
                        @if (filled($sheet['customer_mobile'] ?? null)) · {{ $sheet['customer_mobile'] }} @endif
                    </div>
                </div>

                <div class="agricart-pos-return-sale-info agricart-pu-screen-only">
                    <div class="agricart-pos-return-sale-info__grid">
                        <div>
                            <span class="agricart-pos-return-sale-info__label">Sale Invoice</span>
                            <strong>{{ $sheet['sale_number'] ?? '' }}</strong>
                        </div>
                        <div>
                            <span class="agricart-pos-return-sale-info__label">Customer</span>
                            <strong>{{ $sheet['customer_name'] ?? '' }}</strong>
                            @if (filled($sheet['customer_mobile'] ?? null))
                                <small>{{ $sheet['customer_mobile'] }}</small>
                            @endif
                        </div>
                        <div>
                            <span class="agricart-pos-return-sale-info__label">Original Payment</span>
                            <strong>{{ $paymentMethods[$sheet['original_payment_method'] ?? ''] ?? ($sheet['original_payment_method'] ?? '—') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="agricart-pos-invoice__grid-panel">
                <div class="agricart-pp-worksheet__grid-wrap agricart-pu-worksheet__grid-wrap">
                    <div class="agricart-pp-worksheet__grid-scroll agricart-pu-worksheet__grid-scroll">
                        <table class="agricart-pp-worksheet__grid agricart-pu-worksheet__grid agricart-pos-return-grid">
                            <thead>
                                <tr>
                                    <th class="agricart-pp-worksheet__col-sr">Sr #</th>
                                    <th class="agricart-pp-worksheet__col-thumb agricart-pu-screen-only">Image</th>
                                    <th class="agricart-pp-worksheet__col-name">Product</th>
                                    <th class="agricart-pp-worksheet__col-num agricart-pu-screen-only">Sold</th>
                                    <th class="agricart-pp-worksheet__col-num agricart-pu-screen-only">Returned</th>
                                    <th class="agricart-pp-worksheet__col-num">Return Qty</th>
                                    <th class="agricart-pp-worksheet__col-num">Rate</th>
                                    <th class="agricart-pp-worksheet__col-num">Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $index => $row)
                                    <tr wire:key="return-line-{{ $row['line_id'] }}">
                                        <td class="agricart-pp-worksheet__col-sr">{{ $index + 1 }}</td>
                                        <td class="agricart-pp-worksheet__col-thumb agricart-pu-screen-only">
                                            @if (filled($row['thumbnail_url'] ?? null))
                                                <img src="{{ $row['thumbnail_url'] }}" alt="" class="agricart-pp-worksheet__thumb" />
                                            @endif
                                        </td>
                                        <td class="agricart-pp-worksheet__col-name">
                                            <div class="agricart-pp-worksheet__product-name">{{ \App\Services\SalesPos\PosSaleLineBuilder::displayName($row, $nameLang) }}</div>
                                            <div class="agricart-pp-worksheet__product-sku">{{ $row['product_number'] ?? '' }}</div>
                                        </td>
                                        <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only">{{ $row['sold_qty'] ?? '' }}</td>
                                        <td class="agricart-pp-worksheet__col-num agricart-pu-screen-only">{{ $row['previously_returned_qty'] ?: '0' }}</td>
                                        <td class="agricart-pp-worksheet__col-num">
                                            @if ($isEditable)
                                                <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.return_qty" placeholder="0" />
                                            @else
                                                {{ $row['return_qty'] ?? '' }}
                                            @endif
                                        </td>
                                        <td class="agricart-pp-worksheet__col-num">{{ $row['unit_price'] ?? '' }}</td>
                                        <td class="agricart-pp-worksheet__col-num">{{ $row['line_total'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="agricart-pu-worksheet__grand-total-row">
                                    <td colspan="7" class="agricart-pu-worksheet__grand-total-label">Return Total</td>
                                    <td class="agricart-pp-worksheet__col-num">
                                        <span class="agricart-pu-worksheet__grand-total">{{ $currencyCode }} {{ $total ?: '0' }}</span>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                </div>

                <div class="agricart-pos-thermal-receipt agricart-pu-print-only">
                    @foreach ($rows as $index => $row)
                        @if ($isEditable || \App\Services\SalesPos\PosSaleLineBuilder::numeric($row['return_qty'] ?? '') > 0)
                            <article class="agricart-pos-thermal-item" wire:key="return-thermal-{{ $row['line_id'] }}">
                                <div class="agricart-pos-thermal-item__primary">
                                    <span class="agricart-pos-thermal-item__sr">{{ $index + 1 }}</span>
                                    <div class="agricart-pos-thermal-item__info">
                                        <p class="agricart-pos-thermal-item__name">{{ \App\Services\SalesPos\PosSaleLineBuilder::displayName($row, $nameLang) }}</p>
                                        <p class="agricart-pos-thermal-item__sku">{{ $row['product_number'] ?? '' }}</p>
                                    </div>
                                </div>
                                <div class="agricart-pos-thermal-item__amounts">
                                    <span><em>{{ $nameLang === 'ur' ? 'واپسی' : 'Return' }}</em> {{ $row['return_qty'] ?? '' }}</span>
                                    <span><em>{{ $nameLang === 'ur' ? 'ریٹ' : 'Rate' }}</em> {{ $row['unit_price'] ?? '' }}</span>
                                    <span><em>{{ $nameLang === 'ur' ? 'کل' : 'Total' }}</em> {{ $row['line_total'] ?? '' }}</span>
                                </div>
                            </article>
                        @endif
                    @endforeach
                    <div class="agricart-pos-thermal-total">
                        <span>{{ $nameLang === 'ur' ? 'ریٹرن کل' : 'Return Total' }}</span>
                        <strong>{{ $currencyCode }} {{ $total ?: '0' }}</strong>
                    </div>
                </div>

                @if (filled($notes) || filled($refundNotes))
                    <p class="agricart-pos-invoice__notes-print agricart-pu-print-only">
                        @if (filled($notes))<strong>Notes:</strong> {{ $notes }} @endif
                        @if (filled($refundNotes))<strong>Refund:</strong> {{ $refundNotes }} @endif
                    </p>
                @endif

                <div class="agricart-pos-inv-footer agricart-pu-print-only">
                    <table class="agricart-pos-inv-footer__table">
                        <tbody>
                            <tr>
                                <th>{{ $nameLang === 'ur' ? 'ریفنڈ طریقہ' : 'Refund Method' }}</th>
                                <td>{{ $refundMethods[$refundMethod] ?? $refundMethods[$sheet['refund_method'] ?? ''] ?? '' }}</td>
                            </tr>
                            @if (in_array($refundMethod, ['customer_credit'], true) || ($refundMethod === 'original_payment' && ($sheet['original_payment_method'] ?? '') === 'credit') || in_array($sheet['refund_method'] ?? '', ['customer_credit'], true))
                                <tr>
                                    <th>{{ $nameLang === 'ur' ? 'کھاتے میں کریڈٹ' : 'Credit to Account' }}</th>
                                    <td>{{ $currencyCode }} {{ $printCreditAmount ?: $total }}</td>
                                </tr>
                            @else
                                <tr>
                                    <th>{{ $nameLang === 'ur' ? 'نقد ریفنڈ' : 'Cash Refund' }}</th>
                                    <td>{{ $currencyCode }} {{ $printRefundAmount ?: '0' }}</td>
                                </tr>
                            @endif
                            <tr>
                                <th>{{ $nameLang === 'ur' ? 'حیثیت' : 'Settlement' }}</th>
                                <td>{{ $refundStatuses[$refundStatus] ?? $refundStatuses[$sheet['refund_status'] ?? ''] ?? '' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if (count($saleHistory) > 0)
                    <div class="agricart-pos-return-history agricart-pu-screen-only">
                        <h3 class="agricart-pos-return-history__title">Return History for {{ $sheet['sale_number'] ?? '' }}</h3>
                        <table class="agricart-pos-return-history__table">
                            <thead>
                                <tr>
                                    <th>Return #</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Refund</th>
                                    <th>Settlement</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($saleHistory as $history)
                                    @if (($history['id'] ?? '') !== ($sheet['id'] ?? ''))
                                        <tr wire:key="return-history-{{ $history['id'] }}">
                                            <td>{{ $history['return_number'] ?? '' }}</td>
                                            <td>{{ $history['return_date'] ?? '' }}</td>
                                            <td>{{ $currencyCode }} {{ $history['return_total'] ?: '0' }}</td>
                                            <td>{{ $refundMethods[$history['refund_method'] ?? ''] ?? ($history['refund_method'] ?? '') }}</td>
                                            <td>{{ $refundStatuses[$history['refund_status'] ?? ''] ?? ($history['refund_status'] ?? '') }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif
        </div>
    </div>

    @if ($saleLoaded && $isEditable)
        <div class="agricart-pos-footer-bar agricart-pu-screen-only">
            <label class="agricart-pos-footer-bar__field">
                <span class="agricart-pu-worksheet__meta-label">Refund Method</span>
                <select class="agricart-pu-worksheet__meta-select" wire:model.live="refundMethod">
                    @foreach ($refundMethods as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            @if (! in_array($refundMethod, ['customer_credit'], true) && ($refundMethod !== 'original_payment' || ($sheet['original_payment_method'] ?? '') !== 'credit'))
                <label class="agricart-pos-footer-bar__field">
                    <span class="agricart-pu-worksheet__meta-label">Cash Refund Amount</span>
                    <div class="agricart-pos-footer-bar__amount">
                        <input type="text" class="agricart-pu-worksheet__payment-input" wire:model.blur="refundAmount" placeholder="0.00" />
                        <button type="button" class="agricart-pp-worksheet__load-btn" wire:click="fillReturnTotal">Full</button>
                    </div>
                </label>
                <label class="agricart-pos-footer-bar__field">
                    <span class="agricart-pu-worksheet__meta-label">Refund Settled</span>
                    <select class="agricart-pu-worksheet__meta-select" wire:model.live="refundStatus">
                        @foreach ($refundStatuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <div class="agricart-pos-footer-bar__change">
                    Credit to account: <strong>{{ $currencyCode }} {{ $creditAmount ?: $total }}</strong>
                </div>
            @endif
            <label class="agricart-pos-footer-bar__field agricart-pos-footer-bar__field--notes">
                <span class="agricart-pu-worksheet__meta-label">Notes</span>
                <input type="text" class="agricart-pos-footer-bar__input" wire:model.blur="notes" placeholder="Return notes..." />
            </label>
            <label class="agricart-pos-footer-bar__field agricart-pos-footer-bar__field--notes">
                <span class="agricart-pu-worksheet__meta-label">Refund Notes</span>
                <input type="text" class="agricart-pos-footer-bar__input" wire:model.blur="refundNotes" placeholder="Cheque no., paid to customer, etc." />
            </label>
        </div>
    @elseif ($saleLoaded && ! $isEditable)
        <div class="agricart-pos-footer-bar agricart-pu-screen-only">
            <div class="agricart-pos-footer-bar__change">
                Return Total: <strong>{{ $currencyCode }} {{ $sheet['return_total'] ?: $total }}</strong>
                · {{ $refundMethods[$sheet['refund_method'] ?? ''] ?? '' }}
                · {{ $refundStatuses[$sheet['refund_status'] ?? ''] ?? '' }}
            </div>
        </div>
    @endif
</div>
