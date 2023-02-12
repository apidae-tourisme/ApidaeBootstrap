<?php

namespace ApidaeTourisme\ApidaeBundle\Controller ;

use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use ApidaeTourisme\ApidaeBundle\Services\Taches;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Exception\InvalidParameterException;

/**
 * @Route("/taches", priority=700)
 */
class TacheController extends AbstractController
{
    public function __construct(protected Taches $taches)
    {
    }

    /**
     * @Route("/", name="taches")
     */
    public function index()
    {
        return $this->render('base.html.twig');
    }

    #[Route('/login', name: 'app_login')]
    public function login()
    {
        return $this->render('login.html.twig');
    }

    /**
     * @Route("/mestaches", name="taches_mestaches")
     */
    public function mestaches(TacheRepository $tacheRepository, KernelInterface $kernel, Taches $tachesService)
    {
        $this->taches->monitorRunningTasks();

        $user = $this->getUser();
        $taches = $tacheRepository->findBy(['userEmail' => $user->getEmail()]);
        $taches = $tachesService->setRealStatus($taches);

        $alerts = [];
        if (!is_writable($kernel->getProjectDir() . $this->getParameter('app.task_folder'))) {
            $alerts[] = ['type' => 'warning', 'message' => 'Attention, le dossier de stockage des tâches n\'est pas accessible en écriture pour ' . get_current_user()];
        }

        return $this->render('taches/list.html.twig', ['h1' => 'Mes tâches', 'taches' => $taches, 'alerts' => $alerts]);
    }

    /**
     * @Route("/manager", name="taches_manager")
     */
    public function manager(TacheRepository $tacheRepository, KernelInterface $kernel, Taches $tachesService)
    {
        $this->taches->monitorRunningTasks();

        $taches = $tacheRepository->findAll();
        $taches = $tachesService->setRealStatus($taches);

        $alerts = [];
        if (!is_writable($kernel->getProjectDir() . $this->getParameter('app.task_folder'))) {
            $alerts[] = ['type' => 'warning', 'message' => 'Attention, le dossier de stockage des tâches n\'est pas accessible en écriture pour ' . get_current_user()];
        }
        return $this->render('taches/list.html.twig', ['taches' => $taches, 'alerts' => $alerts]);
    }


    /**
     * @Route("/start/{id}", name="taches_start")
     */
    public function start(string $id, Request $request, Taches $taches, TacheRepository $tacheRepository)
    {
        $pid = $taches->start($id, $request->get('force') == true);
        $tache = $tacheRepository->findOneBy(['id' => $id]);
        return new JsonResponse([
            'id' => (int)$id,
            'pid' => $pid,
            'startdate' => $tache->getStartDate()
        ]);
    }

    /**
     * @Route("/stop/{id}", name="taches_stop")
     */
    public function stop(string $id, Request $request, Taches $taches)
    {
        return new JsonResponse($taches->stop($id));
    }

    /**
     * @Route("/delete/{id}", name="taches_delete")
     */
    public function delete(string $id, Request $request, Taches $taches, TacheRepository $tacheRepository)
    {
        $tache = $tacheRepository->getTacheById($id);
        $ret = [];
        if (!$tache) {
            $ret['error'] = 'Tâche introuvable';
        } else {
            $user = $this->getUser();
            if ($tache->getUserEmail() == $user->getEmail()) {
                $ret = $taches->delete($id);
            } else {
                $ret['error'] = 'l\'utilisateur #' . $user->getEmail() . ' ne peut pas supprimer une tâche #' . $id . ' de l\'utilisateur #' . $tache->getUserEmail();
            }
        }
        return new JsonResponse($ret);
    }

    /**
     * @Route("/download/{id}", name="taches_download")
     */
    public function download(string $id, Request $request, Taches $taches, TacheRepository $tacheRepository, KernelInterface $kernel)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        } else {
            $user = $this->getUser();
            if ($tache->getUserEmail() != $user->getEmail()) {
                throw new \Exception('Utilisateur ' . $user->getEmail() . ' ne peut pas accéder au fichier de la tâche ' . $id);
            }
        }

        $response = new BinaryFileResponse($kernel->getProjectDir() . $this->getParameter('app.task_folder') . $tache->getId() . '/' . $tache->getFichier());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $tache->getFichier()
        );
        return $response;
    }

    /**
     * @Route("/status/{id}", name="taches_status")
     */
    public function status(string $id, Request $request, TacheRepository $tacheRepository, Taches $tachesService)
    {
        $_format = (in_array($request->get('_format'), ['html', 'json'])) ? $request->get('_format') : 'html';
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesService->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        if ($_format == 'json') {
            $ret = $tache->get();
            $user = $this->getUser();
            if ($tache->getUserEmail() != $user->getEmail()) {
                unset($ret['parametresCaches']);
            }
            return new JsonResponse($ret);
        }


        return $this->render(
            'taches/status.' . $_format . '.twig',
            ['tache' => $tache]
        );
    }

    /**
     * @Route("/result/{id}", name="taches_result")
     */
    public function result(string $id, TacheRepository $tacheRepository, Taches $tachesService)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesService->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        return $this->render(
            'taches/result.html.twig',
            ['tache' => $tache]
        );
    }


    /**
     * @Route("/tache/{id}", name="taches_tache")
     * @todo : on vérifie que l'utilisateur qui cherche à accéder a les droit sur la tâche ou pas ?
     */
    public function tache(string $id, TacheRepository $tacheRepository, Taches $tachesService)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesService->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        return $this->render(
            'taches/tache.html.twig',
            ['tache' => $tache]
        );
    }
}
