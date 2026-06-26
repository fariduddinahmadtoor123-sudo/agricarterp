<?php

namespace App\Services\SalesPos;

class PosSaleLineBuilder
{
    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    public function fromProduct(array $product): array
    {
        $unitPrice = self::numeric($product['sale_rate'] ?? $product['unit_price'] ?? '');
        $qty = self::numeric($product['qty'] ?? 1);

        if ($qty <= 0) {
            $qty = 1;
        }

        $row = [
            'line_id' => (string) \Illuminate\Support\Str::uuid(),
            'product_id' => (int) $product['id'],
            'thumbnail_url' => $product['thumbnail_url'] ?? null,
            'product_number' => (string) ($product['product_number'] ?? $product['barcode'] ?? ''),
            'name_en' => (string) ($product['name_en'] ?? ''),
            'name_ur' => (string) ($product['name_ur'] ?? ''),
            'brand_name' => (string) ($product['brand_name'] ?? ''),
            'attributes_label' => (string) ($product['attributes_label'] ?? ''),
            'controls' => array_values($product['controls'] ?? []),
            'unit_label' => (string) ($product['unit_label'] ?? ''),
            'on_hand' => self::formatQuantity($product['on_hand'] ?? ''),
            'qty' => self::formatQuantity($qty),
            'unit_price' => self::formatAmount($unitPrice),
            'line_total' => self::formatAmount($unitPrice * $qty),
        ];

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function recalculate(array $row): array
    {
        $qty = self::numeric($row['qty'] ?? '');
        $unitPrice = self::numeric($row['unit_price'] ?? '');
        $row['line_total'] = self::formatAmount($qty * $unitPrice);

        return $row;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function subtotal(array $rows): float
    {
        return round(collect($rows)->sum(fn (array $row): float => self::numeric($row['line_total'] ?? '')), 2);
    }

    public static function numeric(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', (string) $value);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    public static function formatAmount(float $value): string
    {
        if ($value <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public static function formatQuantity(mixed $value): string
    {
        $value = self::numeric($value);

        if ($value <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function displayName(array $row, string $nameLang = 'both'): string
    {
        return match ($nameLang) {
            'ur' => filled($row['name_ur'] ?? null) ? (string) $row['name_ur'] : (string) ($row['name_en'] ?? ''),
            'en' => (string) ($row['name_en'] ?? ''),
            default => filled($row['name_ur'] ?? null)
                ? trim((string) $row['name_en'] . ' / ' . $row['name_ur'])
                : (string) ($row['name_en'] ?? ''),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{product_name: string, product_number: string, controls: list<string>}>
     */
    public static function controlsByProduct(array $rows, string $nameLang = 'both'): array
    {
        $items = [];

        foreach ($rows as $row) {
            $controls = $row['controls'] ?? [];

            if (! is_array($controls) || $controls === []) {
                continue;
            }

            $items[] = [
                'product_name' => self::displayName($row, $nameLang),
                'product_number' => (string) ($row['product_number'] ?? ''),
                'controls' => array_map(
                    fn (string $label): string => self::controlInstructionText($label),
                    array_values($controls),
                ),
            ];
        }

        return $items;
    }

    public static function controlInstructionText(string $label): string
    {
        $parts = preg_split('/\s*[—\-]\s+/u', trim($label), 2);

        if (is_array($parts) && count($parts) === 2 && filled($parts[1])) {
            return trim($parts[1]);
        }

        return $label;
    }
}
