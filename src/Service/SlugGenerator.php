<?php

namespace App\Service;

use App\Repository\LinkRepository;

class SlugGenerator
{
    private const CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const LENGTH = 7;

    public function __construct(
        private readonly LinkRepository $linkRepository,
    ) {
    }

    public function generate(): string
    {
        do {
            $slug = '';
            for ($i = 0; $i < self::LENGTH; $i++) {
                $slug .= self::CHARS[random_int(0, 61)];
            }
        } while ($this->linkRepository->slugExists($slug));

        return $slug;
    }
}
