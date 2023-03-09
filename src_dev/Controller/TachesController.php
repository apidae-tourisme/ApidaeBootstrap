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

class TachesController extends AbstractController
{
    #[Route('/taches', name: 'app_taches')]
    public function demo(Request $request, TacheRepository $tacheRepository, TachesServices $tachesServices)
    {
        $action = $request->get('action') ;
        $nb = (int)$request->get('nb') ;

        if ($action == 'creer_taches' && $nb > 1 && $nb <= 100) {
            for ($i = 1 ; $i < 10 ; $i++) {
                $tache = new Tache() ;
                $tache->setMethod('App\\Services\\DemoService:demo2') ;
                $tache->setParametres([
                    'num' => $i,
                    'var2' => ['a','b','c']
                ]) ;
                $tache->setSignature('action1_sur_objet'.$i) ;
                $tachesServices->add($tache);
            }
        }

        $taches = $tacheRepository->findAll() ;
        return $this->render('demo/taches.html.twig', ['taches' => $taches]) ;
    }
}
