<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Customer\CommercialLeads;
use App\Service\Customer\CustomerSession;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CommercialServiceController
{
    public function __construct(private readonly CommercialLeads $leads, private readonly CustomerSession $identity)
    {
    }

    #[Route('/services', name: 'services_entry_slashless')]
    #[Route('/services/', name: 'services_entry_directory')]
    #[Route('/services/index.php', name: 'services_entry')]
    public function entry(): Response
    {
        return new RedirectResponse('/index.php#services');
    }

    #[Route('/services/check_availability.php', name: 'services_check_availability', methods: ['GET', 'POST'])]
    public function availability(Request $request): JsonResponse
    {
        $date = trim((string) ($request->query->get('date') ?? $request->request->get('date', '')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new JsonResponse(['success' => false, 'message' => 'Valid date parameter (YYYY-MM-DD) is required.'], 400);
        }
        try {
            return new JsonResponse($this->leads->availability($date));
        } catch (\Throwable) {
            return new JsonResponse(['success' => false, 'message' => 'Error checking slot availability.'], 500);
        }
    }

    #[Route('/services/commercial_consultation.php', name: 'services_commercial_consultation', methods: ['GET', 'POST'])]
    public function consultation(Request $request): JsonResponse
    {
        if (!$request->isMethod('POST')) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request method. Only POST is allowed.', 'errors' => []], 405);
        }
        try {
            [$details, $errors] = $this->leads->validate($request->request->all());
            if ($errors !== []) {
                return new JsonResponse(['success' => false, 'message' => 'Please correct the highlighted fields and try again.', 'errors' => $errors], 422);
            }
            return new JsonResponse($this->leads->create($details, $this->identity->userId()));
        } catch (\Throwable) {
            return new JsonResponse(['success' => false, 'message' => 'A server error occurred while processing your request.', 'errors' => []], 500);
        }
    }
}
