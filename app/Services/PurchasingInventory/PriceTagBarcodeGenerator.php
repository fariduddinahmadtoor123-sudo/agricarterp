<?php

namespace App\Services\PurchasingInventory;

/**
 * Generates scannable Code 128 (subset B) and EAN-13 barcodes as inline SVG.
 */
class PriceTagBarcodeGenerator
{
    /** @var list<string> */
    private const CODE128_PATTERNS = [
        '11011001100', '11001101100', '11001100110', '10010011000', '10010001100',
        '10001001100', '10011001000', '10011000100', '10001100100', '11001001000',
        '11001000100', '11000100100', '10110011100', '10011011100', '10011001110',
        '10111001100', '10011101100', '10011100110', '11001110010', '11001011100',
        '11001001110', '11011100100', '11001110100', '11101101110', '11101001100',
        '11100101100', '11100100110', '11101100100', '11100110100', '11100110010',
        '11011011000', '11011000110', '11000110110', '10100011000', '10001011000',
        '10001000110', '10110001000', '10001101000', '10001100010', '11010001000',
        '11000101000', '11000100010', '10110111000', '10110001110', '10001101110',
        '10111011000', '10111000110', '10001110110', '11101110110', '11010001110',
        '11000101110', '11011101000', '11011100010', '11011101110', '11101011000',
        '11101000110', '11100010110', '11101101000', '11101100010', '11100011010',
        '11101111010', '11001000010', '11110001010', '10100110000', '10100001100',
        '10010110000', '10010000110', '10000101100', '10000100110', '10110010000',
        '10110000100', '10011010000', '10011000010', '10000110100', '10000110010',
        '11000010010', '11001010000', '11110111010', '11000010100', '10001111010',
        '10100111100', '10010111100', '10010011110', '10111100100', '10011110100',
        '10011110010', '11110100100', '11110010100', '11110010010', '11011011110',
        '11011110110', '11110110110', '10101111000', '10100011110', '10001011110',
        '10111101000', '10111100010', '11110101000', '11110100010', '10111011110',
        '10111101110', '11101011110', '11110101110', '11010000100', '11010010000',
        '11010011100', '1100011101011',
    ];

    private const CODE128_START_B = 104;

    private const CODE128_STOP = 106;

    public function svg(?string $value, int $height = 42, float $widthFactor = 1.1): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{13}$/', $value)) {
            return $this->ean13Svg($value, $height, $widthFactor);
        }

        if (! preg_match('/^[\x20-\x7E]+$/', $value)) {
            return null;
        }

        return $this->code128BSvg($value, $height, $widthFactor);
    }

    protected function code128BSvg(string $value, int $height, float $widthFactor): ?string
    {
        $codes = [self::CODE128_START_B];
        $checksum = self::CODE128_START_B;

        foreach (str_split($value) as $index => $char) {
            $code = ord($char) - 32;

            if ($code < 0 || $code > 94) {
                return null;
            }

            $codes[] = $code;
            $checksum += $code * ($index + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = self::CODE128_STOP;

        $modules = '';

        foreach ($codes as $code) {
            $modules .= self::CODE128_PATTERNS[$code] ?? '';
        }

        if ($modules === '') {
            return null;
        }

        return $this->modulesToSvg($modules, $height, $widthFactor);
    }

    protected function ean13Svg(string $value, int $height, float $widthFactor): ?string
    {
        if (! $this->isValidEan13($value)) {
            return null;
        }

        $leftPatterns = [
            '0001101', '0011001', '0010011', '0111101', '0100011',
            '0110001', '0101111', '0111011', '0110111', '0001011',
        ];
        $rightPatterns = [
            '1110010', '1100110', '1101100', '1000010', '1011100',
            '1001110', '1010000', '1000100', '1001000', '1110100',
        ];
        $parity = [
            'AAAAAA', 'AABABB', 'AABBAB', 'AABAAB', 'ABAABB',
            'ABBAAB', 'ABBBAA', 'ABABAB', 'ABABBA', 'ABBABA',
        ];

        $firstDigit = (int) $value[0];
        $parityMap = str_split($parity[$firstDigit]);
        $modules = '101';

        for ($i = 1; $i <= 6; $i++) {
            $digit = (int) $value[$i];
            $pattern = $parityMap[$i - 1] === 'A'
                ? $leftPatterns[$digit]
                : strtr($leftPatterns[$digit], ['0' => '1', '1' => '0']);
            $modules .= $pattern;
        }

        $modules .= '01010';

        for ($i = 7; $i <= 12; $i++) {
            $digit = (int) $value[$i];
            $modules .= $rightPatterns[$digit];
        }

        $modules .= '101';

        return $this->modulesToSvg($modules, $height, $widthFactor, quietZone: 11);
    }

    protected function modulesToSvg(string $modules, int $height, float $widthFactor, int $quietZone = 10): string
    {
        $moduleWidth = max(0.8, $widthFactor);
        $totalModules = strlen($modules) + ($quietZone * 2);
        $width = $totalModules * $moduleWidth;
        $rects = [];
        $x = $quietZone * $moduleWidth;
        $drawing = false;
        $startX = 0.0;

        foreach (str_split($modules) as $bit) {
            if ($bit === '1' && ! $drawing) {
                $drawing = true;
                $startX = $x;
            } elseif ($bit === '0' && $drawing) {
                $drawing = false;
                $rects[] = sprintf(
                    '<rect x="%.2F" y="0" width="%.2F" height="%d" />',
                    $startX,
                    $x - $startX,
                    $height,
                );
            }

            $x += $moduleWidth;
        }

        if ($drawing) {
            $rects[] = sprintf(
                '<rect x="%.2F" y="0" width="%.2F" height="%d" />',
                $startX,
                $x - $startX,
                $height,
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %.2F %d" preserveAspectRatio="none"><g fill="#000">%s</g></svg>',
            $width,
            $height,
            implode('', $rects),
        );
    }

    protected function isValidEan13(string $value): bool
    {
        if (! preg_match('/^\d{13}$/', $value)) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $value[$i];
            $sum += $i % 2 === 0 ? $digit : $digit * 3;
        }

        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $value[12];
    }
}
