<?php

namespace ApidaeTourisme\ApidaeBundle\Command;

use Exception;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use ApidaeTourisme\ApidaeBundle\Config\TachesCode;
use Symfony\Component\Console\Input\InputInterface;
use ApidaeTourisme\ApidaeBundle\Config\TachesStatus;
use Symfony\Component\Console\Output\OutputInterface;
use ApidaeTourisme\ApidaeBundle\Services\TacheService;
use ApidaeTourisme\ApidaeBundle\Services\TachesServices;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;

/**
 * Lance l'exécution d'une tâche définie par son identifiant
 */
#[AsCommand(name: 'apidae:tache:run', description: 'Lance une tâche définie par son identifiant')]
class TacheCommand extends Command
{
    protected LoggerInterface $logger;

    public function __construct(
        LoggerInterface $tachesLogger,
        protected EntityManagerInterface $entityManager,
        protected Filesystem $filesystem,
        protected TacheRepository $tacheRepository,
        protected TachesServices $tachesServices
    ) {
        $this->logger = $tachesLogger;
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Identifiant de tâche obligatoire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $tache = $this->tacheRepository->getTacheById($id) ;
        $logger_context = ['command' => self::getDefaultName(), 'id' => $id] ;
        $this->logger->info(self::getDefaultName().' '.$id, $logger_context) ;

        if (!$tache) {
            $this->logger->warning('Tâche '.$id.' introuvable...', $logger_context) ;
            return Command::FAILURE ;
        }

        $this->logger->info('Tâche '.$id.' trouvée : lancement de la tâche', $logger_context) ;

        // On passe le statut à RUNNING pour que l'interface graphique l'affiche correctement
        $tache->setStatus(TachesStatus::RUNNING);
        $tache->setStartDate(new \DateTime());
        $tache->setResult([]);
        $tache->setEndDate(null);
        $tache->setProgress(null);
        $this->tachesServices->save($tache) ;

        $commandState = Command::FAILURE;

        try {
            $retour = $this->tachesServices->run($tache);

            if ($retour instanceof TachesCode) {
                $commandState = $retour->value ;
            } else {
                $tache->setStatus(TachesStatus::INTERRUPTED) ;
                $tache->log('warning', 'La tâche n\'a pas renvoyé un code erreur cohérent (not instanceof TachesCode)') ;
                $this->logger->warning('La tâche n\'a pas renvoyé un code erreur cohérent (not instanceof TachesCode)') ;
            }
        } catch (Exception $e) {
            /**
             * La tâche a planté sans qu'on ait catché l'erreur : ça veut dire qu'on n'a pas encore logué l'erreur.
             * On ne sait pas s'il y a déjà des logs dans $result, on va donc le récupérer.
             * On ajoute ensuite l'erreur dans les logs ($result)
             */
            $this->logger->error('Sortie de tâche sur une exception... '.$e->getMessage()) ;
            $tache->log('error', 'Sortie de tâche sur une exception... '.$e->getMessage()) ;
            $tache->setStatus(TachesStatus::INTERRUPTED) ;
        }

        // on le fait dans tous les cas... on sait jamais, un jour on mettra peut-être des logs d'erreur dans output_file alors s'il est présent, on le stocke !
        /**
         * @todo sur la console, $retour pouvait être un array.
         * Ici pour simplifier, $retour est un Command::SUCCESS/FAILURE/INVALID.
         * Il faudra voir comment gérer le cas où la tâche renvoie un fichier...
         * L'action effectuée reçoit la tâche en paramètre (voir TachesServices::run),
         * le setFichier peut se faire dedans.
         */
        // if (isset($retour['output_file'])) {
        //     $tache->setFichier($retour['output_file']);
        // }

        // Une fois la tâche terminée, on change son status
        if ($commandState === Command::SUCCESS) {
            $tache->setStatus(TachesStatus::COMPLETED);
        } else {
            $tache->setStatus(TachesStatus::FAILED);
        }

        $tache->setEndDate(new \DateTime());
        $this->tachesServices->save($tache);

        $this->logger->info('STATUS:' . $tache->getStatus(), $logger_context);
        return $commandState;
    }
}
