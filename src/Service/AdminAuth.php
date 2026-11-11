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

    public function isAdmin(SessionInterface $session): bool
    {
        $admin = $this->user($session);
        return $admin !== null && in_array(strtolower($admin['role']), ['superadmin', 'admin', 'manager'], true);
    }

    public function isStaff(SessionInterface $session): bool
    {
        $admin = $this->user($session);
        return $admin !== null && strtolower($admin['role']) === 'staff';
    }

    public function staffUsername(SessionInterface $session): string
    {
        $admin = $this->user($session);
        return trim((string) ($admin['username'] ?? ''));
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
        if ($roles !== null) {
            $normalizedRole = strtolower($admin['role']);
            $allowedRoles = array_map('strtolower', $roles);
            if (!in_array('superadmin', $allowedRoles, true)) {
                $allowedRoles[] = 'superadmin';
            }
            if (!in_array($normalizedRole, $allowedRoles, true)) {
                if ($json) {
                    return new Response(json_encode(['success' => false, 'message' => 'Access Denied: You do not have permission to view this section.'], JSON_THROW_ON_ERROR), 403, ['Content-Type' => 'application/json; charset=UTF-8']);
                }
                return new Response('Access Denied: You do not have permission to view this section.', 403);
            }
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

    public function badgeCounts(?SessionInterface $session = null): array
    {
        if ($session !== null && $this->isStaff($session)) {
            $username = trim((string) ($this->user($session)['username'] ?? ''));
            return [
                'pending_orders' => 0,
                'low_stock' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point'),
                'pending_bookings' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE assigned_technician = :tech AND status IN ('pending', 'confirmed')", ['tech' => $username]),
                'new_quotes' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE installation_head = :head AND status IN ('new', 'pending', 'confirmed', 'in_progress')", ['head' => $username]),
            ];
        }

        return [
            'pending_orders' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
            'low_stock' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point'),
            'pending_bookings' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'"),
            'new_quotes' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
        ];
    }
}
