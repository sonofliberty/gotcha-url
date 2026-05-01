<?php

namespace App\Service;

use App\Entity\Link;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LinkSerializer
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Link $link): array
    {
        return [
            'id' => $link->getId()->toRfc4122(),
            'slug' => $link->getSlug(),
            'short_url' => $this->shortUrl($link->getSlug()),
            'type' => $link->getType(),
            'target_url' => $link->getTargetUrl(),
            'label' => $link->getLabel(),
            'tracking_enabled' => $link->isTrackingEnabled(),
            'content' => $link->getMarkdownContent(),
            'visit_count' => $link->getVisitCount(),
            'created_at' => $link->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    private function shortUrl(string $slug): string
    {
        return $this->urlGenerator->generate(
            'app_track',
            ['slug' => $slug],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
    }
}
