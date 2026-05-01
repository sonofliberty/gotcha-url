<?php

namespace App\Repository;

use App\Entity\Link;
use App\Entity\User;
use App\Entity\Visit;
use App\Repository\Concerns\HandlesMalformedUuid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Visit>
 */
class VisitRepository extends ServiceEntityRepository
{
    use HandlesMalformedUuid;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visit::class);
    }

    /**
     * @return Paginator<Visit>
     */
    public function findByLinkPaginated(Link $link, int $page = 1, int $limit = 50): Paginator
    {
        $query = $this->createQueryBuilder('v')
            ->andWhere('v.link = :link')
            ->setParameter('link', $link->getId(), 'uuid')
            ->orderBy('v.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($query);
    }

    public function findOneByIdAndUser(string $id, User $user): ?Visit
    {
        return $this->nullOnInvalidUuid(fn () => $this->createQueryBuilder('v')
            ->innerJoin('v.link', 'l')
            ->andWhere('v.id = :id')
            ->andWhere('l.user = :user')
            ->setParameter('id', $id, 'uuid')
            ->setParameter('user', $user->getId(), 'uuid')
            ->getQuery()
            ->getOneOrNullResult());
    }
}
