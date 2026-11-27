<?php

namespace App\Repository;

use App\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * Avis d'un produit, les plus récents d'abord, avec leur auteur (pseudonyme affiché) en une requête.
     *
     * @return list<Rating>
     */
    public function findForProduct(string $productId): array
    {
        /** @var list<Rating> $ratings */
        $ratings = $this->createQueryBuilder('rating')
            ->addSelect('author')
            ->leftJoin('rating.user', 'author')
            ->where('rating.productId = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('rating.created_at', 'DESC')
            ->addOrderBy('rating.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $ratings;
    }

    /**
     * Moyenne et nombre d'avis des produits d'une page de liste, en une seule requête groupée. Un produit sans avis
     * n'a pas d'entrée.
     *
     * @param list<string> $productIds
     *
     * @return array<string, array{average: float, count: int}>
     */
    public function summaries(array $productIds): array
    {
        if ([] === $productIds) {
            return [];
        }

        /** @var list<array{productId: string, average: string|float, count: int|string}> $rows */
        $rows = $this->createQueryBuilder('rating')
            ->select('rating.productId AS productId', 'AVG(rating.note) AS average', 'COUNT(rating.id) AS count')
            ->where('rating.productId IN (:productIds)')
            ->setParameter('productIds', array_values(array_unique($productIds)))
            ->groupBy('rating.productId')
            ->getQuery()
            ->getArrayResult();

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[$row['productId']] = ['average' => round((float) $row['average'], 1), 'count' => (int) $row['count']];
        }

        return $summaries;
    }

    /**
     * Moyenne et nombre de tous les avis vérifiés de la boutique, pour le bandeau de l'accueil.
     *
     * @return array{average: float|null, count: int} moyenne des notes (null sans avis) et nombre d'avis
     */
    public function globalSummary(): array
    {
        /** @var array{average: string|float|null, count: int|string} $row */
        $row = $this->createQueryBuilder('rating')
            ->select('AVG(rating.note) AS average', 'COUNT(rating.id) AS count')
            ->getQuery()
            ->getSingleResult();

        return ['average' => null === $row['average'] ? null : round((float) $row['average'], 1), 'count' => (int) $row['count']];
    }

    /**
     * @return array{average: float|null, count: int} moyenne des notes (null sans avis) et nombre d'avis
     */
    public function summary(string $productId): array
    {
        /** @var array{average: string|float|null, count: int|string} $row */
        $row = $this->createQueryBuilder('rating')
            ->select('AVG(rating.note) AS average', 'COUNT(rating.id) AS count')
            ->where('rating.productId = :productId')
            ->setParameter('productId', $productId)
            ->getQuery()
            ->getSingleResult();

        return ['average' => null === $row['average'] ? null : round((float) $row['average'], 1), 'count' => (int) $row['count']];
    }

    //    /**
    //     * @return Rating[] Returns an array of Rating objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Rating
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
