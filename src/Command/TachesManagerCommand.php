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
 * Elle effectue LOOP(10) boucles avec un interval minimal (sleep) de SLEEPTIME(6) secondes à chaque lancement, donc peut durer plus d'une minute.
 * Elle peut lancer des tâches TO_RUN même si d'autres sont déjà en cours : elle n'en lancera au maximum que MAX_TACHES en même temps.
 * Il peut donc y avoir un recouvrement entre les tâches cron si on les déclenche à 1 min d'intervalle :
 *  ce n'est pas un problème puisque chaque commande vérifiera qu'on n'a pas plus de MAX_TACHES lancées.
 */
#[AsCommand(name: 'apidae:tachesManager:start', description: 'Commande destinée à traiter les tâches en attente (1 par 1) : destinée à être utilisée en cron')]
class TachesManagerCommand extends Command
{
    protected LoggerInterface $logger;

    public function __construct(
        protected LoggerInterface $tachesLogger,
        protected EntityManagerInterface $entityManager,
        protected Filesystem $filesystem,
        protected TacheRepository $tacheRepository,
        protected TachesServices $tachesServices,
        protected $APIDAEBUNDLE_TACHES_SLEEP,
        protected $APIDAEBUNDLE_TACHES_LOOP,
        protected $APIDAEBUNDLE_TACHES_MAX
    ) {
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
        $this->tachesLogger->debug('Starting '.self::getDefaultName()) ;

        for ($i = 1 ; $i <= $this->APIDAEBUNDLE_TACHES_LOOP ; $i++) {
            $this->tachesServices->monitorRunningTasks() ;
            $running = $this->tacheRepository->getTachesNumberByStatus('RUNNING') ;

            if ($running >= $this->APIDAEBUNDLE_TACHES_MAX) {
                $this->tachesLogger->debug($running . '/'.$this->APIDAEBUNDLE_TACHES_MAX.' tâches sont déjà en cours : aucune autre tâche ne sera lancée') ;
            } else {
                $next = $this->tacheRepository->getTacheToRun();

                if ($next) {
                    $this->tachesLogger->info('Une tâche en attente va être exécutée', [
                        'command' => self::getDefaultName(),
                        'id' => $next->getId(),
                        'tache' => $next->getMethod()
                    ]) ;
                    $this->tachesServices->startByProcess($next) ;
                } else {
                    $this->tachesLogger->debug('Aucune tâche en attente n\'a été trouvée') ;
                }
            }
            if ($i != $this->APIDAEBUNDLE_TACHES_LOOP) {
                sleep($this->APIDAEBUNDLE_TACHES_SLEEP) ;
            }
        }
        return Command::SUCCESS;
    }
}
