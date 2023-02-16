<?php

namespace ApidaeTourisme\ApidaeBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        //return $this->redirectToRoute('connect_start', ['service' => 'apidae']) ;
        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Link to this controller to start the "connect" process
     *
     * @Route("/connect/start", name="connect_start")
     */
    public function connectStart(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        return match ($request->query->get('service')) {
            'apidae' => $clientRegistry
                        ->getClient($request->query->get('service'))
                        ->redirect(['sso'], []),
            'auth0' => $clientRegistry
                        ->getClient($request->query->get('service'))
                        ->redirect(['openid email'], []),
            default => new RedirectResponse('index')
        } ;
    }

    /**
     * Apidae redirects to back here afterwards
     *
     * @Route("/connect/check", name="connect_check")
     */
    public function connectCheck(Session $session)
    {
        $target = $session->get('_security.main.target_path');
        if ($target != null) {
            return $this->redirect($session->get('_security.main.target_path'));
        } else {
            return $this->redirectToRoute('index');
        }
    }
}
