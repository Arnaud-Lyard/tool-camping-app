<?php

namespace App\Domain\Equipment\Repository;

use App\Domain\Equipment\Entity\Equipment;
use App\Domain\Equipment\Enum\EquipmentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipment>
 */
class EquipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipment::class);
    }

    /**
     * Items ordered with "in progress" first, "completed" last, then by the
     * manual order (ordre), then newest first as a tie-breaker.
     *
     * @return Equipment[]
     */
    public function findOrdered(?EquipmentStatus $status = null): array
    {
        $qb = $this->createQueryBuilder("e")
            ->addSelect("CASE WHEN e.status = :inProgress THEN 0 ELSE 1 END AS HIDDEN statusOrder")
            ->setParameter("inProgress", EquipmentStatus::InProgress)
            ->orderBy("statusOrder", "ASC")
            ->addOrderBy("e.ordre", "ASC")
            ->addOrderBy("e.createdAt", "DESC");

        if (null !== $status) {
            $qb->andWhere("e.status = :status")->setParameter("status", $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Smallest "ordre" to assign to a new item so it floats to the top.
     */
    public function getTopOrdre(): int
    {
        $min = $this->createQueryBuilder("e")
            ->select("MIN(e.ordre)")
            ->getQuery()
            ->getSingleScalarResult();

        return null === $min ? 0 : ((int) $min) - 1;
    }
}
