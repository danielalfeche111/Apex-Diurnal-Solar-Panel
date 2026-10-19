<?php

declare(strict_types=1);

namespace App\View;

use Symfony\Component\HttpFoundation\RequestStack;

/** Small presentation helpers shared by the site's PHP view templates. */
final class ViewContext
{
    public function __construct(
        private readonly RequestStack $requests,
        private readonly string $projectDir,
    ) {
    }

    public function loggedIn(): bool
    {
        return (bool) $this->requests->getSession()->get('user_id');
    }

    public function currentUserId(): ?int
    {
        $id = $this->requests->getSession()->get('user_id');

        return $id === null ? null : (int) $id;
    }

    public function currentUserEmail(): ?string
    {
        return $this->requests->getSession()->get('email');
    }

    public function adminLoggedIn(): bool
    {
        return (bool) $this->requests->getSession()->get('admin_id');
    }

    public function adminUser(): ?array
    {
        if (!$this->adminLoggedIn()) {
            return null;
        }
        $session = $this->requests->getSession();

        return [
            'id' => (int) $session->get('admin_id'),
            'username' => $session->get('admin_username', 'Admin'),
            'email' => $session->get('admin_email', ''),
            'role' => $session->get('admin_role', 'staff'),
        ];
    }

    /** @return list<string> */
    public function flash(string $type): array
    {
        return $this->requests->getSession()->getFlashBag()->get($type);
    }

    public function assetExists(string $path): bool
    {
        return is_file($this->assetPath($path));
    }

    public function assetMtime(string $path): int|false
    {
        return @filemtime($this->assetPath($path));
    }

    public function navLink(string $label, string $href, string $class = '', string $extraAttr = ''): string
    {
        $css = $class ? ' class="'.$class.'"' : '';

        return '<a href="'.htmlspecialchars($href).'"'.$css.$extraAttr.'>'.htmlspecialchars($label).'</a>';
    }

    public function voidLink(string $inner, string $class = '', string $extraAttr = ''): string
    {
        $css = $class ? ' class="'.$class.'"' : '';

        return '<a href="javascript:void(0)" onclick="return false;"'.$css.$extraAttr.'>'.$inner.'</a>';
    }

    private function assetPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $templateRoot = str_replace('\\', '/', $this->projectDir.'/templates/');
        if (str_starts_with($path, $templateRoot)) {
            $path = substr($path, strlen($templateRoot));
        } else {
            $path = ltrim($path, '/');
        }

        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return $this->projectDir.'/public/'.implode('/', $parts);
    }
}
