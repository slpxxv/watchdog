<?php

declare(strict_types=1);

namespace Watchdog\Identity\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    /**
     * The POST is handled by the firewall's form_login before this action runs; a failed attempt
     * lands here again with the error in the session.
     */
    #[Route('/login', name: 'identity_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $failed = null !== $authentication->getLastAuthenticationError();

        return $this->render(
            'identity/login.html.twig',
            ['lastUsername' => $authentication->getLastUsername(), 'failed' => $failed],
            new Response(status: $failed ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK),
        );
    }

    #[Route('/logout', name: 'identity_logout', methods: ['GET', 'POST'])]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles /logout.');
    }
}
