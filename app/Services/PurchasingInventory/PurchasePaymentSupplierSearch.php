<?php

namespace App\Services\PurchasingInventory;

use App\Models\Supplier;

class PurchasePaymentSupplierSearch
{
    /**
     * @return list<array{
     *     id: int,
     *     business_name: string,
     *     label: string,
     *     supplier_code: ?string,
     *     city: ?string
     * }>
     */
    public function search(string $term, int $limit = 15): array
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $like = '%' . addcslashes($term, '%_\\') . '%';

        return Supplier::query()
            ->operational()
            ->where(function ($query) use ($like, $term): void {
                $query
                    ->where('business_name', 'like', $like)
                    ->orWhere('urdu_business_name', 'like', $like)
                    ->orWhere('supplier_code', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like)
                    ->orWhere('city', 'like', $like);

                if (mb_strlen($term) >= 3) {
                    $query->orWhere('supplier_code', $term);
                }
            })
            ->orderBy('business_name')
            ->limit($limit)
            ->get(['id', 'business_name', 'urdu_business_name', 'supplier_code', 'city'])
            ->map(fn (Supplier $supplier): array => $this->presentSupplier($supplier))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     business_name: string,
     *     label: string,
     *     supplier_code: ?string,
     *     city: ?string
     * }|null
     */
    public function findById(int $supplierId): ?array
    {
        $supplier = Supplier::query()
            ->operational()
            ->whereKey($supplierId)
            ->first(['id', 'business_name', 'urdu_business_name', 'supplier_code', 'city']);

        if ($supplier === null) {
            return null;
        }

        return $this->presentSupplier($supplier);
    }

    /**
     * @return array{
     *     id: int,
     *     business_name: string,
     *     label: string,
     *     supplier_code: ?string,
     *     city: ?string
     * }
     */
    protected function presentSupplier(Supplier $supplier): array
    {
        $name = (string) $supplier->business_name;
        $code = filled($supplier->supplier_code) ? (string) $supplier->supplier_code : null;
        $city = filled($supplier->city) ? (string) $supplier->city : null;
        $label = $name;

        if ($code !== null) {
            $label .= ' (' . $code . ')';
        }

        return [
            'id' => (int) $supplier->id,
            'business_name' => $name,
            'label' => $label,
            'supplier_code' => $code,
            'city' => $city,
        ];
    }
}
