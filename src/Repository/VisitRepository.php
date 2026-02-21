<?php

namespace App\Repository;

use App\Entity\Link;
use App\Entity\Visit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Visit>
 */
class VisitRepository extends ServiceEntityRepository
{
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
}
