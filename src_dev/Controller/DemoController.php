<?php

namespace App\Controller ;

use ApidaeTourisme\ApidaeBundle\Entity\Tache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use ApidaeTourisme\ApidaeBundle\Services\TachesServices;
use ApidaeTourisme\ApidaeBundle\Repository\TacheRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class DemoController extends AbstractController
{
    #[Route('/', name: 'app_index')]
    public function index(TacheRepository $tacheRepository)
    {
        return $this->render('demo/index.html.twig');
    }

    #[Route('/demo', name: 'app_demo')]
    public function demo(TacheRepository $tacheRepository)
    {
        /**
         * le tacheDemoId ici est un exemple stupide puisqu'ici on a la tâche,
         * il n'y a donc aucun intérêt à ne passer que son id :
         * c'est juste pour l'exemple du monitoring par render(path())
         */
        $tacheDemo = null ;
        $tacheDemoId = null ;
        if ($tacheDemo = $tacheRepository->findLast()) {
            $tacheDemoId = $tacheDemo->getId() ;
        }

        return $this->render('demo/demo.html.twig', ['tacheDemo' => $tacheDemo, 'tacheDemoId' => $tacheDemoId]);
    }

    /**
     * Exemple d'ajout d'une tâche par une action déclenchée en Ajax.
     * Ce type d'ajout devra être fait dans chaque application et n'est pas géré par le bundle
     */
    #[Route('/demo/addTache', name: 'app_demo_addTache')]
    public function addTache(Request $request, TachesServices $tachesServices, TacheRepository $tacheRepository)
    {
        $tache = new Tache() ;
        $tache->setMethod('App\\Services\\DemoService:demo2') ;
        $tache->setParametres([
            'action' => $request->get('action'),
            'cible' => $request->get('cible')
        ]) ;
        $tache->setSignature($request->get('action').'_sur_'.$request->get('cible')) ;
        $tache_id = $tachesServices->add($tache);
        $tache = $tacheRepository->getTacheById($tache_id) ;
        return $this->render('taches/placeholder.html.twig', ['tache' => $tache]) ;
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout()
    {
        return $this->render('base.html.twig');
    }
}
