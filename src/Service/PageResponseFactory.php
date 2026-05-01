<?php

namespace App\Service;

use App\Entity\Link;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PageResponseFactory
{
    public function __construct(
        private MarkdownRenderer $markdownRenderer,
        private Environment $twig,
    ) {
    }

    public function build(Link $link, ?string $trackToken, ?int $trackTs, string $partialTemplate = 'page/content.html.twig'): Response
    {
        $result = $this->markdownRenderer->render($link->getMarkdownContent() ?? '');

        if ($result->isFullDocument) {
            $html = $result->html;
            if ($trackToken !== null) {
                $tracker = $this->twig->render('page/_tracking_script.html.twig', [
                    'slug' => $link->getSlug(),
                    'trackToken' => $trackToken,
                    'trackTs' => $trackTs,
                ]);
                $html = $this->markdownRenderer->injectBeforeBodyClose($html, $tracker);
            }
            return new Response($html);
        }

        return new Response($this->twig->render($partialTemplate, [
            'slug' => $link->getSlug(),
            'title' => $result->title,
            'contentHtml' => $result->html,
            'trackToken' => $trackToken,
            'trackTs' => $trackTs,
        ]));
    }
}
