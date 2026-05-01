<?php

namespace App\Service;

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class MarkdownRenderer
{
    private CommonMarkConverter $converter;

    public function __construct(
        #[Autowire(service: 'html_sanitizer.sanitizer.page_content')]
        private HtmlSanitizerInterface $pageContent,
    ) {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $this->converter->getEnvironment()->addExtension(new GithubFlavoredMarkdownExtension());
    }

    public function render(string $input): RenderedPage
    {
        if ($this->isFullHtmlDocument($input)) {
            return $this->renderFullHtml($input);
        }

        $html = $this->pageContent->sanitize($this->converter->convert($input)->getContent());

        return new RenderedPage($html, false, $this->extractTitle($input));
    }

    public function injectBeforeBodyClose(string $html, string $snippet): string
    {
        $result = str_replace('</body>', $snippet.'</body>', $html, $count);

        return $count > 0 ? $result : $html.$snippet;
    }

    private function isFullHtmlDocument(string $input): bool
    {
        return preg_match('/^\s*(<!doctype\s+html\b|<html[\s>])/i', $input) === 1;
    }

    private function extractTitle(string $input): ?string
    {
        $patterns = [
            '/^#\s+(.+)$/m',
            '/<title[^>]*>(.+?)<\/title>/is',
            '/<h1[^>]*>(.+?)<\/h1>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                return trim(strip_tags($matches[1]));
            }
        }

        return null;
    }

    private function renderFullHtml(string $source): RenderedPage
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">'.$source, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $title = null;
        foreach ($dom->getElementsByTagName('title') as $t) {
            $title = trim($t->textContent);
            break;
        }

        $styleBlocks = '';
        foreach ($dom->getElementsByTagName('style') as $s) {
            $css = preg_replace('/(expression\s*\(|behavior\s*:|javascript\s*:|vbscript\s*:|@import|url\s*\(\s*[\'"]?\s*javascript:)/i', '', $s->textContent ?? '');
            $styleBlocks .= '<style>'.$css.'</style>'."\n";
        }

        $bodyStyle = '';
        $bodyInner = '';
        $bodyEls = $dom->getElementsByTagName('body');
        if ($bodyEls->length > 0) {
            $body = $bodyEls->item(0);
            if ($body->hasAttribute('style')) {
                $bodyStyle = $body->getAttribute('style');
            }
            foreach ($body->childNodes as $child) {
                $bodyInner .= $dom->saveHTML($child);
            }
        } else {
            $bodyInner = $source;
        }

        $sanitizedBody = $this->pageContent->sanitize($bodyInner);

        $bodyOpen = '<body'.($bodyStyle !== '' ? ' style="'.htmlspecialchars($bodyStyle, ENT_QUOTES).'"' : '').'>';
        $titleTag = $title !== null && $title !== '' ? '<title>'.htmlspecialchars($title, ENT_QUOTES).'</title>' : '';

        $html = '<!DOCTYPE html>'."\n"
            .'<html lang="en"><head>'."\n"
            .'<meta charset="UTF-8">'."\n"
            .'<meta name="viewport" content="width=device-width, initial-scale=1.0">'."\n"
            .$titleTag."\n"
            .$styleBlocks
            .'</head>'.$bodyOpen."\n"
            .$sanitizedBody."\n"
            .'</body></html>';

        return new RenderedPage($html, true, $title);
    }
}
