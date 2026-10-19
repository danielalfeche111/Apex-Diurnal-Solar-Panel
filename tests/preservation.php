<?php
declare(strict_types=1);

/** Source preservation and native route checks need no database connection. */
(static function (): void {
    $root = dirname(__DIR__);
    $manifest = json_decode((string)file_get_contents($root . '/config/preservation_manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $routes = require $root . '/config/original_urls.php';
    $errors = [];
    $assertions = 0;
    $check = static function (bool $condition, string $message) use (&$assertions, &$errors): void {
        ++$assertions;
        if (!$condition) {
            $errors[] = $message;
        }
    };
    $check(count($manifest['assets'] ?? []) > 40, 'Frontend asset baseline is incomplete.');
    $check(count($manifest['inline_html'] ?? []) >= 42, 'Original PHP template baseline is incomplete.');

    foreach ($manifest['assets'] as $relative => $expectedHash) {
        $file = $root . '/public/' . $relative;
        $check(is_file($file) && hash_file('sha256', $file) === $expectedHash, "Original asset changed or missing: $relative");
    }
    $templateCount = 0;
    foreach ($manifest['inline_html'] as $relative => $expectedHash) {
        if ($expectedHash === hash('sha256', '')) {
            continue;
        }
        ++$templateCount;
        $file = $root . '/templates/' . ($relative === 'index.php' ? 'home.php' : $relative);
        $html = '';
        if (is_file($file)) {
            foreach (token_get_all((string)file_get_contents($file)) as $token) {
                if (is_array($token) && $token[0] === T_INLINE_HTML) {
                    $html .= $token[1];
                }
            }
        }
        if ($relative === 'admin/inventory/index.php') {
            $check(str_contains($html, 'Stock Levels & Warehouse Catalog') && str_contains($html, 'id="catalog-modal"')
                && str_contains($html, 'id="stock-modal"'), 'Original inventory table or catalog editor is missing.');
            continue;
        }
        $check(is_file($file) && hash('sha256', $html) === $expectedHash, "Template HTML, inline styling, or inline script changed: $relative");
    }

    $expectedRoutes = [
        '/index.php', '/search.php', '/session.php',
        '/auth/index.php', '/auth/login.php', '/auth/register.php', '/auth/logout.php', '/auth/change_password.php',
        '/account/index.php', '/account/settings.php', '/account/orders/index.php', '/account/orders/view.php', '/account/orders/action.php',
        '/cart/index.php', '/cart/cart_action.php', '/cart/checkout.php', '/cart/receipt.php',
        '/services/index.php', '/services/check_availability.php', '/services/commercial_consultation.php',
        '/api/cart.php', '/api/profile.php', '/api/user/orders/list.php', '/api/user/orders/view.php',
        '/admin/index.php', '/admin/login.php', '/admin/logout.php',
        '/admin/orders/index.php', '/admin/orders/view.php', '/admin/orders/api.php', '/admin/orders/process.php',
        '/admin/inventory/index.php', '/admin/inventory/edit.php', '/admin/inventory/alerts.php',
        '/admin/quotes/index.php', '/admin/quotes/view.php', '/admin/quotes/respond.php', '/admin/quotes/convert.php',
        '/admin/schedule/index.php', '/admin/schedule/calendar.php', '/admin/schedule/bookings.php', '/admin/schedule/appointments.php',
    ];
    $actualRoutes = array_keys($routes);
    sort($expectedRoutes);
    sort($actualRoutes);
    $check($expectedRoutes === $actualRoutes, 'The public route allowlist no longer matches all 42 original endpoints.');
    require $root . '/config/bootstrap.php';
    $kernel = new \App\Kernel($_SERVER['APP_ENV'] ?? 'test', false);
    $kernel->boot();
    $nativePaths = [];
    foreach ($kernel->getContainer()->get('router')->getRouteCollection()->all() as $route) {
        $controller = (string) $route->getDefault('_controller');
        if (str_starts_with($controller, 'App\\Controller\\')) {
            $nativePaths[$route->getPath()] = true;
        }
    }
    foreach ($routes as $url => $relative) {
        $check($url === '/' . $relative && isset($nativePaths[$url]), "Original URL has no native Symfony controller: $url");
    }
    $kernel->shutdown();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS)) as $file) {
        if (strtolower($file->getExtension()) === 'php') {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root . '/public/')));
            $check(in_array($relative, ['index.php', 'router.php'], true), "Unexpected executable PHP file exposed in document root: $relative");
        }
    }
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, "FAIL preservation: $error\n");
        }
        exit(1);
    }
    echo sprintf("PASS preservation: %d checks; %d original assets, %d exact template HTML blocks (inventory extended for CRUD), and %d native public endpoints.\n", $assertions, count($manifest['assets']), $templateCount - 1, count($routes));
})();
