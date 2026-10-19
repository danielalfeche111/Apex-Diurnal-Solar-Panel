<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Keep the existing 30-minute login timeout and 15-minute ID rotation. */
final class SessionLifecycleSubscriber implements EventSubscriberInterface
{
    private const IDLE_TIMEOUT = 1800;
    private const REGENERATE_INTERVAL = 900;

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 0]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$event->getRequest()->hasSession()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();
        $now = time();
        $fingerprint = hash('sha256', $request->headers->get('User-Agent', 'unknown_agent'));
        $storedFingerprint = $session->get('_session_fingerprint');
        if (is_string($storedFingerprint) && !hash_equals($storedFingerprint, $fingerprint)) {
            $this->clearIdentity($session);
            $session->getFlashBag()->add('error', 'Your session was reset due to a security verification check. Please log in again.');
        }
        $session->set('_session_fingerprint', $fingerprint);

        $authenticated = (int) $session->get('user_id', 0) > 0 || (int) $session->get('admin_id', 0) > 0;
        $lastActivity = (int) $session->get('_last_activity', 0);
        if ($authenticated && $lastActivity > 0 && $now - $lastActivity > self::IDLE_TIMEOUT) {
            $this->clearIdentity($session);
            $session->set('_session_expired', true);
            $session->getFlashBag()->add('warning', 'Your session has expired due to inactivity. Please log in again.');
            $session->migrate(true);
        }

        $created = (int) $session->get('_session_created_at', 0);
        if ($created === 0) {
            $session->set('_session_created_at', $now);
        } elseif ($now - $created > self::REGENERATE_INTERVAL) {
            $session->migrate(true);
            $session->set('_session_created_at', $now);
        }
        $session->set('_last_activity', $now);
    }

    private function clearIdentity(\Symfony\Component\HttpFoundation\Session\SessionInterface $session): void
    {
        foreach (['user_id', 'email', 'admin_id', 'admin_username', 'admin_email', 'admin_role', '_last_activity'] as $key) {
            $session->remove($key);
        }
    }
}
