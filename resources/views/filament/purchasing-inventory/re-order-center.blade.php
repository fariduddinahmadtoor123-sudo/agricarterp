<div class="agricart-ro-center agricart-pp-worksheet">
    <div class="agricart-ro-center__intro agricart-ro-screen-only">
        <h1 class="agricart-ro-center__title">Re-Order Center</h1>
        <p class="agricart-ro-center__hint">Low / out-of-stock products appear automatically. Send all or selected lines to a purchaser — sent items move to the queue and leave this list until stock is received.</p>
    </div>

  {{-- Needs re-order --}}
    <section class="agricart-ro-section agricart-ro-screen-only">
        <div class="agricart-ro-section__head">
            <div>
                <h2 class="agricart-ro-section__title">Needs Re-Order</h2>
                <p class="agricart-ro-section__meta">{{ $candidateCount }} products</p>
            </div>
        </div>

        <div class="agricart-pp-worksheet__toolbar">
            <div class="agricart-pp-worksheet__toolbar-row agricart-pp-worksheet__toolbar-row--category">
                <span class="agricart-pp-worksheet__load-label">LOAD</span>
                <div class="agricart-pp-worksheet__category-load">
                    <div class="agricart-pp-inline-search agricart-pp-inline-search--category">
                        <input
                            type="text"
                            class="agricart-pp-inline-search__input"
                            wire:model.live.debounce.250ms="categorySearch"
                            wire:focus="focusCategorySearch"
                            placeholder="Category filter..."
                            autocomplete="off"
                        />
                        @if (filled($selectedCategoryLabel))
                            <button type="button" class="agricart-pp-inline-search__clear" wire:click="clearCategoryFilter" title="Clear category">&times;</button>
                        @endif
                        @if (count($categorySearchResults) > 0)
                            <ul class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--category" role="listbox">
                                @foreach ($categorySearchResults as $category)
                                    <li wire:key="ro-category-{{ $category['id'] }}">
                                        <button type="button" class="agricart-pp-inline-search__option" wire:click="selectCategoryFilter({{ (int) $category['id'] }})">
                                            <span class="agricart-pp-inline-search__option-label">{{ $category['name'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            <div class="agricart-pp-worksheet__toolbar-row agricart-pp-worksheet__toolbar-row--actions">
                <div class="agricart-pp-worksheet__quick-load">
                    @foreach ($stockFilters as $key => $label)
                        <button
                            type="button"
                            @class(['agricart-pp-worksheet__load-btn', 'agricart-pp-worksheet__load-btn--active' => $stockFilter === $key])
                            wire:click="setStockFilter('{{ $key }}')"
                        >{{ $label }}</button>
                    @endforeach
                </div>

                <div class="agricart-pp-worksheet__lang-group">
                    <span class="agricart-pp-worksheet__lang-label">LANG</span>
                    <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'en']) wire:click="setNameLang('en')">EN</button>
                    <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'ur']) wire:click="setNameLang('ur')">UR</button>
                    <button type="button" @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'both']) wire:click="setNameLang('both')">Both</button>
                </div>
            </div>
        </div>

        <div class="agricart-ro-send-bar">
            <input
                type="text"
                class="agricart-ro-send-bar__purchaser"
                wire:model.blur="purchaserName"
                placeholder="Purchaser name"
            />
            <button type="button" class="agricart-pp-worksheet__btn" wire:click="selectAllCandidates">Select All</button>
            <button type="button" class="agricart-pp-worksheet__btn" wire:click="clearCandidateSelection">Clear</button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="sendSelectedOrder">
                Send Selected ({{ count($selectedProductIds) }})
            </button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="sendAllCandidates">
                Send All
            </button>
        </div>

        <div class="agricart-ro-grid-scroll">
            <table class="agricart-ro-grid">
                <thead>
                    <tr>
                        <th class="agricart-ro-col-check"></th>
                        <th class="agricart-ro-col-sr">No.</th>
                        <th class="agricart-ro-col-thumb">Picture</th>
                        <th class="agricart-ro-col-name">Product</th>
                        <th class="agricart-ro-col-stock">On Hand</th>
                        <th class="agricart-ro-col-stock">Alert</th>
                        <th class="agricart-ro-col-qty">Required Qty</th>
                        <th class="agricart-ro-col-rate">Previous Rate</th>
                        <th class="agricart-ro-col-status">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($candidates as $index => $row)
                        @php
                            $productId = (int) $row['product_id'];
                            $isSelected = in_array($productId, $selectedProductIds, true);
                            $displayName = \App\Services\PurchasingInventory\ReOrderLineBuilder::displayName($row, $nameLang);
                        @endphp
                        <tr wire:key="ro-candidate-{{ $productId }}">
                            <td class="agricart-ro-col-check">
                                <input
                                    type="checkbox"
                                    class="agricart-ro-checkbox"
                                    @checked($isSelected)
                                    wire:click="toggleProductSelection({{ $productId }})"
                                />
                            </td>
                            <td class="agricart-ro-col-sr">{{ $index + 1 }}</td>
                            <td class="agricart-ro-col-thumb">
                                @if (filled($row['thumbnail_url'] ?? null))
                                    <img src="{{ $row['thumbnail_url'] }}" alt="" class="agricart-pp-worksheet__thumb" />
                                @else
                                    <span class="agricart-pp-worksheet__thumb agricart-pp-worksheet__thumb--empty"></span>
                                @endif
                            </td>
                            <td class="agricart-ro-col-name">
                                <span class="agricart-ro-product-name">{{ $displayName }}</span>
                                <span class="agricart-ro-product-sku">{{ $row['sku'] }}</span>
                            </td>
                            <td class="agricart-ro-col-stock">{{ $row['on_hand'] }}</td>
                            <td class="agricart-ro-col-stock">{{ $row['alert_qty'] }}</td>
                            <td class="agricart-ro-col-qty">{{ $row['required_qty'] }}</td>
                            <td class="agricart-ro-col-rate">{{ filled($row['previous_rate'] ?? '') ? $row['previous_rate'] : '—' }}</td>
                            <td class="agricart-ro-col-status">
                                <span @class([
                                    'agricart-ro-status',
                                    'agricart-ro-status--low' => ($row['stock_status'] ?? '') === 'low',
                                    'agricart-ro-status--out' => ($row['stock_status'] ?? '') === 'out',
                                ])>
                                    {{ ($row['stock_status'] ?? '') === 'out' ? 'Out' : 'Low' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="agricart-ro-empty">No products need re-order with current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Order queue --}}
    <section class="agricart-ro-section agricart-ro-screen-only">
        <div class="agricart-ro-section__head">
            <div>
                <h2 class="agricart-ro-section__title">Order Queue</h2>
                <p class="agricart-ro-section__meta">{{ $queueCount }} lines · auto-stale after {{ $staleDays }} days</p>
            </div>
            <div class="agricart-ro-queue-filters">
                @foreach ($queueFilters as $key => $label)
                    <button
                        type="button"
                        @class(['agricart-ro-queue-filter', 'agricart-ro-queue-filter--active' => $queueFilter === $key])
                        wire:click="setQueueFilter('{{ $key }}')"
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="agricart-ro-grid-scroll">
            <table class="agricart-ro-grid">
                <thead>
                    <tr>
                        <th>Order No.</th>
                        <th>Purchaser</th>
                        <th>Product</th>
                        <th>Required Qty</th>
                        <th>Sent</th>
                        <th>Status</th>
                        <th class="agricart-ro-col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($queueRows as $row)
                        @php
                            $displayName = \App\Services\PurchasingInventory\ReOrderLineBuilder::displayName($row, (string) ($row['name_lang'] ?? 'both'));
                            $status = (string) ($row['order_status'] ?? 'pending');
                        @endphp
                        <tr wire:key="ro-queue-{{ $row['order_id'] }}-{{ $row['product_id'] }}">
                            <td>
                                <a href="{{ \App\Filament\Pages\PurchasingInventory\ReOrderSendWorksheet::getUrl(['orderId' => $row['order_id']]) }}" class="agricart-ro-order-link">
                                    {{ $row['order_number'] }}
                                </a>
                            </td>
                            <td>{{ $row['purchaser_name'] }}</td>
                            <td>{{ $displayName }}</td>
                            <td>{{ $row['required_qty'] }}</td>
                            <td>{{ filled($row['sent_at'] ?? '') ? \Illuminate\Support\Carbon::parse($row['sent_at'])->format('d M Y') : '—' }}</td>
                            <td>
                                <span @class([
                                    'agricart-ro-status',
                                    'agricart-ro-status--pending' => $status === 'pending',
                                    'agricart-ro-status--stale' => $status === 'stale',
                                    'agricart-ro-status--disputed' => $status === 'disputed',
                                ])>
                                    {{ $queueStatuses[$status] ?? ucfirst($status) }}
                                </span>
                            </td>
                            <td class="agricart-ro-col-actions">
                                <div class="agricart-ro-actions">
                                    <a href="{{ \App\Filament\Pages\PurchasingInventory\ReOrderSendWorksheet::getUrl(['orderId' => $row['order_id']]) }}" class="agricart-ro-action">Open</a>
                                    @if ($status !== 'received')
                                        <button type="button" class="agricart-ro-action agricart-ro-action--ok" wire:click="markOrderReceived('{{ $row['order_id'] }}')">Received</button>
                                    @endif
                                    @if (in_array($status, ['pending', 'stale'], true))
                                        <button type="button" class="agricart-ro-action agricart-ro-action--warn" wire:click="markOrderDisputed('{{ $row['order_id'] }}')">Dispute</button>
                                    @endif
                                    @if ($status === 'disputed')
                                        <button type="button" class="agricart-ro-action" wire:click="reopenOrder('{{ $row['order_id'] }}')">Re-open</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="agricart-ro-empty">Queue is empty.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
