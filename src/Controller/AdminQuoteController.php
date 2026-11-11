<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAuth;
use App\Service\AdminQuote;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminQuoteController
{
    public function __construct(private readonly AdminAuth $auth, private readonly AdminQuote $quotes)
    {
    }

    private function redirect(string $path, string $key = '', string $message = ''): Response
    {
        return new Response('', 302, ['Location' => $path.($key === '' ? '' : (str_contains($path, '?') ? '&' : '?').$key.'='.rawurlencode($message))]);
    }

    #[Route('/admin/quotes/convert.php', name: 'admin_quote_convert')]
    public function convert(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request, false, ['admin', 'superadmin', 'manager'])) return $blocked;
        if (!$request->isMethod('POST')) return $this->redirect('index.php');
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) return $this->redirect('index.php', 'err', 'Security token expired.');
        $id = (int) $request->request->get('quote_id', 0);
        if ($id <= 0) return $this->redirect('index.php', 'err', 'Invalid quote reference.');
        try {
            $result = $this->quotes->convert($id);
            $quoteNumber = $result['quote']['quote_number'];
            $message = $result['already']
                ? "Quote $quoteNumber was already converted to Order {$result['number']}."
                : "Quote $quoteNumber successfully converted to Order {$result['number']}.";
            return $this->redirect('../orders/view.php?id='.$result['id'], 'msg', $message);
        } catch (\Throwable $error) {
            return $this->redirect('view.php?id='.$id, 'err', $error->getMessage());
        }
    }

    #[Route('/admin/quotes/respond.php', name: 'admin_quote_respond')]
    public function respond(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        if (!$request->isMethod('POST')) return $this->redirect('index.php');
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) return $this->redirect('index.php', 'err', 'Security token expired.');
        if ($request->request->has('engineer_action')) {
            return $this->handleEngineerAction($request);
        }
        $id = (int) $request->request->get('quote_id', 0);
        if ($id <= 0) return $this->redirect('index.php', 'err', 'Invalid quote reference.');

        $session = $request->getSession();
        $isStaff = $this->auth->isStaff($session);
        $staffName = $isStaff ? $this->auth->staffUsername($session) : '';
        $quote = $this->quotes->findById($id);
        if (!$quote) {
            return $this->redirect('index.php', 'err', 'Quote request not found.');
        }

        if ($isStaff && strtolower(trim((string) ($quote['installation_head'] ?? ''))) !== strtolower($staffName)) {
            return new Response('Access Denied: You can only modify quotes assigned to your account.', 403);
        }

        $mode = strtolower(trim((string) $request->request->get('mode', 'update')));
        if ($mode === 'delete') {
            try {
                $result = $this->quotes->delete($id);
                return $this->redirect('index.php', 'msg', $result['message']);
            } catch (\Throwable $error) {
                return $this->redirect('index.php', 'err', $error->getMessage());
            }
        }

        $status = $mode === 'complete'
            ? 'completed'
            : trim((string) $request->request->get('status', (string) ($quote['status'] ?? 'confirmed')));
        $head = $isStaff
            ? $staffName
            : trim((string) $request->request->get('installation_head', (string) ($quote['installation_head'] ?? '')));
        $date = trim((string) $request->request->get('installation_date', (string) ($quote['installation_date'] ?? '')));
        $notes = $request->request->has('admin_notes')
            ? trim((string) $request->request->get('admin_notes', ''))
            : (string) ($quote['admin_notes'] ?? '');

        try {
            $result = $this->quotes->respond(
                $id,
                $status,
                $head,
                $date,
                $notes,
                $this->auth->user($session)['id'],
            );
            $returnTo = trim((string) $request->request->get('redirect_source', '')) === 'index' ? 'index.php' : 'view.php?id='.$id;
            return $this->redirect($returnTo, 'msg', $result['message']);
        } catch (\Throwable $error) {
            return $this->redirect('view.php?id='.$id, 'err', $error->getMessage());
        }
    }

    private function handleEngineerAction(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request, false, ['admin', 'superadmin', 'manager'])) {
            return $blocked;
        }
        $targetType = trim((string) $request->request->get('target_type', 'quote'));
        $targetId = (int) $request->request->get('target_id', $request->request->get('quote_id', 0));
        $action = trim((string) $request->request->get('engineer_action', 'update'));
        $engineer = trim((string) $request->request->get('installation_head', ''));
        $returnTo = $this->sanitizeRedirect((string) $request->request->get('redirect_to', ''), $targetType, $targetId);

        try {
            $result = $this->quotes->manageEngineer(
                $targetType,
                $targetId,
                $action,
                $engineer,
                $this->auth->user($request->getSession())['id'] ?? null,
            );
            return $this->redirect($returnTo, 'msg', $result['message']);
        } catch (\Throwable $error) {
            return $this->redirect($returnTo, 'err', $error->getMessage());
        }
    }

    private function sanitizeRedirect(string $redirectTo, string $targetType, int $targetId): string
    {
        $redirectTo = trim($redirectTo);
        if ($redirectTo !== '' && str_starts_with($redirectTo, '/admin/') && !str_contains($redirectTo, '//')) {
            $parts = parse_url($redirectTo);
            $path = $parts['path'] ?? '/admin/index.php';
            $query = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
                unset($query['msg'], $query['err']);
            }
            return $path.($query !== [] ? '?'.http_build_query($query) : '');
        }

        return match ($targetType) {
            'booking' => '/admin/schedule/bookings.php',
            'order' => $targetId > 0 ? '/admin/orders/view.php?id='.$targetId : '/admin/orders/index.php',
            default => $targetId > 0 ? 'view.php?id='.$targetId : 'index.php',
        };
    }
}
