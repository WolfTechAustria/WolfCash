<?php

namespace App\Printing;

class RenderedPrint
{
    /**
     * @param array<int, string> $lines
     * @param array<int, string> $headerLines zentriert unter dem Titel
     */
    public function __construct(
        public readonly string $title,
        public readonly array $lines,
        public readonly bool $cutPaper = true,
        public readonly bool $openDrawer = false,
        public readonly array $headerLines = [],
    ) {
    }
}
