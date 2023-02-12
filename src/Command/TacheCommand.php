<?php

namespace ApidaeTourisme\ApidaeBundle\Command;

use Psr\Log\LoggerInterface;
//use App\Service\Taches;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ApidaeTourisme\ApidaeBundle\Services\TachesExecuter;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;

/**
 * Lance l'exécution d'une tâche définie par son identifiant
 */
#[AsCommand(name: 'app:tache:run', description: 'Lance une tâche définie par son identifiant')]
class TacheCommand extends Command
{
    protected LoggerInterface $logger;
    protected EntityManagerInterface $entityManager;
    protected Filesystem $filesystem;
    //protected Taches $taches;
    protected TachesExecuter $tachesExecuter;
    protected TacheRepository $tacheRepository;

    public function __construct(
        LoggerInterface $tachesLogger,
        EntityManagerInterface $entityManager,
        Filesystem $filesystem,
        //Taches $taches,
        TachesExecuter $tachesExecuter,
        TacheRepository $tacheRepository
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $tachesLogger;
        $this->filesystem = $filesystem;
        //$this->taches = $taches;
        $this->tachesExecuter = $tachesExecuter;
        $this->tacheRepository = $tacheRepository;
        parent::__construct();
    }

    protected function configure()
    {
        $this->addArgument('id', InputArgument::REQUIRED, 'Identifiant de tâche obligatoire');
        $this->addArgument('verbose', InputArgument::OPTIONAL, 'Verbose ? (1|0)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id = $input->getArgument('id');
        $verbose = $input->getArgument('verbose');

        $logger_key = self::$defaultName . ' ' . $id;
        $this->logger->info($logger_key);

        $tache = $this->tacheRepository->getTacheById($id);
        if (!$tache) {
            $log = $logger_key . ':tache ' . $id . ' non trouvée';
            $this->logger->info($log);
            return Command::FAILURE;
        }

        // On passe le statut à RUNNING pour que l'interface graphique l'affiche correctement
        $tache->setStatus(Tache::STATUS['RUNNING']);
        $tache->setStartDate(new \DateTime());
        $tache->setResult(null);
        $tache->setEndDate(null);
        $tache->setProgress(null);
        $this->entityManager->persist($tache);
        $this->entityManager->flush();

        $tache->setVerbose($verbose);

        $completed = false;
        /**
         * TachesExecuter::exec($id) lance la tâche (ex: DescriptifsThematisesService::export)
         * récupère le $retour[] de la tâche et le renvoie ici directement
         * Si la tâche ne s'est pas lancée, $retour peut être false
         */
        $retour = null;
        try {
            $tache->setResult(['logs' => 'Lancement de la tâche (exec)...']);
            $retour = $this->tachesExecuter->exec($id);
            if ($retour === true) {
                $completed = true;
            } elseif ($tache->getVerbose()) {
                dump($tache->getResult());
            }
        } catch (\Exception $e) {
            /**
             * La tâche a planté sans qu'on ait catché l'erreur : ça veut dire qu'on n'a pas encore logué l'erreur.
             * On ne sait pas s'il y a déjà des logs dans $result, on va donc le récupérer.
             * On ajoute ensuite l'erreur dans les logs ($result)
             */
            $retour = 'INTERRUPTED';
            $result = $tache->getResult();
            $result[] = ['error', $e->getFile() . ':' . $e->getLine(), $e->getMessage()];
            $tache->setResult($result);
            if ($tache->getVerbose()) {
                dump($e);
            }
        }

        // on le fait dans tous les cas... on sait jamais, un jour on mettra peut-être des logs d'erreur dans output_file alors s'il est présent, on le stocke !
        if (isset($retour['output_file'])) {
            $tache->setFichier($retour['output_file']);
        }

        // Une fois la tâche terminée, on change son status
        if ($completed == true) {
            $tache->setStatus(Tache::STATUS['COMPLETED']);
        } else {
            $tache->setStatus(Tache::STATUS['FAILED']);
        }

        $tache->setEndDate(new \DateTime());
        $this->entityManager->persist($tache);
        $this->entityManager->flush();

        $this->logger->info($logger_key . ':' . $tache->getStatus());
        return $completed ? Command::SUCCESS : Command::FAILURE;
    }
}
