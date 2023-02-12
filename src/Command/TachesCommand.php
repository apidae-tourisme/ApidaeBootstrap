<?php

namespace ApidaeTourisme\ApidaeBundle\Command;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use ApidaeTourisme\ApidaeBundle\Services\Taches;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;

/**
 * Cette commande permet de lancer les tâches en attente
 * Elle ne lancera les tâches qu'une par une, en prenant la plus ancienne au statut TO_RUN
 * En lançant cette commande par une tâche cron (toutes les minutes par exemple) on s'assure d'avoir un traitement régulier des tâches
 * Pour pouvoir traiter plusieurs tâches à la suite dans la même minute, il faut en revanche que la première tâche terminée relance ce processus
 * pour "forcer" cette commande (app:taches:start) à enchaîner sur la tâche suivante
 */
#[AsCommand(name: 'app:taches:start', description: 'Commande destinée à traiter les tâches en attente (1 par 1) : destinée à être utilisée en cron')]
class TachesCommand extends Command
{
    protected LoggerInterface $logger;
    protected EntityManagerInterface $entityManager;
    protected Filesystem $filesystem;
    protected $sleepTime = 7;
    protected TacheRepository $tacheRepository;
    protected Taches $taches;

    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        TacheRepository $tacheRepository,
        Taches $taches
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->filesystem = $filesystem;
        $this->tacheRepository = $tacheRepository;
        $this->taches = $taches;
        parent::__construct();
    }

    protected function configure()
    {
    }

    /**
     * @todo Lancer la plus ancienne tâche au statut TO_RUN
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $next = $this->tacheRepository->getTacheToRun();
        $this->taches->start($next['id']);
        $this->taches->monitorRunningTasks();
        return Command::SUCCESS;
    }
}
