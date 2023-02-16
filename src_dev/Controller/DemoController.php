<?php

namespace App\Controller ;

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

class DemoController extends AbstractController
{
    #[Route('/demo', name: 'app_demo')]
    public function demo(Request $request, TacheRepository $tacheRepository, TachesServices $tachesServices)
    {
        $action = $request->get('action') ;
        $tache = null ;

        if ($action == 'creer_tache') {
            $parametres = [
                'var' => 'value1',
                'var2' => ['a','b','c']
            ] ;

            $tache = [
                'tache' => 'App\\Services\\DemoService::demo',
                'parametres' => $parametres,
                //'fichier' => $data['fichier'],
                //'parametresCaches' => ['tokenSSO' => $user->getApidaeToken()]
            ];
            $tache_id = $tachesServices->add($tache);
        }

        $taches = $tacheRepository->findAll() ;
        return $this->render('demo/demo.html.twig', ['taches' => $taches, 'tache' => $tache]) ;
    }
}
