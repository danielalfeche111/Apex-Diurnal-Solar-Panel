<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class AdminAuth
{
    public function __construct(private readonly Connection $database)
    {
    }

    public function user(SessionInterface $session): ?array
    {
        if (!$session->has('admin_id')) {
            return null;
        }

        return [
            'id' => (int) $session->get('admin_id'),
            'username' => (string) $session->get('admin_username', 'Admin'),
            'email' => (string) $session->get('admin_email', ''),
            'role' => (string) $session->get('admin_role', 'staff'),
        ];
    }

    public function guard(Request $request, bool $json = false, ?array $roles = null): ?Response
    {
        $session = $request->getSession();
        $admin = $this->user($session);
        if ($admin === null) {
            if ($json) {
                return new Response(json_encode(['success' => false, 'message' => 'Unauthorized'], JSON_THROW_ON_ERROR), 401, ['Content-Type' => 'application/json; charset=UTF-8']);
            }
            $session->set('admin_redirect_after_login', $request->getRequestUri());
            return new Response('', 302, ['Location' => '/auth/login.php']);
        }
        if ($roles !== null && $admin['role'] !== 'superadmin' && !in_array($admin['role'], $roles, true)) {
            return new Response('Access Denied: You do not have permission to view this section.', 403);
        }
        return null;
    }

    public function token(SessionInterface $session): string
    {
        $token = $session->get('admin_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set('admin_csrf_token', $token);
        }
        return $token;
    }

    public function verify(SessionInterface $session, ?string $token): bool
    {
        $expected = $session->get('admin_csrf_token');
        return is_string($expected) && is_string($token) && $token !== '' && hash_equals($expected, $token);
    }

    public function badgeCounts(): array
    {
        return [
            'pending_orders' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
            'low_stock' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point'),
            'pending_bookings' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'"),
            'new_quotes' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
        ];
    }
}
