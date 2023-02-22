<?php

namespace ApidaeTourisme\ApidaeBundle\Controller ;

use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use ApidaeTourisme\ApidaeBundle\Services\TachesServices;
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

#[Route('/apidaebundle/taches', name: 'apidaebundle_taches_')]
class TacheController extends AbstractController
{
    public function __construct(protected TachesServices $tachesServices)
    {
    }

    #[Route('/mestaches', name: 'mestaches')]
    public function mestaches(TacheRepository $tacheRepository, KernelInterface $kernel, TachesServices $tachesServices)
    {
        $this->tachesServices->monitorRunningTasks();

        /**
         * @var User $user
         */
        $user = $this->getUser();
        $taches = $tacheRepository->findBy(['userEmail' => $user->getEmail()]);
        $taches = $tachesServices->setRealStatus($taches);

        $alerts = [];
        if (!is_writable($kernel->getProjectDir() . $this->getParameter('apidaebundle.task_folder'))) {
            $alerts[] = ['type' => 'warning', 'message' => 'Attention, le dossier de stockage des tâches n\'est pas accessible en écriture pour ' . get_current_user()];
        }

        return $this->render('taches/list.html.twig', ['h1' => 'Mes tâches', 'taches' => $taches, 'alerts' => $alerts]);
    }

    #[Route('/manager', name: 'manager')]
    public function manager(TacheRepository $tacheRepository, KernelInterface $kernel, TachesServices $tachesServices)
    {
        $this->tachesServices->monitorRunningTasks();

        $taches = $tacheRepository->findAll();
        $taches = $tachesServices->setRealStatus($taches);

        $alerts = [];
        if (!is_writable($kernel->getProjectDir() . $this->getParameter('apidaebundle.task_folder'))) {
            $alerts[] = ['type' => 'warning', 'message' => 'Attention, le dossier de stockage des tâches n\'est pas accessible en écriture pour ' . get_current_user()];
        }
        return $this->render('taches/list.html.twig', ['taches' => $taches, 'alerts' => $alerts]);
    }

    #[Route('/start/{id}', name: 'start')]
    public function start(string $id, Request $request, TachesServices $tachesServices, TacheRepository $tacheRepository)
    {
        $tache = $tacheRepository->findOneBy(['id' => $id]);
        $pid = $tachesServices->startByProcess($tache, $request->get('force') == true);
        return new JsonResponse([
            'id' => (int)$tache->getId(),
            'pid' => $pid,
            'startdate' => $tache->getStartDate()
        ]);
    }

    #[Route('/stop/{id}', name: 'stop')]
    public function stop(string $id, Request $request, TachesServices $tachesServices, TacheRepository $tacheRepository)
    {
        $tache = $tacheRepository->findOneBy(['id' => $id]);
        return new JsonResponse($tachesServices->stop($tache));
    }

    #[Route('/delete/{id}', name: 'delete')]
    public function delete(string $id, Request $request, TachesServices $tachesServices, TacheRepository $tacheRepository)
    {
        $tache = $tacheRepository->getTacheById($id);
        $ret = [];
        if (!$tache) {
            $ret['error'] = 'Tâche introuvable';
        } else {
            /**
             * @var User $user
             */
            $user = $this->getUser();
            if ($tache->getUserEmail() == $user->getEmail()) {
                $ret = $tachesServices->delete($id);
            } else {
                $ret['error'] = 'l\'utilisateur #' . $user->getEmail() . ' ne peut pas supprimer une tâche #' . $id . ' de l\'utilisateur #' . $tache->getUserEmail();
            }
        }
        return new JsonResponse($ret);
    }

    #[Route('/download/{id}', name: 'download')]
    public function download(string $id, Request $request, TachesServices $tachesServices, TacheRepository $tacheRepository, KernelInterface $kernel)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        } else {
            /**
             * @var User $user
             */
            $user = $this->getUser();
            if ($tache->getUserEmail() != $user->getEmail()) {
                throw new \Exception('Utilisateur ' . $user->getEmail() . ' ne peut pas accéder au fichier de la tâche ' . $id);
            }
        }

        $response = new BinaryFileResponse($kernel->getProjectDir() . $this->getParameter('apidaebundle.task_folder') . $tache->getId() . '/' . $tache->getFichier());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $tache->getFichier()
        );
        return $response;
    }

    #[Route('/status/{id}', name: 'status')]
    public function status(string $id, Request $request, TacheRepository $tacheRepository, TachesServices $tachesServices)
    {
        $_format = (in_array($request->get('_format'), ['html', 'json'])) ? $request->get('_format') : 'html';
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesServices->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        if ($_format == 'json') {
            $ret = $tache->get();
            /**
             * @var User $user
             */
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

    #[Route('/result/{id}', name: 'result')]
    public function result(string $id, TacheRepository $tacheRepository, TachesServices $tachesServices)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesServices->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        return $this->render(
            'taches/result.html.twig',
            ['tache' => $tache]
        );
    }


    /**
     * @todo : on vérifie que l'utilisateur qui cherche à accéder a les droit sur la tâche ou pas ?
     */
    #[Route('/tache/{id}', name: 'tache')]
    public function tache(string $id, TacheRepository $tacheRepository, TachesServices $tachesServices)
    {
        $tache = $tacheRepository->getTacheById($id);
        if (!$tache) {
            throw new \Exception('Tache introuvable');
        }
        $tachesServices->setRealStatus([$tache]);
        $tache = $tacheRepository->getTacheById($id);

        return $this->render(
            'taches/tache.html.twig',
            ['tache' => $tache]
        );
    }
}
