<?php

namespace ApidaeTourisme\ApidaeBundle\Command;

use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
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
        if ($tache) {
            $this->logger->info('Tâche trouvée => $this->tachesServices->execute(...)', $logger_context) ;

            $this->logger->info('execute...', $logger_context);

            // On passe le statut à RUNNING pour que l'interface graphique l'affiche correctement
            $tache->setStatus(TachesStatus::RUNNING);
            $tache->setStartDate(new \DateTime());
            $tache->setResult([]);
            $tache->setEndDate(null);
            $tache->setProgress(null);
            $this->tachesServices->save($tache) ;

            $commandState = Command::FAILURE;
            /**
             * TachesExecuter::exec($id) lance la tâche (ex: DescriptifsThematisesService::export)
             * récupère le $retour[] de la tâche et le renvoie ici directement
             * Si la tâche ne s'est pas lancée, $retour peut être false
             */
            $retour = null;
            try {
                $this->logger->info('Lancement de la tâche (exec)...', $logger_context) ;
                $retour = $this->tachesServices->run($tache);
                if (is_bool($retour)) {
                    if ($retour) {
                        $commandState = Command::SUCCESS;
                    } else {
                        $this->logger->warning('La tâche ne s\'est pas exécutée correctement') ;
                    }
                } elseif (is_int($retour) && in_array($retour, [Command::SUCCESS, Command::FAILURE, Command::INVALID])) {
                    $commandState = $retour ;
                } else {
                    $this->logger->warning('La tâche n\'a pas renvoyé un code erreur cohérent :(') ;
                    $this->logger->warning(var_export($retour)) ;
                }
            } catch (\Exception $e) {
                /**
                 * La tâche a planté sans qu'on ait catché l'erreur : ça veut dire qu'on n'a pas encore logué l'erreur.
                 * On ne sait pas s'il y a déjà des logs dans $result, on va donc le récupérer.
                 * On ajoute ensuite l'erreur dans les logs ($result)
                 */
                $this->logger->error('Uncatched exception...') ;
                $this->logger->error($e->getMessage()) ;
                $retour = 'INTERRUPTED';
                $tache->log('error', $e->getFile() . ':' . $e->getLine(), $e->getMessage()) ;
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
        } else {
            $this->logger->warning('Tâche '.$id.' introuvable...', $logger_context) ;
            return Command::FAILURE ;
        }
    }
}
