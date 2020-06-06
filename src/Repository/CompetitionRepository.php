<?php

namespace App\Repository;

use App\Entity\Competition;
use App\Entity\Game;
use App\Entity\Player;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Persistence\ManagerRegistry;

class CompetitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Competition::class);
    }

    public function save(Competition $competition, bool $flush = true): void
    {
        $this->getEntityManager()->persist($competition);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Competition $competition, bool $flush = true)
    {
        $this->getEntityManager()->remove($competition);
        if ($flush) {
            $this->flush();
        }
    }

    public function flush()
    {
        $this->getEntityManager()->flush();
    }

    /** @return Competition[]|Collection */
    public function findAllOrdered(): array
    {
        return $this->findBy([], ['heldAt' => 'DESC']);
    }

    /** @return Competition[]|Collection */
    public function findByGame(Game $game): array
    {
        return $this->findBy(['game' => $game], ['heldAt' => 'DESC']);
    }

    public function findLastByStreamer(Player $player): ?Competition
    {
        return $this->findOneBy(['streamer' => $player], ['updatedAt' => 'DESC']);
    }

    /** @return Competition|null Random competition from the last 3 months or null if it doesn't exists */
    public function findOneRandomFinished(): ?Competition
    {
        return $this->createQueryBuilder('c')
            ->orderBy('RAND()')
            ->where('c.heldAt >= :since')
            ->andWhere('c.isFinished = 1')
            ->setParameter('since', strtotime('-3 month'))
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    public function findCompleteById(int $competitionId): ?Competition
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'r', 't', 'p')
            ->leftJoin('c.rounds', 'r')
            ->leftJoin('r.teams', 't')
            ->leftJoin('t.players', 'p')
            ->where('c.id = :id')
            ->setParameter('id', $competitionId)
            ->orderBy('r.bracketLevel', 'ASC')
            ->addOrderBy('r.bracketOrder', 'ASC')
            ->addOrderBy('t.id')
            ->getQuery()->getOneOrNullResult();
    }

    public function findWithRegistrationsAndTeamsByPlayer(Player $player): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'r', 't')
            ->join('c.registrations', 'r')
            ->leftJoin('c.teams', 't')
            ->join('t.players', 'p')
            ->where('r.player = :player')
            ->andWhere('p = :player')
            ->setParameter('player', $player)
            ->getQuery()->execute();
    }

    public function findByStreamer(Player $streamer)
    {
        return $this->findBy(['streamer' => $streamer]);
    }

    public function getTotalCount(): int
    {
        return $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()->getSingleScalarResult();
    }
}
