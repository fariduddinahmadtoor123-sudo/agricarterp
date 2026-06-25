<?php

namespace App\Filament\Pages\PurchasingInventory;

use App\Filament\Pages\Concerns\InteractsWithModuleSubmenuPage;
use App\Services\PurchasingInventory\ReOrderLineBuilder;
use App\Support\PurchasingInventory\ReOrderQueueRepository;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ReOrderSendWorksheet extends Page
{
    use InteractsWithModuleSubmenuPage;

    protected static ?string $slug = 'purchasing-inventory/re-order-center/orders/{orderId}';

    protected static bool $shouldRegisterNavigation = false;

    public string $orderId = '';

    /** @var array<string, mixed> */
    public array $order = [];

    public static function moduleKey(): string
    {
        return 'purchasing-inventory';
    }

    public static function submenuKey(): string
    {
        return 're-order-center';
    }

    public function mount(string $orderId): void
    {
        $order = app(ReOrderQueueRepository::class)->findOrder($orderId);

        abort_if($order === null, 404);

        $this->orderId = $orderId;
        $this->order = $order;
    }

    public function getTitle(): string | Htmlable
    {
        return (string) ($this->order['order_number'] ?? 'Re-Order');
    }

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.purchasing-inventory.re-order-send-worksheet')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $nameLang = (string) ($this->order['name_lang'] ?? 'both');
        $lines = $this->order['lines'] ?? [];

        $displayLines = collect($lines)
            ->values()
            ->map(function (array $line, int $index) use ($nameLang): array {
                return array_merge($line, [
                    'serial' => $index + 1,
                    'display_name' => ReOrderLineBuilder::displayName($line, $nameLang),
                ]);
            })
            ->all();

        return [
            'order' => $this->order,
            'orderNumber' => (string) ($this->order['order_number'] ?? ''),
            'purchaserName' => (string) ($this->order['purchaser_name'] ?? ''),
            'nameLang' => $nameLang,
            'lines' => $displayLines,
            'lineCount' => count($displayLines),
            'orderStatus' => (string) ($this->order['status'] ?? 'pending'),
            'queueStatuses' => config('purchasing-inventory.reorder_queue_statuses', []),
            'sentAt' => (string) ($this->order['sent_at'] ?? ''),
        ];
    }

    public function printOrder(): void
    {
        $this->dispatch('agricart-ro-print');
    }
}
