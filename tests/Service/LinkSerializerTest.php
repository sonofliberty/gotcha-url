<?php

namespace App\Tests\Service;

use App\Entity\Link;
use App\Entity\User;
use App\Service\LinkSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class LinkSerializerTest extends TestCase
{
    #[Test]
    public function serializesRedirectLinkToExpectedShape(): void
    {
        $link = (new Link())
            ->setUser(new User())
            ->setType('redirect')
            ->setSlug('abcdefg')
            ->setTargetUrl('https://example.com')
            ->setLabel('campaign')
            ->setTrackingEnabled(true);

        $url = $this->createStub(UrlGeneratorInterface::class);
        $url->method('generate')
            ->willReturn('http://localhost/abcdefg');

        $arr = (new LinkSerializer($url))->toArray($link);

        $this->assertSame($link->getId()->toRfc4122(), $arr['id']);
        $this->assertSame('abcdefg', $arr['slug']);
        $this->assertSame('http://localhost/abcdefg', $arr['short_url']);
        $this->assertSame('redirect', $arr['type']);
        $this->assertSame('https://example.com', $arr['target_url']);
        $this->assertSame('campaign', $arr['label']);
        $this->assertTrue($arr['tracking_enabled']);
        $this->assertNull($arr['content']);
        $this->assertSame(0, $arr['visit_count']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $arr['created_at']);
    }

    #[Test]
    public function serializesPageLinkWithMarkdownContent(): void
    {
        $link = (new Link())
            ->setUser(new User())
            ->setType('page')
            ->setSlug('mypage1')
            ->setMarkdownContent('# Hello');

        $url = $this->createStub(UrlGeneratorInterface::class);
        $url->method('generate')->willReturn('http://localhost/mypage1');

        $arr = (new LinkSerializer($url))->toArray($link);

        $this->assertSame('page', $arr['type']);
        $this->assertNull($arr['target_url']);
        $this->assertSame('# Hello', $arr['content']);
    }

    #[Test]
    public function passesAbsoluteUrlFlagToGenerator(): void
    {
        $link = (new Link())
            ->setUser(new User())
            ->setType('redirect')
            ->setSlug('abcdefg')
            ->setTargetUrl('https://example.com');

        $url = $this->createMock(UrlGeneratorInterface::class);
        $url->expects($this->once())
            ->method('generate')
            ->with('app_track', ['slug' => 'abcdefg'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://localhost/abcdefg');

        (new LinkSerializer($url))->toArray($link);
    }

    #[Test]
    public function trackingDisabledFlagSerializes(): void
    {
        $link = (new Link())
            ->setUser(new User())
            ->setType('redirect')
            ->setSlug('abcdefg')
            ->setTargetUrl('https://example.com')
            ->setTrackingEnabled(false);

        $url = $this->createStub(UrlGeneratorInterface::class);
        $url->method('generate')->willReturn('http://localhost/abcdefg');

        $arr = (new LinkSerializer($url))->toArray($link);

        $this->assertFalse($arr['tracking_enabled']);
    }
}
