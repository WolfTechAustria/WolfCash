<?php

namespace App\Printing;

use App\Enums\PrintTextSize;
use App\Models\Setting;
use Stringable;

/**
 * Formatierte Bonzeile. Normale Zeilen bleiben einfache Strings,
 * nur hervorgehobene Zeilen (z. B. Tisch, Artikel am Produktionsbon)
 * werden als PrintLine übergeben.
 */
final class PrintLine implements Stringable
{
    public function __construct(
        public readonly string $text,
        public readonly bool $bold = false,
        public readonly bool $center = false,
        public readonly PrintTextSize $size = PrintTextSize::Normal,
    ) {
    }

    public static function bold(string $text): self
    {
        return new self($text, bold: true);
    }

    public static function boldCentered(string $text): self
    {
        return new self($text, bold: true, center: true);
    }

    /**
     * Tischzeile in der im Admin eingestellten Schriftgröße.
     */
    public static function table(string $text): self
    {
        return new self(
            $text,
            bold: true,
            center: true,
            size: Setting::printTableTextSize(),
        );
    }

    /**
     * Produktzeile in der im Admin eingestellten Schriftgröße.
     */
    public static function product(string $text, bool $bold = true): self
    {
        return new self(
            $text,
            bold: $bold,
            size: Setting::printProductTextSize(),
        );
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
