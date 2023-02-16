<?php

namespace ApidaeTourisme\ApidaeBundle\Command;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use ApidaeTourisme\ApidaeBundle\Services\TachesServices;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;

/**
 * Cette commande permet de lancer les tâches en attente
 * Elle ne lancera les tâches qu'une par une, en prenant la plus ancienne au statut TO_RUN
 * En lançant cette commande par une tâche cron (toutes les minutes par exemple) on s'assure d'avoir un traitement régulier des tâches
 * Pour pouvoir traiter plusieurs tâches à la suite dans la même minute, il faut en revanche que la première tâche terminée relance ce processus
 * pour "forcer" cette commande (apidae:taches:start) à enchaîner sur la tâche suivante
 */
#[AsCommand(name: 'apidae:taches:start', description: 'Commande destinée à traiter les tâches en attente (1 par 1) : destinée à être utilisée en cron')]
class TachesCommand extends Command
{
    protected LoggerInterface $logger;
    public const SLEEPTIME = 7;
    public const MAX_RUNNERS = 5 ;

    public function __construct(
        LoggerInterface $logger,
        protected EntityManagerInterface $entityManager,
        protected Filesystem $filesystem,
        protected TacheRepository $tacheRepository,
        protected TachesServices $tachesServices
    ) {
        $this->logger = $logger;
        parent::__construct();
    }

    /**
     * Est-ce qu'on exécute la tâche dans ce processus ?
     *  Pour :
     *  Contre :
     *      Si la tâche plante, elle fait planter le gestionnaire
     *      On se retrouve avec 2 façons différentes d'exécuter une même tâche
     *
     * Ou est-ce qu'on lance un processus apidaebundle:tache:run ?
     *  Pour :
     *      1 process par tâche
     *  Contre :
     *
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info('Starting '.self::getDefaultName()) ;

        $this->tachesServices->monitorRunningTasks() ;
        $running = $this->tacheRepository->getTachesRunningNumber() ;

        if ($running > self::MAX_RUNNERS) {
            $this->logger->info($running . ' tâches sont déjà en cours (max: '.self::FAILURE.')... aucune autre tâche ne sera lancée') ;
        } else {
            $next = $this->tacheRepository->getTacheToRun();
            if ($next) {
                $this->logger->info('Une tâche en attente va être exécutée : tachesServices->start($tache)', [
                    'id' => $next->getId(),
                    'tache' => $next->getTache()
                ]) ;
                $this->tachesServices->start($next) ;
            //return $this->tachesServices->execute($next->getId(), $verbose, ['command' => self::getDefaultName(), 'id' => $id]) ;
            } else {
                $this->logger->info('Aucune tâche en attente n\'a été trouvée') ;
            }
        }
        sleep(self::SLEEPTIME) ;
        return Command::SUCCESS;
    }
}
