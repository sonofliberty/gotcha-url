<?php

namespace App\Tests\Service;

use App\Repository\LinkRepository;
use App\Service\SlugGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SlugGeneratorTest extends TestCase
{
    #[Test]
    public function generateReturnsSevenCharString(): void
    {
        $repo = $this->createStub(LinkRepository::class);
        $repo->method('slugExists')->willReturn(false);

        $generator = new SlugGenerator($repo);
        $slug = $generator->generate();

        $this->assertSame(7, strlen($slug));
    }

    #[Test]
    public function generateReturnsAlphanumericOnly(): void
    {
        $repo = $this->createStub(LinkRepository::class);
        $repo->method('slugExists')->willReturn(false);

        $generator = new SlugGenerator($repo);
        $slug = $generator->generate();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{7}$/', $slug);
    }

    #[Test]
    public function generateRetriesOnCollision(): void
    {
        $repo = $this->createMock(LinkRepository::class);
        $repo->expects($this->exactly(2))
            ->method('slugExists')
            ->willReturnOnConsecutiveCalls(true, false);

        $generator = new SlugGenerator($repo);
        $slug = $generator->generate();

        $this->assertSame(7, strlen($slug));
    }

    #[Test]
    public function generateChecksUniqueness(): void
    {
        $repo = $this->createMock(LinkRepository::class);
        $repo->expects($this->atLeastOnce())
            ->method('slugExists')
            ->willReturn(false);

        $generator = new SlugGenerator($repo);
        $generator->generate();
    }

    #[Test]
    public function generateProducesVaryingSlugs(): void
    {
        $repo = $this->createStub(LinkRepository::class);
        $repo->method('slugExists')->willReturn(false);

        $generator = new SlugGenerator($repo);
        $slugs = [];
        for ($i = 0; $i < 10; $i++) {
            $slugs[] = $generator->generate();
        }

        $this->assertGreaterThanOrEqual(2, count(array_unique($slugs)));
    }
}
