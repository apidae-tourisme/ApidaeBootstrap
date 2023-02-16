<?php

namespace ApidaeTourisme\ApidaeBundle\Repository;

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

    public function getTachesByUtilisateuId(int $id): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.userEmail = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getResult();
    }

    public function getTacheById(int $id): Tache
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTacheToRun(): Tache
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', Tache::STATUS['TO_RUN'])
            ->orderBy('t.creationdate', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getTachesToRun(): array
    {
        return $this->getTachesByStatus(Tache::STATUS['TO_RUN']);
    }

    public function getTachesByStatus(string $status): array
    {
        if (!in_array($status, Tache::STATUS)) {
            throw new InvalidParameterException('Status ' . $status . ' invalide pour Tache');
        }
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.creationdate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getTachesRunningNumber()
    {
        return $this->getTachesNumberByStatus('RUNNING') ;
    }

    public function getTachesNumberByStatus(string $status)
    {
        return $this->createQueryBuilder('i')
            ->select('count(t.id)')
            ->andWhere('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
