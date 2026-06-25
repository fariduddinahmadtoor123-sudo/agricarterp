<div
    class="agricart-ro-send agricart-pp-worksheet"
    x-data
    x-on:agricart-ro-print.window="window.print()"
>
    <div class="agricart-pp-worksheet__header agricart-ro-screen-only">
        <div class="agricart-pp-worksheet__header-left">
            <a href="{{ \App\Filament\Pages\PurchasingInventory\ReOrderCenter::getUrl() }}" class="agricart-pp-worksheet__back">
                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowLeft, size: \Filament\Support\Enums\IconSize::Small) }}
                <span>Re-Order Center</span>
            </a>
            <span class="agricart-pp-worksheet__header-title">Purchaser Re-Order</span>
            <span class="agricart-pp-worksheet__header-number">{{ $orderNumber }}</span>
        </div>

        <div class="agricart-pp-worksheet__header-center agricart-ro-send-meta">
            <span><strong>Purchaser:</strong> {{ $purchaserName }}</span>
            <span><strong>Sent:</strong> {{ filled($sentAt) ? \Illuminate\Support\Carbon::parse($sentAt)->format('d M Y') : '—' }}</span>
            <span><strong>Status:</strong> {{ $queueStatuses[$orderStatus] ?? ucfirst($orderStatus) }}</span>
            <span><strong>Language:</strong> {{ strtoupper($nameLang) }}</span>
        </div>

        <div class="agricart-pp-worksheet__header-right">
            <span class="agricart-pp-worksheet__item-count">{{ $lineCount }} items</span>
            <button type="button" class="agricart-pp-worksheet__btn" wire:click="printOrder">Print</button>
        </div>
    </div>

    <div class="agricart-ro-grid-scroll agricart-ro-screen-only">
        <table class="agricart-ro-grid agricart-ro-grid--send">
            <thead>
                <tr>
                    <th class="agricart-ro-col-sr">No.</th>
                    <th class="agricart-ro-col-thumb">Picture</th>
                    <th class="agricart-ro-col-name">Product Name</th>
                    <th class="agricart-ro-col-qty">Required Qty</th>
                    <th class="agricart-ro-col-rate">Previous Rate</th>
                    <th class="agricart-ro-col-market">Market Rate 1</th>
                    <th class="agricart-ro-col-market">Market Rate 2</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr wire:key="ro-send-line-{{ $line['line_id'] }}">
                        <td class="agricart-ro-col-sr">{{ $line['serial'] }}</td>
                        <td class="agricart-ro-col-thumb">
                            @if (filled($line['thumbnail_url'] ?? null))
                                <img src="{{ $line['thumbnail_url'] }}" alt="" class="agricart-pp-worksheet__thumb" />
                            @else
                                <span class="agricart-pp-worksheet__thumb agricart-pp-worksheet__thumb--empty"></span>
                            @endif
                        </td>
                        <td class="agricart-ro-col-name">
                            <span class="agricart-ro-product-name">{{ $line['display_name'] }}</span>
                            <span class="agricart-ro-product-sku">{{ $line['sku'] }}</span>
                        </td>
                        <td class="agricart-ro-col-qty">{{ $line['required_qty'] }}</td>
                        <td class="agricart-ro-col-rate">{{ filled($line['previous_rate'] ?? '') ? $line['previous_rate'] : '—' }}</td>
                        <td class="agricart-ro-col-market"><span class="agricart-ro-market-box"></span></td>
                        <td class="agricart-ro-col-market"><span class="agricart-ro-market-box"></span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="agricart-ro-print">
        <div class="agricart-ro-print__header">
            <div>
                <h1 class="agricart-ro-print__title">Re-Order List</h1>
                <p class="agricart-ro-print__meta">{{ $orderNumber }}</p>
            </div>
            <div class="agricart-ro-print__meta agricart-ro-print__meta--right">
                <div><strong>Purchaser:</strong> {{ $purchaserName }}</div>
                <div><strong>Date:</strong> {{ filled($sentAt) ? \Illuminate\Support\Carbon::parse($sentAt)->format('d M Y') : now()->format('d M Y') }}</div>
            </div>
        </div>

        <table class="agricart-ro-print-table">
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Product</th>
                    <th>Req Qty</th>
                    <th>Prev Rate</th>
                    <th>Market 1</th>
                    <th>Market 2</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line['serial'] }}</td>
                        <td>{{ $line['display_name'] }}</td>
                        <td>{{ $line['required_qty'] }}</td>
                        <td>{{ filled($line['previous_rate'] ?? '') ? $line['previous_rate'] : '' }}</td>
                        <td class="agricart-ro-print-market"></td>
                        <td class="agricart-ro-print-market"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
