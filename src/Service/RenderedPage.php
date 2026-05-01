<?php

namespace App\Service;

final readonly class RenderedPage
{
    public function __construct(
        public string $html,
        public bool $isFullDocument,
        public ?string $title,
    ) {}
}
