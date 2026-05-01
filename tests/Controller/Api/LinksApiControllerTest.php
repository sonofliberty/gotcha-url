<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\LinksApiController;
use App\Entity\Link;
use App\Entity\User;
use App\Repository\LinkRepository;
use App\Service\Api\ApiResponder;
use App\Service\LinkSerializer;
use App\Service\LinkUpdater;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class LinksApiControllerTest extends TestCase
{
    private LinksApiControllerWithStubUser $controller;
    private LinkRepository $linkRepo;
    private User $user;
    private ValidatorInterface $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('http://localhost/abc1234');

        $this->controller = new LinksApiControllerWithStubUser(
            new LinkSerializer($urlGenerator),
            new ApiResponder(),
            $urlGenerator,
        );
        $this->linkRepo = $this->createStub(LinkRepository::class);
        $this->user = (new User())->setAccountCode('test-token');
        $this->controller->stubUser = $this->user;

        $this->validator = $this->createStub(ValidatorInterface::class);
        $this->validator->method('validate')->willReturn(new ConstraintViolationList());
        $this->em = $this->createStub(EntityManagerInterface::class);
    }

    #[Test]
    public function updateReturns404WhenLinkNotFound(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn(null);

        $response = $this->controller->update('the-id', $this->jsonRequest('{"label":"x"}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('not_found', $body['error']);
        $this->assertSame('Link not found.', $body['message']);
    }

    #[Test]
    public function updateReturns400OnInvalidJson(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newRedirectLink());

        $response = $this->controller->update('the-id', $this->jsonRequest('not json'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('bad_request', $body['error']);
        $this->assertSame('Request body must be valid JSON.', $body['message']);
    }

    #[Test]
    public function updateReturns400OnEmptyObject(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newRedirectLink());

        $response = $this->controller->update('the-id', $this->jsonRequest('{}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('bad_request', $body['error']);
        $this->assertStringContainsString('at least one updatable field', $body['message']);
    }

    #[Test]
    public function updateReturns400OnNonBoolTrackingEnabled(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newRedirectLink());

        $response = $this->controller->update('the-id', $this->jsonRequest('{"tracking_enabled":"yes"}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(400, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('bad_request', $body['error']);
        $this->assertSame('tracking_enabled must be a boolean.', $body['message']);
    }

    #[Test]
    public function updateReturns200WithSerializedLinkOnSuccess(): void
    {
        $link = $this->newRedirectLink();
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($link);

        $response = $this->controller->update('the-id', $this->jsonRequest('{"label":"renamed"}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('renamed', $body['label']);
        $this->assertSame($link->getId()->toRfc4122(), $body['id']);
        $this->assertSame('redirect', $body['type']);
        $this->assertArrayHasKey('short_url', $body);
        $this->assertArrayHasKey('visit_count', $body);
        $this->assertArrayHasKey('created_at', $body);
    }

    #[Test]
    public function updateReturns422OnValidationFailure(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newRedirectLink());

        $violations = new ConstraintViolationList([
            new ConstraintViolation('Label too long.', null, [], null, 'label', 'x'),
        ]);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $response = $this->controller->update('the-id', $this->jsonRequest('{"label":"way too long"}'), $this->linkRepo, $this->newUpdater($validator));

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertSame('Label too long.', $body['details']['label']);
    }

    #[Test]
    public function updateReturns422WhenContentSentOnRedirectLink(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newRedirectLink());

        $response = $this->controller->update('the-id', $this->jsonRequest('{"content":"# nope"}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertArrayHasKey('markdownContent', $body['details']);
        $this->assertStringContainsString('page links', $body['details']['markdownContent']);
    }

    #[Test]
    public function updateReturns422WhenTargetUrlSentOnPageLink(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($this->newPageLink());

        $response = $this->controller->update('the-id', $this->jsonRequest('{"target_url":"https://example.com"}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(422, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('validation_failed', $body['error']);
        $this->assertArrayHasKey('targetUrl', $body['details']);
        $this->assertStringContainsString('redirect links', $body['details']['targetUrl']);
    }

    #[Test]
    public function updateClearsLabelOnNullValue(): void
    {
        $link = $this->newRedirectLink()->setLabel('old');
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($link);

        $response = $this->controller->update('the-id', $this->jsonRequest('{"label":null}'), $this->linkRepo, $this->newUpdater());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertNull($body['label']);
        $this->assertNull($link->getLabel());
    }

    #[Test]
    public function updateOwnershipFiltersThroughRepository(): void
    {
        $observedUser = null;
        $linkRepo = $this->createMock(LinkRepository::class);
        $linkRepo->expects($this->once())
            ->method('findOneByIdAndUser')
            ->with('the-id', $this->callback(function (User $u) use (&$observedUser) {
                $observedUser = $u;
                return true;
            }))
            ->willReturn(null);

        $this->controller->update('the-id', $this->jsonRequest('{"label":"x"}'), $linkRepo, $this->newUpdater());

        $this->assertSame($this->user, $observedUser);
    }

    private function newUpdater(?ValidatorInterface $validator = null): LinkUpdater
    {
        return new LinkUpdater($validator ?? $this->validator, $this->em);
    }

    private function jsonRequest(string $body): Request
    {
        return new Request(content: $body);
    }

    private function newRedirectLink(): Link
    {
        return (new Link())
            ->setUser($this->user)
            ->setType('redirect')
            ->setSlug('abc1234')
            ->setTargetUrl('https://example.com/old');
    }

    private function newPageLink(): Link
    {
        return (new Link())
            ->setUser($this->user)
            ->setType('page')
            ->setSlug('page1234')
            ->setMarkdownContent('# Old');
    }
}

class LinksApiControllerWithStubUser extends LinksApiController
{
    public ?User $stubUser = null;

    public function getUser(): ?UserInterface
    {
        return $this->stubUser;
    }
}
