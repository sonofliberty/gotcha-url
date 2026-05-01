<?php

namespace App\Tests\Service;

use App\Entity\Link;
use App\Entity\User;
use App\Service\LinkUpdateInput;
use App\Service\LinkUpdater;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LinkUpdaterTest extends TestCase
{
    #[Test]
    public function updatesLabelWhenSet(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $updater = $this->newUpdater(em: $em);
        $link = $this->newRedirectLink();

        $result = $updater->update($link, LinkUpdateInput::label('campaign'));

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('campaign', $link->getLabel());
    }

    #[Test]
    public function clearsLabelWhenSetToEmptyString(): void
    {
        $updater = $this->newUpdater();
        $link = $this->newRedirectLink()->setLabel('old');

        $updater->update($link, LinkUpdateInput::label(''));

        $this->assertNull($link->getLabel());
    }

    #[Test]
    public function clearsLabelWhenSetToNull(): void
    {
        $updater = $this->newUpdater();
        $link = $this->newRedirectLink()->setLabel('old');

        $updater->update($link, LinkUpdateInput::label(null));

        $this->assertNull($link->getLabel());
    }

    #[Test]
    public function togglesTrackingWhenSet(): void
    {
        $updater = $this->newUpdater();
        $link = $this->newRedirectLink();
        $this->assertTrue($link->isTrackingEnabled());

        $updater->update($link, LinkUpdateInput::trackingEnabled(false));

        $this->assertFalse($link->isTrackingEnabled());
    }

    #[Test]
    public function updatesContentOnPageLink(): void
    {
        $updater = $this->newUpdater();
        $link = $this->newPageLink();

        $result = $updater->update($link, LinkUpdateInput::markdownContent('# New'));

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('# New', $link->getMarkdownContent());
    }

    #[Test]
    public function rejectsContentOnRedirectLink(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $updater = $this->newUpdater(em: $em);
        $link = $this->newRedirectLink();

        $result = $updater->update($link, LinkUpdateInput::markdownContent('# nope'));

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('markdownContent', $result->get(0)->getPropertyPath());
        $this->assertStringContainsString('page links', (string) $result->get(0)->getMessage());
    }

    #[Test]
    public function rejectsTargetUrlOnPageLink(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $updater = $this->newUpdater(em: $em);
        $link = $this->newPageLink();

        $result = $updater->update($link, LinkUpdateInput::targetUrl('https://example.com'));

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('targetUrl', $result->get(0)->getPropertyPath());
        $this->assertStringContainsString('redirect links', (string) $result->get(0)->getMessage());
    }

    #[Test]
    public function updatesTargetUrlOnRedirectLink(): void
    {
        $updater = $this->newUpdater();
        $link = $this->newRedirectLink();

        $result = $updater->update($link, LinkUpdateInput::targetUrl('https://example.com/new'));

        $this->assertInstanceOf(Link::class, $result);
        $this->assertSame('https://example.com/new', $link->getTargetUrl());
    }

    #[Test]
    public function returnsValidatorViolationsWhenEntityInvalid(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation('Label too long.', null, [], null, 'label', 'x'),
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');
        $updater = $this->newUpdater(violations: $violations, em: $em);
        $link = $this->newRedirectLink();

        $result = $updater->update($link, LinkUpdateInput::label('something'));

        $this->assertInstanceOf(ConstraintViolationListInterface::class, $result);
        $this->assertSame('Label too long.', (string) $result->get(0)->getMessage());
    }

    #[Test]
    public function emptyInputStillFlushesAndReturnsLink(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $updater = $this->newUpdater(em: $em);
        $link = $this->newRedirectLink();
        $originalLabel = $link->getLabel();
        $originalUrl = $link->getTargetUrl();

        $result = $updater->update($link, LinkUpdateInput::none());

        $this->assertSame($link, $result);
        $this->assertSame($originalLabel, $link->getLabel());
        $this->assertSame($originalUrl, $link->getTargetUrl());
    }

    #[Test]
    public function appliesMultipleFieldsInOneCall(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $updater = $this->newUpdater(em: $em);
        $link = $this->newRedirectLink();

        $updater->update($link, LinkUpdateInput::fromArray([
            'label' => 'renamed',
            'tracking_enabled' => false,
            'target_url' => 'https://example.org/new',
        ]));

        $this->assertSame('renamed', $link->getLabel());
        $this->assertFalse($link->isTrackingEnabled());
        $this->assertSame('https://example.org/new', $link->getTargetUrl());
    }

    private function newUpdater(
        ?ConstraintViolationListInterface $violations = null,
        ?EntityManagerInterface $em = null,
    ): LinkUpdater {
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations ?? new ConstraintViolationList());

        $em ??= $this->createStub(EntityManagerInterface::class);

        return new LinkUpdater($validator, $em);
    }

    private function newRedirectLink(): Link
    {
        return (new Link())
            ->setUser(new User())
            ->setType('redirect')
            ->setSlug('abc1234')
            ->setTargetUrl('https://example.com/old');
    }

    private function newPageLink(): Link
    {
        return (new Link())
            ->setUser(new User())
            ->setType('page')
            ->setSlug('page1234')
            ->setMarkdownContent('# Old');
    }
}
