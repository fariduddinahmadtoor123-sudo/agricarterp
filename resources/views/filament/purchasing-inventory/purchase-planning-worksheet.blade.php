<div class="agricart-pp-worksheet">
    {{-- Sheet header --}}
    <div class="agricart-pp-worksheet__header agricart-pp-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\PurchasingInventory\PurchasePlanning::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Sheets</span>
            </a>
            <span class="agricart-pp-worksheet__header-title">
                {{ filled($sheetTitle) ? $sheetTitle : ($isNewSheet ? 'New Sheet' : $sheetNumber) }}
            </span>
            @if (filled($sheetNumber))
                <span class="agricart-pp-worksheet__header-number">{{ $sheetNumber }}</span>
            @endif
        </div>

        <div class="agricart-pp-worksheet__header-center">
            <input
                type="date"
                class="agricart-pp-worksheet__date"
                wire:model.blur="sheetDate"
            />
            <input
                type="text"
                class="agricart-pp-worksheet__title-input"
                wire:model.blur="sheetTitle"
                placeholder="Optional title"
            />
            <input
                type="text"
                class="agricart-pp-worksheet__notes-inline"
                wire:model.blur="notes"
                placeholder="Notes..."
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
            <span class="agricart-pp-worksheet__item-count">
                {{ $itemCount }} {{ $itemCount === 1 ? 'Item' : 'Items' }}
            </span>
            <button type="button" class="agricart-pp-worksheet__btn" onclick="window.print()">Print</button>
            <button
                type="button"
                class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--discard"
                wire:click="discardSheet"
                wire:confirm="Discard this planning sheet? All lines and notes will be removed."
            >
                Discard
            </button>
            <button type="button" class="agricart-pp-worksheet__btn agricart-pp-worksheet__btn--primary" wire:click="saveSheet">
                Save Sheet
            </button>
        </div>
    </div>

    <div class="agricart-pp-print-header agricart-pp-print-only">
        <h2>Purchase Planning — {{ filled($sheetNumber) ? $sheetNumber : 'Draft' }}</h2>
        <p>
            {{ filled($sheetTitle) ? $sheetTitle : 'Planning Sheet' }}
            · {{ $sheetDate }}
        </p>
        @if (filled($notes))
            <p>{{ $notes }}</p>
        @endif
    </div>

    {{-- Load toolbar --}}
    <div class="agricart-pp-worksheet__toolbar agricart-pp-screen-only">
        <div class="agricart-pp-worksheet__toolbar-row agricart-pp-worksheet__toolbar-row--category">
            <span class="agricart-pp-worksheet__load-label">LOAD</span>

            <div class="agricart-pp-worksheet__category-load">
                <div class="agricart-pp-inline-search agricart-pp-inline-search--category">
                    <input
                        type="text"
                        class="agricart-pp-inline-search__input"
                        wire:model.live.debounce.250ms="categorySearch"
                        wire:focus="focusCategorySearch"
                        placeholder="Search category by name or path..."
                        autocomplete="off"
                    />

                    @if (filled($selectedCategoryLabel))
                        <button
                            type="button"
                            class="agricart-pp-inline-search__clear"
                            wire:click="clearCategorySelection"
                            title="Clear category"
                        >&times;</button>
                    @endif

                    @if (count($categorySearchResults) > 0)
                        <ul
                            class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--category"
                            role="listbox"
                            wire:key="pp-category-results-{{ count($categorySearchResults) }}-{{ md5($categorySearch) }}"
                        >
                            @foreach ($categorySearchResults as $category)
                                <li wire:key="pp-category-option-{{ $category['id'] }}">
                                    <button
                                        type="button"
                                        class="agricart-pp-inline-search__option"
                                        wire:click="selectCategoryForLoad({{ (int) $category['id'] }})"
                                    >
                                        <span class="agricart-pp-inline-search__option-label">{{ $category['name'] }}</span>
                                        @if (filled($category['path_hint'] ?? null))
                                            <small class="agricart-pp-inline-search__option-path">{{ $category['path_hint'] }}</small>
                                        @endif
                                        @if (! ($category['is_leaf'] ?? false))
                                            <small class="agricart-pp-inline-search__option-meta">Includes subcategories</small>
                                        @endif
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
                <button
                    type="button"
                    class="agricart-pp-worksheet__load-btn"
                    wire:click="loadFromDraftSheets"
                    title="Copy products from other unfinished (draft) sheets into this sheet"
                >From Drafts</button>
            </div>

            <div class="agricart-pp-worksheet__lang-group">
            <span class="agricart-pp-worksheet__lang-label">LANG</span>
            <button
                type="button"
                @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'en'])
                wire:click="setNameLang('en')"
            >EN</button>
            <button
                type="button"
                @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'ur'])
                wire:click="setNameLang('ur')"
            >UR</button>
            <button
                type="button"
                @class(['agricart-pp-worksheet__lang-btn', 'agricart-pp-worksheet__lang-btn--active' => $nameLang === 'both'])
                wire:click="setNameLang('both')"
            >Both</button>
            </div>
        </div>
    </div>

    {{-- Product grid --}}
    <div class="agricart-pp-worksheet__grid-wrap">
        {{-- Search bar sits outside horizontal scroll so the dropdown is not clipped --}}
        <div class="agricart-pp-worksheet__search-bar agricart-pp-screen-only">
            <span class="agricart-pp-worksheet__search-bar-plus">+</span>
            <div class="agricart-pp-inline-search agricart-pp-inline-search--product">
                <input
                    type="text"
                    class="agricart-pp-inline-search__input"
                    wire:model.live.debounce.300ms="productSearch"
                    wire:keydown.enter.prevent="addProductFromSearch"
                    placeholder="Barcode, SKU, English or Urdu name..."
                    autocomplete="off"
                    autofocus
                />

                @if (count($searchResults) > 0)
                    <ul
                        class="agricart-pp-inline-search__dropdown agricart-pp-inline-search__dropdown--product"
                        role="listbox"
                    >
                        @foreach ($searchResults as $result)
                            <li>
                                <button
                                    type="button"
                                    class="agricart-pp-inline-search__option agricart-pp-inline-search__option--product"
                                    wire:click="selectSearchResult({{ (int) $result['id'] }})"
                                >
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
        </div>

        <div class="agricart-pp-worksheet__grid-scroll">
        <table class="agricart-pp-worksheet__grid">
            <thead>
                <tr>
                    <th class="agricart-pp-worksheet__col-sr">Sr</th>
                    <th class="agricart-pp-worksheet__col-thumb">Thumb</th>
                    <th class="agricart-pp-worksheet__col-name">Product Name</th>
                    <th class="agricart-pp-worksheet__col-num">Stock</th>
                    <th class="agricart-pp-worksheet__col-num">Req Qty</th>
                    <th class="agricart-pp-worksheet__col-num">Low</th>
                    <th class="agricart-pp-worksheet__col-num">Purchase</th>
                    <th class="agricart-pp-worksheet__col-num">Landing</th>
                    <th class="agricart-pp-worksheet__col-num">Sale</th>
                    <th class="agricart-pp-worksheet__col-action"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $index => $row)
                    <tr wire:key="pp-line-{{ $row['line_id'] }}">
                        <td class="agricart-pp-worksheet__col-sr">{{ $index + 1 }}</td>
                        <td class="agricart-pp-worksheet__col-thumb">
                            @if (filled($row['thumbnail_url'] ?? null))
                                <img
                                    src="{{ $row['thumbnail_url'] }}"
                                    alt=""
                                    class="agricart-pp-worksheet__thumb agricart-product-table-image"
                                />
                            @endif
                        </td>
                        <td class="agricart-pp-worksheet__col-name">
                            @php
                                $displayName = \App\Services\PurchasingInventory\PurchasePlanningLineBuilder::displayName($row, $nameLang);
                            @endphp
                            @if (filled($displayName))
                                <div class="agricart-pp-worksheet__product-name">{{ $displayName }}</div>
                            @endif
                            <div class="agricart-pp-worksheet__product-sku">{{ $row['sku'] ?? $row['barcode'] ?? '' }}</div>
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.stock" />
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.required_qty" />
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.low_stock" />
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.purchase_price" />
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.landing_cost" />
                        </td>
                        <td class="agricart-pp-worksheet__col-num">
                            <input type="text" class="agricart-pp-worksheet__cell-input" wire:model.blur="rows.{{ $index }}.sale_price" />
                        </td>
                        <td class="agricart-pp-worksheet__col-action">
                            <button
                                type="button"
                                class="agricart-pp-worksheet__remove"
                                wire:click="removeLine('{{ $row['line_id'] }}')"
                                title="Remove line"
                            >&times;</button>
                        </td>
                    </tr>
                @endforeach

                @php
                    $placeholderCount = max(0, $minVisualRows - count($rows));
                @endphp

                @for ($p = 0; $p < $placeholderCount; $p++)
                    <tr class="agricart-pp-worksheet__placeholder-row" wire:key="pp-placeholder-{{ $p }}">
                        <td class="agricart-pp-worksheet__col-sr">{{ count($rows) + $p + 1 }}</td>
                        <td class="agricart-pp-worksheet__col-thumb"></td>
                        <td class="agricart-pp-worksheet__col-name">
                            @if (count($rows) === 0 && $p === 0)
                                <span class="agricart-pp-worksheet__placeholder-hint">Search above to add products. Press Enter to add each line.</span>
                            @endif
                        </td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-num"></td>
                        <td class="agricart-pp-worksheet__col-action"></td>
                    </tr>
                @endfor
            </tbody>
        </table>
        </div>
    </div>
</div>
