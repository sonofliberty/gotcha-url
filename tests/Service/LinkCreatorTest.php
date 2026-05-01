<?php

namespace App\Tests\Service;

use App\Entity\Link;
use App\Entity\User;
use App\Service\LinkCreator;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LinkCreatorTest extends TestCase
{
    #[Test]
    public function createsRedirectLinkWithValidUrl(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $creator = $this->newCreator(em: $em);

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: 'campaign',
            customSlug: null,
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('redirect', $result->getType());
        $this->assertSame('https://example.com', $result->getTargetUrl());
        $this->assertSame('campaign', $result->getLabel());
        $this->assertSame('autogen0', $result->getSlug());
        $this->assertTrue($result->isTrackingEnabled());
    }

    #[Test]
    public function createsPageLinkWithMarkdown(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'page',
            targetUrl: null,
            markdownContent: '# Hello',
            label: null,
            customSlug: null,
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('page', $result->getType());
        $this->assertSame('# Hello', $result->getMarkdownContent());
        $this->assertNull($result->getLabel());
    }

    #[Test]
    public function returnsViolationsWhenValidationFails(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Please enter a valid URL.', null, [], null, 'targetUrl', 'not-a-url'),
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $creator = $this->newCreator(violations: $violations, em: $em);

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'not-a-url',
            markdownContent: null,
            label: null,
            customSlug: null,
        );

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('Please enter a valid URL.', (string) $result->get(0)->getMessage());
    }

    #[Test]
    public function usesCustomSlugWhenProvided(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: 'my-promo',
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('my-promo', $result->getSlug());
    }

    #[Test]
    public function fallsBackToAutoSlugWhenCustomSlugIsEmpty(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: '',
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('autogen0', $result->getSlug());
    }

    #[Test]
    public function honorsTrackingEnabledFalse(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: null,
            trackingEnabled: false,
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertFalse($result->isTrackingEnabled());
    }

    #[Test]
    public function unknownTypeIsRejected(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $creator = $this->newCreator(em: $em);

        $result = $creator->create(
            user: new User(),
            type: 'garbage',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: null,
        );

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('type', $result->get(0)->getPropertyPath());
    }

    #[Test]
    public function reservedSlugIsRejectedWithoutPersisting(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $creator = $this->newCreator(em: $em);

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: 'dashboard',
        );

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('slug', $result->get(0)->getPropertyPath());
        $this->assertStringContainsString('reserved', (string) $result->get(0)->getMessage());
    }

    #[Test]
    public function reservedSlugCheckIsCaseInsensitive(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: null,
            customSlug: 'LOGIN',
        );

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
    }

    #[Test]
    public function isSlugReservedReportsKnownNames(): void
    {
        $creator = $this->newCreator();

        $this->assertTrue($creator->isSlugReserved('login'));
        $this->assertTrue($creator->isSlugReserved('Dashboard'));
        $this->assertFalse($creator->isSlugReserved('regular'));
    }

    #[Test]
    public function emptyLabelStaysNull(): void
    {
        $creator = $this->newCreator();

        $result = $creator->create(
            user: new User(),
            type: 'redirect',
            targetUrl: 'https://example.com',
            markdownContent: null,
            label: '',
            customSlug: null,
        );

        $this->assertInstanceOf(Link::class, $result);
        $this->assertNull($result->getLabel());
    }

    private function newCreator(
        ?ConstraintViolationListInterface $violations = null,
        ?EntityManagerInterface $em = null,
    ): LinkCreator {
        $slugGenerator = $this->createStub(SlugGenerator::class);
        $slugGenerator->method('generate')->willReturn('autogen0');

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations ?? new ConstraintViolationList());

        $em ??= $this->createStub(EntityManagerInterface::class);

        return new LinkCreator($slugGenerator, $validator, $em);
    }
}
