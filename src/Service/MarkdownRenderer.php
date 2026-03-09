<?php

namespace App\Service;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

class MarkdownRenderer
{
    private CommonMarkConverter $converter;

    public function __construct()
    {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $this->converter->getEnvironment()->addExtension(new GithubFlavoredMarkdownExtension());
    }

    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }

    public function extractTitle(string $markdown): ?string
    {
        if (preg_match('/^#\s+(.+)$/m', $markdown, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}
