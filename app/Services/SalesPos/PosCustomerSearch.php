<?php

namespace App\Services\SalesPos;

use App\Models\Customer;

class PosCustomerSearch
{
    /**
     * @return array<int, string>
     */
    public function search(string $term, int $limit = 15): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';
        $prefix = addcslashes($term, '%_\\') . '%';

        return Customer::query()
            ->operational()
            ->where(function ($query) use ($like, $prefix): void {
                $query
                    ->where('customer_code', 'like', $prefix)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like);
            })
            ->orderByRaw('CASE WHEN customer_code LIKE ? THEN 0 ELSE 1 END', [$prefix])
            ->orderBy('customer_name')
            ->limit($limit)
            ->get()
            ->mapWithKeys(fn (Customer $customer): array => [
                $customer->id => trim($customer->customer_code . ' — ' . $customer->customer_name),
            ])
            ->all();
    }
}
