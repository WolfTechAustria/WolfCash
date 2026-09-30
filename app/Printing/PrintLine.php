<?php

namespace App\Printing;

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

    public function __toString(): string
    {
        return $this->text;
    }
}
