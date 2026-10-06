<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Commandes du client, la plus récente d'abord, avec leurs lignes : le nombre d'articles de chaque
     * commande est affiché sans une requête par commande.
     *
     * @return list<Order>
     */
    public function findForCustomer(User $customer): array
    {
        /** @var list<Order> */
        return $this->createQueryBuilder('o')
            ->addSelect('l')
            ->leftJoin('o.orderLines', 'l')
            ->andWhere('o.user = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.created_at', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    public function findOneByStripePaymentIntentId(string $stripePaymentIntentId): ?Order
    {
        return $this->findOneBy(['stripePaymentIntentId' => $stripePaymentIntentId]);
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
