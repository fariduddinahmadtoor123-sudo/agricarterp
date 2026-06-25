<?php

namespace App\Support\PurchasingInventory;

use App\Models\PurchasingInventory\ReorderOrder;
use App\Services\PurchasingInventory\DocumentNumberService;

class ReOrderQueueRepository
{
    use SyncsSheetLines;

    public function __construct(
        protected DocumentNumberService $documentNumbers,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function allOrders(): array
    {
        return ReorderOrder::query()
            ->with('lines')
            ->orderByDesc('sent_at')
            ->get()
            ->map(fn (ReorderOrder $order): array => $this->toArray($order))
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findOrder(string $orderId): ?array
    {
        $order = ReorderOrder::query()->with('lines')->find($orderId);

        return $order === null ? null : $this->toArray($order);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByOrderNumber(string $orderNumber): ?array
    {
        $needle = strtoupper(trim($orderNumber));

        $order = ReorderOrder::query()
            ->with('lines')
            ->whereRaw('UPPER(order_number) = ?', [$needle])
            ->first();

        return $order === null ? null : $this->toArray($order);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    public function createOrder(
        string $purchaserName,
        string $nameLang,
        array $lines,
        ?int $purchaserId = null,
    ): array {
        $now = now();

        $order = ReorderOrder::query()->create([
            'order_number' => $this->documentNumbers->next('reorder'),
            'purchaser_id' => $purchaserId,
            'purchaser_name' => trim($purchaserName),
            'name_lang' => in_array($nameLang, ['both', 'en', 'ur'], true) ? $nameLang : 'both',
            'status' => 'pending',
            'sent_at' => $now,
            'received_at' => null,
            'created_by' => auth()->id(),
        ]);

        $this->syncLineRows($order, $order->lines(), array_values($lines));

        return $this->toArray($order->fresh('lines'));
    }

    /**
     * @return list<int>
     */
    public function activeProductIds(): array
    {
        $ids = [];

        foreach ($this->allOrders() as $order) {
            if (! in_array($order['status'] ?? '', ['pending', 'stale', 'disputed'], true)) {
                continue;
            }

            foreach ($order['lines'] ?? [] as $line) {
                $productId = (int) ($line['product_id'] ?? 0);

                if ($productId > 0) {
                    $ids[] = $productId;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queueRows(string $filter = 'all'): array
    {
        $this->flagStaleOrders();

        $rows = [];

        foreach ($this->allOrders() as $order) {
            if ($filter !== 'all' && ($order['status'] ?? '') !== $filter) {
                continue;
            }

            if ($filter === 'all' && ($order['status'] ?? '') === 'received') {
                continue;
            }

            foreach ($order['lines'] ?? [] as $line) {
                $rows[] = array_merge($line, [
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'purchaser_id' => $order['purchaser_id'] ?? null,
                    'purchaser_name' => $order['purchaser_name'],
                    'order_status' => $order['status'],
                    'sent_at' => $order['sent_at'],
                    'received_at' => $order['received_at'],
                    'name_lang' => $order['name_lang'],
                ]);
            }
        }

        return collect($rows)
            ->sortByDesc('sent_at')
            ->values()
            ->all();
    }

    public function flagStaleOrders(): void
    {
        $days = (int) config('purchasing-inventory.reorder_stale_days', 7);
        $cutoff = now()->subDays($days);

        ReorderOrder::query()
            ->where('status', 'pending')
            ->where('sent_at', '<=', $cutoff)
            ->update([
                'status' => 'stale',
                'updated_at' => now(),
            ]);
    }

    public function markReceived(string $orderId): bool
    {
        $order = ReorderOrder::query()->find($orderId);

        if ($order === null) {
            return false;
        }

        if ($order->status === 'received') {
            return true;
        }

        $order->update([
            'status' => 'received',
            'received_at' => now(),
        ]);

        return true;
    }

    public function markDisputed(string $orderId): bool
    {
        return $this->updateOrderStatus($orderId, 'disputed');
    }

    public function markPending(string $orderId): bool
    {
        return $this->updateOrderStatus($orderId, 'pending');
    }

    protected function updateOrderStatus(string $orderId, string $status): bool
    {
        $order = ReorderOrder::query()->find($orderId);

        if ($order === null) {
            return false;
        }

        $order->update(['status' => $status]);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(ReorderOrder $order): array
    {
        return [
            'id' => (string) $order->id,
            'order_number' => (string) $order->order_number,
            'purchaser_id' => $order->purchaser_id,
            'purchaser_name' => (string) ($order->purchaser_name ?? ''),
            'name_lang' => (string) $order->name_lang,
            'status' => (string) $order->status,
            'sent_at' => $order->sent_at?->toIso8601String() ?? '',
            'received_at' => $order->received_at?->toIso8601String(),
            'lines' => $this->rowsFromLines($order->lines),
            'created_at' => $order->created_at?->toIso8601String() ?? '',
            'updated_at' => $order->updated_at?->toIso8601String() ?? '',
        ];
    }
}
