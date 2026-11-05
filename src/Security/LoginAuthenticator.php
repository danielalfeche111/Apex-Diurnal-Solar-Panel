<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Service\Customer\CustomerAccount;
use App\Service\Customer\CustomerCart;
use App\Service\Customer\CustomerSession;
use App\View\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    public function __construct(
        private readonly CustomerAccount $accounts,
        private readonly CustomerCart $cart,
        private readonly CustomerSession $identity,
        private readonly PhpTemplateRenderer $templates,
    ) {
    }

    public function supports(Request $request): bool
    {
        if (!parent::supports($request)) {
            return false;
        }

        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        if ($email === '' || $password === '') {
            return false;
        }

        try {
            // The original sign-in page also serves admins. Keep their existing
            // credentials, priority and destination in its controller.
            $admin = $this->accounts->findAdmin($email);
            if ($admin !== null && password_verify($password, (string) $admin['password_hash'])) {
                return false;
            }
            $user = $this->accounts->findByEmail($email);
            if ($user === null) {
                return false;
            }
            $role = strtolower((string) ($user['role'] ?? ''));
            return !in_array($role, ['admin', 'superadmin', 'manager', 'staff'], true)
                && $admin === null
                && $user['email'] !== 'admin@gmail.com';
        } catch (\Throwable) {
            // Preserve the existing temporary-unavailable message from the controller.
            return false;
        }
    }

    public function authenticate(Request $request): Passport
    {
        $email = trim((string) $request->request->get('email', ''));
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials((string) $request->request->get('password', '')),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        /** @var User $user */
        $user = $token->getUser();
        $id = $user->getId();
        if ($id === null) {
            throw new \LogicException('Authenticated user must have an ID.');
        }

        $guestCart = $this->cart->sessionCart();
        $destination = $this->identity->consumeDestination();
        if (str_contains($destination, 'admin')) {
            $destination = '../index.php';
        }
        $response = new RedirectResponse($destination);
        $this->identity->loginCustomer($id, $user->getEmail(), $response, $request);
        $merged = $this->cart->merge($id, $guestCart);
        if ($merged['notices'] !== []) {
            $this->identity->session()->set('cart_notifications', $merged['notices']);
            foreach ($merged['notices'] as $notice) {
                if (!empty($notice['message'])) {
                    $this->identity->flash('warning', (string) $notice['message']);
                }
            }
        }

        return $response;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->templates->render('auth/login', [
            'email' => trim((string) $request->request->get('email', '')),
            'password' => '',
            'errors' => ['password' => 'Invalid email/username or password.'],
        ]);
    }

    protected function getLoginUrl(Request $request): string
    {
        return '/auth/login.php';
    }
}
