<?php

namespace App\Services\PurchasingInventory;

class PurchasePaymentSheetBuilder
{
    public function defaultVendorRows(): int
    {
        return (int) config('purchasing-inventory.payment_sheet_default_vendor_rows', 10);
    }

    public function defaultSourceRows(): int
    {
        return (int) config('purchasing-inventory.payment_sheet_default_source_rows', 3);
    }

    public function maxVendorRows(): int
    {
        return (int) config('purchasing-inventory.payment_sheet_max_vendor_rows', 100);
    }

    public function maxSourceRows(): int
    {
        return (int) config('purchasing-inventory.payment_sheet_max_source_rows', 50);
    }

    public function printBlankVendorRows(): int
    {
        return (int) config('purchasing-inventory.payment_sheet_print_blank_vendor_rows', 3);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blankVendorLines(?int $count = null): array
    {
        $count ??= $this->defaultVendorRows();
        $lines = [];

        for ($i = 1; $i <= $count; $i++) {
            $lines[] = $this->emptyVendorLine($i);
        }

        return $lines;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function blankPaymentSources(?int $count = null): array
    {
        $count ??= $this->defaultSourceRows();
        $lines = [];

        for ($i = 0; $i < $count; $i++) {
            $lines[] = $this->emptyPaymentSourceLine();
        }

        return $lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyVendorLine(int $serial): array
    {
        return [
            'serial' => $serial,
            'supplier_id' => null,
            'vendor_name' => '',
            'payment' => '',
            'invoice_ok' => false,
            'invoice_dispute' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPaymentSourceLine(): array
    {
        return [
            'source' => '',
            'amount' => '',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function normalizeVendorLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $supplierId = $line['supplier_id'] ?? null;
            $supplierId = is_numeric($supplierId) ? (int) $supplierId : null;

            $normalized[] = [
                'serial' => 0,
                'supplier_id' => $supplierId > 0 ? $supplierId : null,
                'vendor_name' => trim((string) ($line['vendor_name'] ?? '')),
                'payment' => $this->normalizeAmount((string) ($line['payment'] ?? '')),
                'invoice_ok' => (bool) ($line['invoice_ok'] ?? false),
                'invoice_dispute' => (bool) ($line['invoice_dispute'] ?? false),
            ];
        }

        $normalized = $this->trimTrailingEmptyVendorLines($normalized);
        $normalized = $this->padVendorLinesToMinimum($normalized);

        if ($normalized !== [] && $this->vendorLineIsFilled($normalized[array_key_last($normalized)])) {
            $normalized[] = $this->emptyVendorLine(0);
        }

        foreach ($normalized as $index => &$line) {
            $line['serial'] = $index + 1;
        }

        return array_values($normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    public function normalizePaymentSources(array $sources): array
    {
        $normalized = [];

        foreach ($sources as $line) {
            $normalized[] = [
                'source' => trim((string) ($line['source'] ?? '')),
                'amount' => $this->normalizeAmount((string) ($line['amount'] ?? '')),
            ];
        }

        $normalized = $this->trimTrailingEmptySourceLines($normalized);
        $normalized = $this->padSourceLinesToMinimum($normalized);

        if ($normalized !== [] && $this->sourceLineIsFilled($normalized[array_key_last($normalized)])) {
            $normalized[] = $this->emptyPaymentSourceLine();
        }

        return array_values($normalized);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function filledVendorLines(array $lines): array
    {
        return collect($lines)
            ->filter(fn (array $line): bool => $this->vendorLineIsFilled($line))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @return list<array<string, mixed>>
     */
    public function filledPaymentSources(array $sources): array
    {
        return collect($sources)
            ->filter(fn (array $line): bool => $this->sourceLineIsFilled($line))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function vendorPaymentsTotal(array $lines): float
    {
        return collect($lines)
            ->sum(fn (array $line): float => $this->toFloat((string) ($line['payment'] ?? '')));
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function filledVendorCount(array $lines): int
    {
        return count($this->filledVendorLines($lines));
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function paymentSourcesTotal(array $sources): float
    {
        return collect($sources)
            ->sum(fn (array $line): float => $this->toFloat((string) ($line['amount'] ?? '')));
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     */
    public function filledPaymentSourceCount(array $sources): int
    {
        return count($this->filledPaymentSources($sources));
    }

    public function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ',');
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public function vendorLineIsFilled(array $line): bool
    {
        return trim((string) ($line['vendor_name'] ?? '')) !== ''
            || $this->toFloat((string) ($line['payment'] ?? '')) > 0;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function sourceLineIsFilled(array $line): bool
    {
        return trim((string) ($line['source'] ?? '')) !== ''
            || $this->toFloat((string) ($line['amount'] ?? '')) > 0;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function trimTrailingEmptyVendorLines(array $lines): array
    {
        $minimum = $this->defaultVendorRows();

        while (count($lines) > $minimum) {
            $last = $lines[array_key_last($lines)] ?? null;

            if ($last === null || $this->vendorLineIsFilled($last)) {
                break;
            }

            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function padVendorLinesToMinimum(array $lines): array
    {
        while (count($lines) < $this->defaultVendorRows()) {
            $lines[] = $this->emptyVendorLine(count($lines) + 1);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function trimTrailingEmptySourceLines(array $lines): array
    {
        $minimum = $this->defaultSourceRows();

        while (count($lines) > $minimum) {
            $last = $lines[array_key_last($lines)] ?? null;

            if ($last === null || $this->sourceLineIsFilled($last)) {
                break;
            }

            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    protected function padSourceLinesToMinimum(array $lines): array
    {
        while (count($lines) < $this->defaultSourceRows()) {
            $lines[] = $this->emptyPaymentSourceLine();
        }

        return $lines;
    }

    protected function normalizeAmount(string $value): string
    {
        $value = str_replace(',', '', trim($value));

        if ($value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            return '';
        }

        $float = (float) $value;

        if ($float <= 0) {
            return '';
        }

        return rtrim(rtrim(number_format($float, 2, '.', ''), '0'), '.');
    }

    protected function toFloat(string $value): float
    {
        $value = str_replace(',', '', trim($value));

        if ($value === '' || ! is_numeric($value)) {
            return 0.0;
        }

        return (float) $value;
    }
}
