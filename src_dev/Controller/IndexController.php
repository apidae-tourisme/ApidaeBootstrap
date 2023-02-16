<?php

    namespace App\Controller ;

    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\Routing\RouterInterface;
    use Symfony\Component\Routing\Annotation\Route;

    class IndexController extends AbstractController
    {
        #[Route('/', name: 'app_index')]
        public function index()
        {
            return $this->render('base.html.twig', ['debug' => 'debug']);
        }

        #[Route('/login', name: 'app_login')]
        public function login()
        {
            return $this->render('login.html.twig');
        }

        #[Route('/logout', name: 'app_logout')]
        public function logout()
        {
            return $this->render('base.html.twig');
        }
    }
