<?php

namespace App\Enums;

/**
 * Schriftgröße für hervorgehobene Bonzeilen (Tisch, Produkte).
 * Breite/Höhe sind die ESC/POS-Vergrößerungsfaktoren — bei
 * doppelter Breite passen nur noch 16 statt 32 Zeichen pro Zeile.
 */
enum PrintTextSize: string
{
    case Normal = 'normal';
    case Tall = 'tall';
    case Double = 'double';
    case Triple = 'triple';

    public function width(): int
    {
        return match ($this) {
            self::Normal, self::Tall => 1,
            self::Double => 2,
            self::Triple => 3,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Normal => 1,
            self::Tall, self::Double => 2,
            self::Triple => 3,
        };
    }

    public function charsPerLine(): int
    {
        return intdiv(32, $this->width());
    }

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Tall => 'Doppelt hoch',
            self::Double => 'Doppelt groß',
            self::Triple => 'Dreifach groß',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
