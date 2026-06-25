@php
    use App\Filament\Pages\PurchasingInventory\PriceTags;
    use App\Filament\Pages\SalesPos\PosSales;
@endphp

<div class="agricart-topbar-quick-actions" role="group" aria-label="Quick actions">
    <a
        href="{{ PriceTags::getUrl() }}"
        class="agricart-topbar-quick-actions__btn"
        title="Barcode / price tag printing"
    >
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedPrinter, size: \Filament\Support\Enums\IconSize::Small) }}
        <span class="agricart-topbar-quick-actions__label">Print</span>
    </a>

    <a
        href="{{ route('filament.admin.quick.purchase') }}"
        class="agricart-topbar-quick-actions__btn"
        title="New purchase invoice"
    >
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedPlus, size: \Filament\Support\Enums\IconSize::Small) }}
        <span class="agricart-topbar-quick-actions__label">Purchase</span>
    </a>

    <a
        href="{{ PosSales::getUrl() }}"
        class="agricart-topbar-quick-actions__btn"
        title="New sale (POS)"
    >
        {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedShoppingCart, size: \Filament\Support\Enums\IconSize::Small) }}
        <span class="agricart-topbar-quick-actions__label">Sale</span>
    </a>
</div>
