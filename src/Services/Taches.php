<?php

namespace ApidaeTourisme\ApidaeBundle\Services;

use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Process\Process;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Taches
{
    protected $dossierTaches;

    public const FICHIERS_EXTENSIONS = ['xlsx', 'ods'];
    public const FICHIERS_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    public function __construct(
        protected EntityManagerInterface $em,
        protected TacheRepository $tacheRepository,
        protected LoggerInterface $logger,
        protected Security $security,
        protected KernelInterface $kernel,
        protected ParameterBagInterface $params,
        protected Filesystem $filesystem,
        protected SluggerInterface $slugger
    ) {
        $this->dossierTaches = $this->kernel->getProjectDir() . '/public/taches/';
    }

    public function getDossierTaches()
    {
        return $this->dossierTaches;
    }

    public function add(array $t)
    {
        $tache = new Tache();

        if (!isset($t['userEmail'])) {
            $user = $this->security->getUser();
            $tache->setUserEmail($user->getEmail());
        } else {
            $tache->setUserEmail($t['utilisateurEmail']);
        }

        $tache->setTache($t['tache']);

        if (isset($t['parametres'])) {
            $tache->setParametres($t['parametres']);
        }
        if (isset($t['parametresCaches'])) {
            $tache->setParametresCaches($t['parametresCaches']);
        }
        //if (isset($t['status'])) $tache->setStatus($t['status']);
        if (isset($t['result'])) {
            $tache->setResult($t['result']);
        }


        $tache->setCreationdate(new \DateTime());

        $tache->setStatus(Tache::STATUS['TO_RUN']);

        $this->em->persist($tache);
        $this->em->flush();

        $id = $tache->getId();

        $tachePath = $this->kernel->getProjectDir() . $this->params->get('app.task_folder') . $id . '/';
        try {
            $this->filesystem->remove($tachePath);
            $this->filesystem->mkdir($tachePath, 0777);
        } catch (IOException $e) {
            $this->logger->error(__METHOD__ . ':' . $e->getMessage());
            return false;
        }
        /**
         * Traitement du fichier :
         * 2 cas : le fichier est déjà créé et déjà mis à sa place.
         */
        if (
            isset($t['fichier'])
            && gettype($t['fichier']) == 'object'
            && get_class($t['fichier']) == 'Symfony\Component\HttpFoundation\File\UploadedFile'
        ) {
            if (!in_array($t['fichier']->guessExtension(), self::FICHIERS_EXTENSIONS)) {
                $this->logger->error(__METHOD__ . ': Type de fichier non autorisé');
                return false;
            }
            $originalFilename = pathinfo($t['fichier']->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $this->slugger->slug($originalFilename) .  '.' . $t['fichier']->guessExtension();

            try {
                $t['fichier']->move(
                    $tachePath,
                    $filename
                );
            } catch (FileException $e) {
                $this->logger->error(__METHOD__ . ': Déplacement du fichier impossible');
                return false;
            }

            $tache->setFichier($filename);
            $this->em->persist($tache);
            $this->em->flush();
        }

        return $id;
    }

    /**
     * @see https://symfony.com/doc/current/components/process.html
     * Lance une tâche en process (tâche de fond)
     * Une fois lancée, la tâche renseigne le pid du process en base
     */
    public function start(int $id, bool $force = false)
    {
        $tache = $this->tacheRepository->getTacheById($id);

        if (!$tache) {
            throw new InvalidParameterException('tâche ' . $id . ' introuvable');
        }
        if (!$force && $tache->getStatus() != Tache::STATUS['TO_RUN']) {
            throw new \Exception('La tâche ' . $id . ' n\'est pas en état TO_RUN (' . $tache->getStatus() . ')');
        }

        $path = $this->kernel->getProjectDir() . '/bin/console';

        $cl = $path . ' app:tache:run ' . $tache->getId();
        $this->logger->info(__METHOD__ . ' => new Process ' . $cl);

        /*
        //$process = new Process([$path, $tache->getTache()]);
        $process = new Process($cl);
        $process->start();
        */
        /**
         * @see https://stackoverflow.com/a/58765200/2846837
         */
        $process = Process::fromShellCommandline($cl);
        $process->start();

        /**
         * En réalité ça ne sert quasiment à rien puisque le pid de création 222 ne reste pas, il sera remplacé par un fork (222 => 223)
         * C'est pour ça qu'on a ici une méthode Taches::getPid() qui va rechercher le pid d'une tâche directement dans ps -ef
         */
        $pid = $process->getPid();
        $tache->setPid($pid);
        $tache->setEndDate(null);
        $tache->setProgress(null);
        $this->em->persist($tache);
        $this->em->flush();

        return $pid;
    }

    /**
     * @todo : voir comment tuer un process à partir du pid
     * @warning : pas sûr que ce soit facile, il faut déjà que l'utilisateur ayant lancé le process soit le même que celui qui lance le stop
     *  et il faut que le processus tourne toujours, sauf qu'il a pu lancer des sous-process et ne plus tourner lui même alors que la tâche n'est pas terminée
     */
    public function stop(int $tacheId)
    {
        $response = [];

        $tache = $this->tacheRepository->getTacheById($tacheId);

        if (!$tache) {
            $response['error'] = 'La tâche ' . $tacheId . ' est introuvable en base';
            return $response;
        }

        $killable_status = [
            Tache::STATUS['RUNNING']
        ];

        $realPid = $this->getPid($tacheId);
        if (!$realPid) {
            $response['error'] = 'La tâche ne semble pas être en cours d\'éxécution';
            return $response;
        }

        if (in_array($tache->getStatus(), $killable_status)) {
            try {
                $process = new Process(['kill', '-9', $realPid]);
                $process->run();
                if ($process->getErrorOutput() == "" && $process->getExitCode() == 0) {
                    $response['result'] = 'ok';
                } else {
                    $response['error'] = $process->getErrorOutput();
                }
            } catch (\Exception $e) {
                $response = ['error' => 'kill -9 failed'];
            }
        } else {
            $response['error'] = 'La tâche ' . $tacheId . ' n\'est pas en état [' . implode(',', $killable_status) . '] (' . $tache->getStatus() . ')';
        }

        return $response;
    }

    /**
     * @todo
     * Vérifie le statut des tâches en cours (status=RUNNING)
     *
     */
    public function monitorRunningTasks()
    {
        // Récupérer le pid des tâches RUNNING
        // Vérifier si le pid tourne toujours ?
        // Si non : mettre la tâche à INTERRUPTED : la tâche aurait dû passer à COMPLETED
        $taches = $this->tacheRepository->getTachesByStatus(Tache::STATUS['RUNNING']);
        foreach ($taches as $tache) {
            $tacheId = $tache->getId();
            if (!$this->running($tacheId)) {
                $tache->setStatus(Tache::STATUS['INTERRUPTED']);
                $this->em->persist($tache);
                $this->em->flush();
            }
        }
    }

    /**
     * Détermine si une tâche est en cours d'exécution ou non
     */
    public function running(int $tacheId)
    {
        return $this->getPid($tacheId) !== false;
    }

    /**
     * Renvoie le pid réel de la tâche en cours
     */
    public static function getPid(int $tacheId)
    {
        $process = Process::fromShellCommandline('pgrep -f \'[b]in/console app:tache:run ' . $tacheId . '\'');
        $process->run();
        $output = $process->getOutput();
        if (is_numeric($output)) {
            return (int)$output;
        }
        return false;
    }

    public function setProgress($tache, array $progress)
    {
        if (is_integer($tache)) {
            $tache = $this->tacheRepository->getTacheById($tache);
        }
        if (get_class($tache) != Tache::class) {
            return false;
        }

        $tache->setProgress($progress);
        $this->em->persist($tache);
        $this->em->flush();
    }

    public function delete(int $tacheId)
    {
        $tache = $this->tacheRepository->getTacheById($tacheId);
        if (!$tache) {
            return false;
        }
        $this->filesystem->remove($this->kernel->getProjectDir() . $this->container->getParameter('app.task_folder') . $tache->getId());
        $this->em->remove($tache);
        $this->em->flush();
        return true;
    }

    public function setRealStatus($taches)
    {
        foreach ($taches as $tache) {
            if ($tache->getStatus() == Tache::STATUS['RUNNING']) {
                $pid = Taches::getPid($tache->getId());
                $tache->setPid($pid);
                if ($pid !== false) {
                    $tache->setRealStatus('RUNNING');
                } else {
                    $tache->setRealStatus('NOT_RUNNING');
                }
            }
        }

        $this->monitorRunningTasks();
        return $taches;
    }
}
