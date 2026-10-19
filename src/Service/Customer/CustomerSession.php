<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CustomerSession
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function session(): SessionInterface
    {
        return $this->requests->getSession();
    }

    public function userId(): ?int
    {
        $id = (int) $this->session()->get('user_id', 0);
        return $id > 0 ? $id : null;
    }

    public function email(): ?string
    {
        return $this->userId() === null ? null : $this->session()->get('email');
    }

    public function adminId(): ?int
    {
        $id = (int) $this->session()->get('admin_id', 0);
        return $id > 0 ? $id : null;
    }

    public function loginCustomer(int $id, string $email, Response $response, Request $request): void
    {
        $session = $this->session();
        $session->migrate(true);
        $session->set('user_id', $id);
        $session->set('email', $email);
        $session->set('_last_activity', time());
        $response->headers->setCookie(Cookie::create('app_logged_in', '1', 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
    }

    public function loginAdmin(array $admin): void
    {
        $session = $this->session();
        $session->migrate(true);
        $session->set('admin_id', (int) $admin['id']);
        $session->set('admin_username', (string) ($admin['username'] ?? 'Admin'));
        $session->set('admin_email', (string) ($admin['email'] ?? ''));
        $session->set('admin_role', (string) ($admin['role'] ?? 'admin'));
        $session->set('_last_activity', time());
    }

    public function logout(Response $response, Request $request): void
    {
        $this->session()->invalidate();
        $response->headers->clearCookie('app_logged_in', '/', null, $request->isSecure(), false, Cookie::SAMESITE_LAX);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
    }

    public function rememberDestination(string $destination): void
    {
        if ($destination !== '') {
            $this->session()->set('redirect_after_login', $destination);
        }
    }

    public function consumeDestination(string $fallback = '../index.php', bool $admin = false): string
    {
        $session = $this->session();
        $key = $admin ? 'admin_redirect_after_login' : 'redirect_after_login';
        $destination = (string) $session->get($key, '');
        $session->remove($key);
        if ($admin) {
            $session->remove('redirect_after_login');
        }
        // A destination is always a local path; never turn login into an open redirect.
        if ($destination === '' || str_starts_with($destination, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $destination)) {
            return $fallback;
        }
        return $destination;
    }

    public function flash(string $type, string $message): void
    {
        $this->session()->getFlashBag()->add($type, $message);
    }

    public function loginRedirect(Request $request): Response
    {
        $this->rememberDestination($request->getRequestUri());
        return new \Symfony\Component\HttpFoundation\RedirectResponse('/auth/login.php');
    }
}
