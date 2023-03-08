<?php

namespace ApidaeTourisme\ApidaeBundle\Services;

use Exception;
use ReflectionMethod;
use Psr\Log\LoggerInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Process\Process;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\KernelInterface;
use ApidaeTourisme\ApidaeBundle\Command\TacheCommand;
use ApidaeTourisme\ApidaeBundle\Command\TachesCommand;
use ApidaeTourisme\ApidaeBundle\Config\TachesCode;
use ApidaeTourisme\ApidaeBundle\Config\TachesStatus;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class TachesServices
{
    protected string $dossierTaches;
    protected LoggerInterface $logger ;
    protected ContainerInterface $container ;

    public const FICHIERS_EXTENSIONS = ['xlsx', 'ods'];
    public const FICHIERS_MIMES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.oasis.opendocument.spreadsheet',
    ];

    public function __construct(
        protected EntityManagerInterface $em,
        protected TacheRepository $tacheRepository,
        protected LoggerInterface $tachesLogger,
        protected Security $security,
        protected KernelInterface $kernel,
        protected ParameterBagInterface $params,
        protected Filesystem $filesystem,
        protected SluggerInterface $slugger
    ) {
        $this->dossierTaches = $this->kernel->getProjectDir() . $this->params->get('apidaebundle.task_folder') ;
        $this->container = $kernel->getContainer() ;
    }

    public function getDossierTaches()
    {
        return $this->dossierTaches;
    }

    /**
     * Ajoute une tâche TO_RUN en bdd
     */
    public function add(Tache $tache, ?array $params = null): int
    {
        if (!isset($params['userEmail'])) {
            /**
             * @var ApidaeUser $user
             */
            $user = $this->security->getUser();
            $tache->setUserEmail($user->getEmail());
        } else {
            $tache->setUserEmail($params['utilisateurEmail']);
        }

        if (isset($params['method'])) {
            $tache->setMethod($params['method']);
        }

        if (isset($params['parametres'])) {
            $tache->setParametres($params['parametres']);
        }
        if (isset($params['parametresCaches'])) {
            $tache->setParametresCaches($params['parametresCaches']);
        }
        //if (isset($params['status'])) $tache->setStatus($params['status']);
        if (isset($params['result'])) {
            $tache->setResult($params['result']);
        }

        $tache->setCreationdate(new \DateTime());

        $tache->setStatus(TachesStatus::TO_RUN);

        $this->save($tache);

        $id = $tache->getId();

        /**
         * Traitement du fichier :
         * 2 cas : le fichier est déjà créé et déjà mis à sa place.
         */
        if (
            isset($params['fichier'])
            && gettype($params['fichier']) == 'object'
            && get_class($params['fichier']) == 'Symfony\Component\HttpFoundation\File\UploadedFile'
        ) {
            $tachePath = $this->kernel->getProjectDir() .'/'. $this->params->get('apidaebundle.task_folder') . $id . '/';
            try {
                $this->filesystem->remove($tachePath);
                $this->filesystem->mkdir($tachePath, 0777);
            } catch (IOException $e) {
                $this->tachesLogger->error(__METHOD__ . ':' . $e->getMessage());
                return false;
            }

            if (!in_array($params['fichier']->guessExtension(), self::FICHIERS_EXTENSIONS)) {
                $this->tachesLogger->error(__METHOD__ . ': Type de fichier non autorisé');
                return false;
            }
            $originalFilename = pathinfo($params['fichier']->getClientOriginalName(), PATHINFO_FILENAME);
            $filename = $this->slugger->slug($originalFilename) .  '.' . $params['fichier']->guessExtension();

            try {
                $params['fichier']->move(
                    $tachePath,
                    $filename
                );
            } catch (FileException $e) {
                $this->tachesLogger->error(__METHOD__ . ': Déplacement du fichier impossible');
                return false;
            }

            $tache->setFichier($filename);
            $this->save($tache);
        }

        return $id;
    }

    public function restart(Tache $tache)
    {
        $tache->setStatus(TachesStatus::TO_RUN);
        //$tache->setCreationdate(new \DateTime());
        $this->save($tache) ;
    }

    /**
     * Lance une tâche en process (tâche de fond)
     * Une fois lancée, la tâche renseigne le pid du process en base
     */
    public function startByProcess(Tache $tache, bool $force = false): void
    {
        if (!$force && $tache->getStatus() != TachesStatus::TO_RUN->value) {
            throw new \Exception('La tâche ' . $tache->getId() . ' n\'est pas en état TO_RUN (' . $tache->getStatus() . ')');
        }

        $path = $this->kernel->getProjectDir() . '/bin/console';

        $cl = $path . ' '.TacheCommand::getDefaultName().' ' . $tache->getId();
        $this->tachesLogger->info(__METHOD__ . ' => new Process ' . $cl);


        /**
         * @see https://symfony.com/doc/current/components/process.html
         * $process = new Process([$path, $tache->getMethod()]);
         * $process = new Process($cl);
         * $process->start();
        */
        /**
         * @see https://stackoverflow.com/a/58765200/2846837
         */
        $process = Process::fromShellCommandline($cl);
        $process->start();

        $tache->setEndDate(null);
        $tache->setProgress(null);
        $this->save($tache);
    }

    /**
     * Stoppe une tâche par un kill -9 en se basant sur son $tache->getPid()
     */
    public function stop(Tache $tache): array
    {
        /**
         * @todo : voir comment tuer un process à partir du pid
         * @warning : pas sûr que ce soit facile, il faut déjà que l'utilisateur ayant lancé le process soit le même que celui qui lance le stop
         *  et il faut que le processus tourne toujours, sauf qu'il a pu lancer des sous-process et ne plus tourner lui même alors que la tâche n'est pas terminée
         */
        $response = [];

        $killable_status = [
            TachesStatus::RUNNING
        ];

        $realPid = $this->getPidFromPS($tache->getId());
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
            $response['error'] = 'La tâche ' . $tache->getId() . ' n\'est pas en état [' . implode(',', $killable_status) . '] (' . $tache->getStatus() . ')';
        }

        return $response;
    }

    /**
     * Exécute la tâche
     */
    public function run(Tache $tache): TachesCode
    {
        $this->tachesLogger->info(__METHOD__.'('.$tache->getId().')') ;

        /**
         * @var int $ret
         */
        $ret = TachesCode::FAILURE ;

        // Méthode non statique : App\Class:method
        if (preg_match("#^([a-zA-Z\\\]+):([a-zA-Z0-9]+)$#", $tache->getMethod(), $match)) {
            /**
             * @see https://www.php.net/manual/en/function.is-callable.php#126199
             * Impossible d'utiliser is_callable ici pour une méthode non statique
             */
            //if (! is_callable([$match[1],$match[2]], false, $callable_name)) {
            if (! method_exists($match[1], $match[2])) {
                $this->tachesLogger->error('Méthode invalide : '.$tache->getMethod()) ;
                return TachesCode::FAILURE ;
            } else {
                $rm = new ReflectionMethod($match[1], $match[2]);
                if ($rm->isStatic()) {
                    $this->tachesLogger->error('Méthode invalide : '.$tache->getMethod().' (la méthode est statique, utilisez :: au lieu de :)') ;
                    return TachesCode::FAILURE ;
                }
            }
            $this->tachesLogger->info(__METHOD__.' : starting : '.lcfirst($match[1]).'->'.$match[2].'(...)') ;
            /**
             * On s'apprète à lancer une méthode sur un service dont on n'a pas connaissance :
             * App\Services\Whatever->method(...)
             * Comme on ne le connait pas il faut l'instancier dynamiquement
             */
            /**
             * @see https://stackoverflow.com/a/65526859
             */

            // read the parameters given in the cmd and decide what class is
            // gona be injected.
            // $service_name = "App\\My\\Namespace\\ServiceClassName"
            $service = $this->container->get($match[1]);
            $ret = $service->{$match[2]}($tache);
        }
        // Méthode statique : App\Class::method
        elseif (preg_match("#^([a-zA-Z\\\]+)::([a-zA-Z]+)$#", $tache->getMethod(), $match)) {
            if (! is_callable([$match[1],$match[2]], false, $callable_name)) {
                $this->tachesLogger->error('Méthode invalide : '.$tache->getMethod().' ('.$callable_name.')') ;
                return TachesCode::FAILURE ;
            } else {
                $rm = new ReflectionMethod($match[1], $match[2]);
                if (! $rm->isStatic()) {
                    $this->tachesLogger->error('Méthode invalide : '.$tache->getMethod().' (la méthode n\'est pas statique, utilisez : au lieu de ::)') ;
                    return TachesCode::FAILURE ;
                }
            }

            $ret = call_user_func($match[1].'::'.$match[2], $tache);
        } else {
            $this->tachesLogger->error('Impossible d\'exécuter la tâche : la commande '.$tache->getMethod().' est incohérence') ;
        }

        return $ret;
    }

    /**
     * @todo
     * Vérifie le statut des tâches en cours (status=RUNNING)
     *
     */
    public function monitorRunningTasks(): void
    {
        // Récupérer le pid des tâches RUNNING
        // Vérifier si le pid tourne toujours ?
        // Si non : mettre la tâche à INTERRUPTED : la tâche aurait dû passer à COMPLETED
        $taches = $this->tacheRepository->getTachesByStatus(TachesStatus::RUNNING);
        foreach ($taches as $tache) {
            $tacheId = $tache->getId();
            if (!$this->running($tacheId)) {
                $tache->setStatus(TachesStatus::INTERRUPTED);
                $this->save($tache);
            }
        }
    }

    /**
     * Détermine si une tâche est en cours d'exécution ou non
     */
    public function running(int $tacheId)
    {
        return $this->getPidFromPS($tacheId) !== false;
    }

    /**
     * Renvoie le pid réel de la tâche en cours
     */
    public static function getPidFromPS(int $tacheId)
    {
        $process = Process::fromShellCommandline('pgrep -f \'[b]in/console '.TacheCommand::getDefaultName().' ' . $tacheId . '\'');
        $process->run();
        $output = $process->getOutput();
        if (is_numeric($output)) {
            return (int)$output;
        }
        return false;
    }

    public function setProgress(int $tacheId, array $progress)
    {
        if (is_integer($tacheId)) {
            $tache = $this->tacheRepository->getTacheById($tacheId);
        }
        if (get_class($tache) != Tache::class) {
            return false;
        }

        $tache->setProgress($progress);
        $this->save($tache);
    }

    public function delete(Tache $tache)
    {
        $this->filesystem->remove($this->kernel->getProjectDir() . $this->params->get('apidaebundle.task_folder') . $tache->getId());
        $this->em->remove($tache) ;
        $this->em->flush() ;
        return true;
    }

    public function setRealStatus(array $taches): array
    {
        foreach ($taches as $tache) {
            if ($tache->getStatus() == TachesStatus::RUNNING) {
                $pid = self::getPidFromPS($tache->getId());
                $tache->setPid($pid);
                if ($pid !== false) {
                    $tache->setRealStatus(TachesStatus::RUNNING);
                } else {
                    $tache->setRealStatus(TachesStatus::INTERRUPTED);
                }
            }
        }

        $this->monitorRunningTasks();
        return $taches;
    }

    /**
     * persist & flush
     */
    public function save(Tache $tache): void
    {
        $this->em->persist($tache);
        $this->em->flush();
    }
}
