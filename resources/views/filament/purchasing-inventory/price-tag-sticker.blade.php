@php
    $sticker = $sticker ?? [];
    $labelWidth = (float) ($sticker['label_width_mm'] ?? config('purchasing-inventory.price_tag_label.width_mm', 38));
    $labelHeight = (float) ($sticker['label_height_mm'] ?? config('purchasing-inventory.price_tag_label.height_mm', 25));
@endphp
<div
    class="agricart-pt-sticker"
    aria-label="Price tag preview"
    style="--agricart-pt-label-width: {{ $labelWidth }}mm; --agricart-pt-label-height: {{ $labelHeight }}mm;"
>
    @if (filled($sticker['store_name'] ?? ''))
        <div class="agricart-pt-sticker__store">{{ $sticker['store_name'] }}</div>
    @endif

    @if (filled($sticker['compact_name'] ?? ''))
        <div class="agricart-pt-sticker__name">{{ $sticker['compact_name'] }}</div>
    @endif

    <div class="agricart-pt-sticker__meta">
        @if (filled($sticker['brand'] ?? ''))
            <span>{{ $sticker['brand'] }}</span>
        @endif
        @if (filled($sticker['unit'] ?? ''))
            <span>{{ $sticker['unit'] }}</span>
        @endif
        @if (filled($sticker['sku'] ?? ''))
            <span>{{ $sticker['sku'] }}</span>
        @endif
    </div>

    <div class="agricart-pt-sticker__scan">
        @if (($sticker['show_barcode'] ?? false) && filled($sticker['barcode_svg'] ?? ''))
            <div class="agricart-pt-sticker__barcode" role="img" aria-label="Barcode {{ $sticker['barcode_value'] ?? '' }}">
                {!! $sticker['barcode_svg'] !!}
                @if (filled($sticker['barcode_value'] ?? ''))
                    <span class="agricart-pt-sticker__barcode-text">{{ $sticker['barcode_value'] }}</span>
                @endif
            </div>
        @endif

        @if (($sticker['show_qr'] ?? false) && filled($sticker['qr_url'] ?? ''))
            <img src="{{ $sticker['qr_url'] }}" alt="QR {{ $sticker['barcode_value'] ?? '' }}" class="agricart-pt-sticker__qr" />
        @endif
    </div>

    <div class="agricart-pt-sticker__footer">
        <span class="agricart-pt-sticker__sale">{{ $sticker['sale_price'] ?? '' }}</span>
        <span class="agricart-pt-sticker__code">{{ $sticker['purchase_code'] ?? '' }}</span>
    </div>

    <div class="agricart-pt-sticker__rates">
        @if (filled($sticker['purchase_price'] ?? ''))
            <span>P: {{ $sticker['purchase_price'] }}</span>
        @endif
        @if (filled($sticker['landing_cost'] ?? ''))
            <span>L: {{ $sticker['landing_cost'] }}</span>
        @endif
        @if (filled($sticker['wholesale'] ?? ''))
            <span>W: {{ $sticker['wholesale'] }}</span>
        @endif
        @if (filled($sticker['super_wholesale'] ?? ''))
            <span>SW: {{ $sticker['super_wholesale'] }}</span>
        @endif
        @if (filled($sticker['distributor'] ?? ''))
            <span>D: {{ $sticker['distributor'] }}</span>
        @endif
        @if (filled($sticker['tier_codes'] ?? ''))
            <span class="agricart-pt-sticker__tiers">{{ $sticker['tier_codes'] }}</span>
        @endif
    </div>
</div>
