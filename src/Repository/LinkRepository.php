<?php

namespace App\Repository;

use App\Entity\Link;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Link>
 */
class LinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Link::class);
    }

    /**
     * @return Paginator<Link>
     */
    public function findByUserPaginated(User $user, int $page = 1, int $limit = 20): Paginator
    {
        $query = $this->createQueryBuilder('l')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user->getId(), 'uuid')
            ->orderBy('l.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        return new Paginator($query);
    }

    public function findBySlug(string $slug): ?Link
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    public function slugExists(string $slug): bool
    {
        return $this->findOneBy(['slug' => $slug]) !== null;
    }
}
