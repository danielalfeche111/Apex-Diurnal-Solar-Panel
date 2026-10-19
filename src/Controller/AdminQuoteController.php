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
        if ($blocked = $this->auth->guard($request)) return $blocked;
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
        $id = (int) $request->request->get('quote_id', 0);
        if ($id <= 0) return $this->redirect('index.php', 'err', 'Invalid quote reference.');
        try {
            $result = $this->quotes->respond(
                $id,
                trim((string) $request->request->get('status', 'confirmed')),
                trim((string) $request->request->get('installation_head', '')),
                trim((string) $request->request->get('installation_date', '')),
                trim((string) $request->request->get('admin_notes', '')),
                $this->auth->user($request->getSession())['id'],
            );
            return $this->redirect('view.php?id='.$id, 'msg', $result['message']);
        } catch (\Throwable $error) {
            return $this->redirect('view.php?id='.$id, 'err', $error->getMessage());
        }
    }
}
