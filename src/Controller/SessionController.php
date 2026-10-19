<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Customer\CustomerSession;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SessionController
{
    public function __construct(private readonly CustomerSession $identity)
    {
    }

    #[Route('/session.php', name: 'session_keepalive', methods: ['GET'])]
    public function keepalive(Request $request): Response
    {
        if ($request->query->get('action') !== 'ping') {
            return new Response();
        }

        $session = $this->identity->session();
        $remaining = max(0, 1800 - (time() - (int) $session->get('_last_activity', time())));
        $session->set('_last_activity', time());

        return new JsonResponse([
            'status' => 'ok',
            'authenticated' => $this->identity->userId() !== null || $this->identity->adminId() !== null,
            'remaining' => $remaining,
        ]);
    }
}
