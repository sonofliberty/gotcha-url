<?php

namespace App\Tests\Service;

use App\Service\MarkdownRenderer;
use App\Service\RenderedPage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class MarkdownRendererTest extends TestCase
{
    private function newRenderer(): MarkdownRenderer
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowRelativeLinks();

        return new MarkdownRenderer(new HtmlSanitizer($config));
    }

    #[Test]
    public function rendersMarkdownToHtml(): void
    {
        $page = $this->newRenderer()->render("# Hello\n\nWorld");

        $this->assertInstanceOf(RenderedPage::class, $page);
        $this->assertFalse($page->isFullDocument);
        $this->assertStringContainsString('<h1>Hello</h1>', $page->html);
        $this->assertStringContainsString('World', $page->html);
    }

    #[Test]
    public function extractsTitleFromFirstH1(): void
    {
        $page = $this->newRenderer()->render("# My Title\n\nSome content");

        $this->assertSame('My Title', $page->title);
    }

    #[Test]
    public function titleIsNullWhenNoHeading(): void
    {
        $page = $this->newRenderer()->render('Just paragraph text.');

        $this->assertNull($page->title);
    }

    #[Test]
    public function detectsFullHtmlDocumentByDoctype(): void
    {
        $page = $this->newRenderer()->render('<!doctype html><html><head><title>Hi</title></head><body><p>x</p></body></html>');

        $this->assertTrue($page->isFullDocument);
        $this->assertSame('Hi', $page->title);
        $this->assertStringContainsString('<!DOCTYPE html>', $page->html);
        $this->assertStringContainsString('<p>x</p>', $page->html);
    }

    #[Test]
    public function detectsFullHtmlDocumentByHtmlTag(): void
    {
        $page = $this->newRenderer()->render('<html><body>hello</body></html>');

        $this->assertTrue($page->isFullDocument);
    }

    #[Test]
    public function stripsJavascriptUrlsFromMarkdownLinks(): void
    {
        $page = $this->newRenderer()->render('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('javascript:', $page->html);
    }

    #[Test]
    public function stripsExpressionFromInlineStyleInFullDocument(): void
    {
        $source = '<!doctype html><html><head><style>body { width: expression(alert(1)); }</style></head><body>x</body></html>';

        $page = $this->newRenderer()->render($source);

        $this->assertStringNotContainsString('expression(', $page->html);
    }

    #[Test]
    public function stripsImportFromInlineStyleInFullDocument(): void
    {
        $source = '<!doctype html><html><head><style>@import url("evil.css");</style></head><body>x</body></html>';

        $page = $this->newRenderer()->render($source);

        $this->assertStringNotContainsString('@import', $page->html);
    }

    #[Test]
    public function injectBeforeBodyClosePlacesSnippetInsideBody(): void
    {
        $renderer = $this->newRenderer();

        $result = $renderer->injectBeforeBodyClose('<html><body><p>x</p></body></html>', '<script>1</script>');

        $this->assertStringContainsString('<p>x</p><script>1</script></body>', $result);
    }

    #[Test]
    public function injectBeforeBodyCloseAppendsWhenNoBodyTag(): void
    {
        $renderer = $this->newRenderer();

        $result = $renderer->injectBeforeBodyClose('<p>x</p>', '<script>1</script>');

        $this->assertSame('<p>x</p><script>1</script>', $result);
    }

    #[Test]
    public function preservesBodyStyleAttributeInFullDocument(): void
    {
        $source = '<!doctype html><html><body style="background: red">hi</body></html>';

        $page = $this->newRenderer()->render($source);

        $this->assertStringContainsString('style="background: red"', $page->html);
    }
}
