<?php

namespace ApidaeTourisme\ApidaeBundle\Repository;

use ApidaeTourisme\ApidaeBundle\Config\TachesStatus;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\Exception\InvalidParameterException;

/**
 * @method Tache|null find($id, $lockMode = null, $lockVersion = null)
 * @method Tache|null findOneBy(array $criteria, array $orderBy = null)
 * @method Tache[]    findAll()
 * @method Tache[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TacheRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tache::class);
    }

    public function getTachesByUtilisateuId(int $id): array|null
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.userEmail = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    public function getTacheById(int $id): Tache|null
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTacheToRun(): Tache|null
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', TachesStatus::TO_RUN)
            ->orderBy('t.creationdate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTachesToRun(): array|null
    {
        return $this->getTachesByStatus(TachesStatus::TO_RUN);
    }

    public function getTachesByStatus(TachesStatus $status): array|null
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.creationdate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTachesNumberByStatus(string $status): int|null
    {
        return $this->createQueryBuilder('t')
            ->select('count(t.id)')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTacheBySignature(string $signature): Tache|null
    {
        return $this->findOneBy(['signature' => $signature], ['creationdate' =>'ASC']) ;
    }

    public function getTachesBySignature(string $signature): array
    {
        return $this->findBy(['signature' => $signature], ['creationdate' =>'ASC']) ;
    }

    public function findLastBySignature(mixed $valeurs): array|null
    {
        if (!in_array('signature', ['id', 'signature'])) {
            return [] ;
        }

        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.creationdate', 'DESC')
            ->setParameter('signature', $valeurs) ;

        if (is_array($valeurs)) {
            $qb->andWhere('t.signature in (:signature)') ;
        } else {
            $qb->andWhere('t.signature = :signature') ;
        }

        $query = $qb->getQuery() ;
        $results = $query->getResult();

        $toFind = is_array($valeurs) ? array_flip(array_values($valeurs)) : [$valeurs => $valeurs] ;

        $return = [] ;
        foreach ($results as $r) {
            if (sizeof($toFind) == 0) {
                break ;
            }
            if (isset($toFind[$r->getSignature()])) {
                unset($toFind[$r->getSignature()]) ;
                $return[] = $r ;
            }
        }

        return $return ;
    }

    public function findLast(): Tache|null
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.creationdate', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
