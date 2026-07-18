<?php

namespace App\Printing;

class RenderedPrint
{
    public function __construct(
        public readonly string $title,
        public readonly array $lines,
        public readonly bool $cutPaper = true,
        public readonly bool $openDrawer = false,
    ) {
    }
}
