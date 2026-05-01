<?php

namespace App\Tests\Controller\Api;

use App\Controller\Api\VisitsApiController;
use App\Entity\Link;
use App\Entity\User;
use App\Entity\Visit;
use App\Repository\LinkRepository;
use App\Repository\VisitRepository;
use App\Service\Api\ApiResponder;
use App\Service\VisitSerializer;
use Doctrine\ORM\Tools\Pagination\Paginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class VisitsApiControllerTest extends TestCase
{
    private VisitsApiControllerWithStubUser $controller;
    private LinkRepository $linkRepo;
    private VisitRepository $visitRepo;
    private User $user;

    protected function setUp(): void
    {
        $this->controller = new VisitsApiControllerWithStubUser(
            new VisitSerializer(),
            new ApiResponder(),
        );
        $this->linkRepo = $this->createStub(LinkRepository::class);
        $this->visitRepo = $this->createStub(VisitRepository::class);
        $this->user = (new User())->setAccountCode('test-token');
        $this->controller->stubUser = $this->user;
    }

    #[Test]
    public function listForLinkReturnsNotFoundWhenLinkUnowned(): void
    {
        $this->linkRepo->method('findOneByIdAndUser')->willReturn(null);

        $response = $this->controller->listForLink(
            'some-id',
            new Request(),
            $this->linkRepo,
            $this->visitRepo,
        );

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('not_found', $body['error']);
        $this->assertSame('Link not found.', $body['message']);
    }

    #[Test]
    public function listForLinkReturnsSerializedVisitsWithPagination(): void
    {
        $link = (new Link())->setUser($this->user)->setSlug('abcdefg');
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($link);

        $visits = [
            (new Visit())->setLink($link)->setIpAddress('1.1.1.1')->setPlatform('MacIntel'),
            (new Visit())->setLink($link)->setIpAddress('2.2.2.2')->setPlatform('Win32'),
        ];
        $this->visitRepo->method('findByLinkPaginated')->willReturn($this->fakePaginator($visits, total: 2));

        $response = $this->controller->listForLink(
            'link-id',
            new Request(),
            $this->linkRepo,
            $this->visitRepo,
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertCount(2, $body['data']);
        $this->assertSame('1.1.1.1', $body['data'][0]['ip_address']);
        $this->assertSame('MacIntel', $body['data'][0]['platform']);
        $this->assertSame($link->getId()->toRfc4122(), $body['data'][0]['link_id']);
        $this->assertSame(['page' => 1, 'per_page' => 50, 'total' => 2, 'total_pages' => 1], $body['pagination']);
    }

    #[Test]
    public function listForLinkClampsPageZeroToOne(): void
    {
        $link = (new Link())->setUser($this->user)->setSlug('abcdefg');
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($link);

        $observedPage = null;
        $repo = $this->createMock(VisitRepository::class);
        $repo->expects($this->once())
            ->method('findByLinkPaginated')
            ->with($link, $this->callback(function (int $page) use (&$observedPage) {
                $observedPage = $page;
                return true;
            }), 50)
            ->willReturn($this->fakePaginator([], total: 0));

        $this->controller->listForLink('link-id', new Request(query: ['page' => '0']), $this->linkRepo, $repo);

        $this->assertSame(1, $observedPage);
    }

    #[Test]
    public function listForLinkOutOfRangePageReturnsEmptyDataWithRealTotal(): void
    {
        $link = (new Link())->setUser($this->user)->setSlug('abcdefg');
        $this->linkRepo->method('findOneByIdAndUser')->willReturn($link);

        $this->visitRepo->method('findByLinkPaginated')->willReturn($this->fakePaginator([], total: 75));

        $response = $this->controller->listForLink(
            'link-id',
            new Request(query: ['page' => '999']),
            $this->linkRepo,
            $this->visitRepo,
        );

        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame([], $body['data']);
        $this->assertSame(['page' => 999, 'per_page' => 50, 'total' => 75, 'total_pages' => 2], $body['pagination']);
    }

    #[Test]
    public function showReturnsSerializedVisit(): void
    {
        $link = (new Link())->setUser($this->user)->setSlug('abcdefg');
        $visit = (new Visit())->setLink($link)->setIpAddress('203.0.113.1')->setCountryCode('US');
        $this->visitRepo->method('findOneByIdAndUser')->willReturn($visit);

        $response = $this->controller->show('visit-id', $this->visitRepo);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame($visit->getId()->toRfc4122(), $body['id']);
        $this->assertSame($link->getId()->toRfc4122(), $body['link_id']);
        $this->assertSame('203.0.113.1', $body['ip_address']);
        $this->assertSame('US', $body['country_code']);
    }

    #[Test]
    public function showReturnsNotFoundWhenVisitUnowned(): void
    {
        $this->visitRepo->method('findOneByIdAndUser')->willReturn(null);

        $response = $this->controller->show('visit-id', $this->visitRepo);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertSame('not_found', $body['error']);
        $this->assertSame('Visit not found.', $body['message']);
    }

    #[Test]
    public function ownershipFiltersThroughRepository(): void
    {
        // The controller delegates ownership entirely to repository methods.
        // This test pins the contract so a refactor of either method that
        // weakens ownership would fail loudly here.
        $observedUser = null;
        $linkRepo = $this->createMock(LinkRepository::class);
        $linkRepo->expects($this->once())
            ->method('findOneByIdAndUser')
            ->with('the-id', $this->callback(function (User $u) use (&$observedUser) {
                $observedUser = $u;
                return true;
            }))
            ->willReturn(null);

        $this->controller->listForLink('the-id', new Request(), $linkRepo, $this->visitRepo);

        $this->assertSame($this->user, $observedUser);

        $observedUser = null;
        $visitRepo = $this->createMock(VisitRepository::class);
        $visitRepo->expects($this->once())
            ->method('findOneByIdAndUser')
            ->with('the-id', $this->callback(function (User $u) use (&$observedUser) {
                $observedUser = $u;
                return true;
            }))
            ->willReturn(null);

        $this->controller->show('the-id', $visitRepo);

        $this->assertSame($this->user, $observedUser);
    }

    /**
     * @param Visit[] $items
     */
    private function fakePaginator(array $items, int $total): Paginator
    {
        return new class ($items, $total) extends Paginator {
            /**
             * @param Visit[] $items
             */
            public function __construct(private readonly array $items, private readonly int $total)
            {
                // intentionally bypass parent constructor; Paginator's iterator
                // and count are the only methods exercised by the controller.
            }

            public function count(): int
            {
                return $this->total;
            }

            public function getIterator(): \Iterator
            {
                return new \ArrayIterator($this->items);
            }
        };
    }
}

class VisitsApiControllerWithStubUser extends VisitsApiController
{
    public ?User $stubUser = null;

    public function getUser(): ?UserInterface
    {
        return $this->stubUser;
    }
}
