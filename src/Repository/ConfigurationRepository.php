<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Configuration;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Configuration>
 */
class ConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Configuration::class);
    }

    /**
     * Configurations du client, la dernière créée d'abord, avec leurs composants : une seule requête pour toute la
     * liste au lieu d'une par configuration.
     *
     * @return list<Configuration>
     */
    public function findByUserWithItems(User $user): array
    {
        /** @var list<Configuration> */
        return $this->createQueryBuilder('configuration')
            ->addSelect('item')
            ->leftJoin('configuration.items', 'item')
            ->andWhere('configuration.user = :user')
            ->setParameter('user', $user)
            ->orderBy('configuration.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
