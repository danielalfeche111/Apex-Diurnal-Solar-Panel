<?php
declare(strict_types=1);

/**
 * End-to-end migration regression suite. Run with a disposable local database:
 * TEST_DATABASE_URL=mysql://solar:<private-app-password>@127.0.0.1:3309/migration_test?charset=utf8mb4
 * php tests/http_regression.php
 *
 * This runner starts its own server, installs the schema, and writes test data.
 * It refuses production database names and remote database hosts.
 */

final class Checks
{
    public int $passed = 0;
    public int $failed = 0;

    public function that(bool $condition, string $message): void
    {
        if (!$condition) {
            ++$this->failed;
            throw new RuntimeException($message);
        }
        ++$this->passed;
    }

    public function group(string $name, callable $run): void
    {
        $before = $this->failed;
        try {
            $run();
            echo "PASS $name\n";
        } catch (Throwable $error) {
            if ($this->failed === $before) {
                ++$this->failed;
            }
            echo "FAIL $name: {$error->getMessage()}\n";
        }
    }
}

final class Browser
{
    private CurlHandle $curl;

    public function __construct(private string $baseUrl)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => '',
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 30,
        ]);
    }

    /** @return array{status:int,body:string,headers:array<string,string>,json:mixed} */
    public function request(string $path, string $method = 'GET', ?array $data = null, bool $json = false): array
    {
        $headers = [];
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $json ? ['Content-Type: application/json'] : [],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return strlen($line);
            },
        ]);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data === null ? null : ($json ? json_encode($data, JSON_THROW_ON_ERROR) : http_build_query($data)));
        // POSTFIELDS can reset the HTTP verb, so always set it last.
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        $body = curl_exec($this->curl);
        if ($body === false) {
            throw new RuntimeException(curl_error($this->curl));
        }
        return [
            'status' => (int)curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE),
            'body' => $body,
            'headers' => $headers,
            'json' => json_decode($body, true),
        ];
    }
}

function status(Checks $checks, array $response, int $expected, string $label): void
{
    $checks->that($response['status'] === $expected, "$label: expected HTTP $expected, got {$response['status']}; " . substr(strip_tags($response['body']), 0, 200));
    $checks->that(!preg_match('/(?:Fatal error|Warning:|Uncaught (?:Error|Exception)|SQLSTATE\[)/i', $response['body']), "$label: PHP/database diagnostic leaked into response");
}

function jsonSuccess(Checks $checks, array $response, string $label): array
{
    status($checks, $response, 200, $label);
    $checks->that(str_contains($response['headers']['content-type'] ?? '', 'application/json'), "$label: JSON content type missing");
    $checks->that(is_array($response['json']) && ($response['json']['success'] ?? false) === true, "$label: JSON success missing: " . substr($response['body'], 0, 300));
    return $response['json'];
}

function csrf(Checks $checks, array $response): string
{
    preg_match('/name="csrf_token"\s+value="([^"]+)"/', $response['body'], $match);
    $checks->that(!empty($match[1]), 'Admin CSRF token missing from original form');
    return html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
}

function scalar(PDO $db, string $sql, array $params = []): mixed
{
    $statement = $db->prepare($sql);
    $statement->execute($params);
    return $statement->fetchColumn();
}

function runCommand(array $command, string $root, array $environment): string
{
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot launch PHP command.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException("Command failed: $output $errors");
    }
    return $output;
}

$root = dirname(__DIR__);
$testUrl = getenv('TEST_DATABASE_URL') ?: '';
$url = parse_url($testUrl);
$database = ltrim($url['path'] ?? '', '/');
if (!is_array($url) || !in_array($url['scheme'] ?? '', ['mysql', 'mariadb'], true)
    || !in_array($url['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
    || !preg_match('/^migration_test(?:_[a-zA-Z0-9_]+)?$/', $database)) {
    fwrite(STDERR, "Set TEST_DATABASE_URL to a local MySQL database named migration_test or migration_test_<suffix>. Production database names are refused.\n");
    exit(2);
}
if (!extension_loaded('curl') || !extension_loaded('pdo_mysql')) {
    fwrite(STDERR, "PHP extensions curl and pdo_mysql are required.\n");
    exit(2);
}

putenv('DATABASE_URL=' . $testUrl);
putenv('APP_ENV=test');
putenv('APP_DEBUG=0');
$_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $testUrl;
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';
$appEnvironment = array_merge(getenv() ?: [], [
    'DATABASE_URL' => $testUrl,
    'APP_ENV' => 'test',
    'APP_DEBUG' => '0',
]);
$checks = new Checks();
$server = null;
$runDir = $root . '/var/test-http-' . bin2hex(random_bytes(5));
mkdir($runDir . '/sessions', 0777, true);

try {
    // A previous migration build may have left a non-debug test container without Doctrine.
    runCommand([PHP_BINARY, 'bin/console', 'cache:clear', '--no-warmup', '--no-interaction'], $root, $appEnvironment);
    $databaseAdmin = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $url['host'], $url['port'] ?? 3306),
        rawurldecode($url['user'] ?? ''),
        rawurldecode($url['pass'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $databaseAdmin->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
    unset($databaseAdmin);
    $selected = runCommand([PHP_BINARY, 'bin/console', 'doctrine:query:sql', 'SELECT DATABASE() AS db'], $root, $appEnvironment);
    if (!str_contains($selected, $database)) {
        throw new RuntimeException('Symfony did not select the disposable test database.');
    }
    runCommand([PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'], $root, $appEnvironment);
    $db = new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $url['host'], $url['port'] ?? 3306, $database), rawurldecode($url['user'] ?? ''), rawurldecode($url['pass'] ?? ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) {
        throw new RuntimeException("Cannot allocate HTTP test port: $error");
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $baseUrl = 'http://' . $address;
    $router = is_file($root . '/public/router.php') ? 'public/router.php' : 'public/index.php';
    $server = proc_open([PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'display_errors=1', '-d', 'session.save_path=' . $runDir . '/sessions', '-S', $address, '-t', 'public', $router], [0 => ['pipe', 'r'], 1 => ['file', $runDir . '/server.log', 'a'], 2 => ['file', $runDir . '/server.log', 'a']], $pipes, $root, $appEnvironment);
    if (!is_resource($server)) {
        throw new RuntimeException('Cannot start HTTP test server.');
    }
    fclose($pipes[0]);
    $ready = false;
    for ($attempt = 0; $attempt < 80; ++$attempt) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.2);
        if ($probe) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) {
        throw new RuntimeException('Server did not start; inspect ' . $runDir . '/server.log');
    }

    $guest = new Browser($baseUrl);
    $customer = new Browser($baseUrl);
    $other = new Browser($baseUrl);
    $admin = new Browser($baseUrl);
    $suffix = bin2hex(random_bytes(6));
    $email = "migration-$suffix@example.test";
    $password = 'Migration-Test-123!';
    $orderId = $rfqOrderId = $quoteId = $bookingId = $userId = 0;
    $appointmentDay = (new DateTimeImmutable('today'))->modify('+90 days');
    while ((int)scalar($db, 'SELECT COUNT(*) FROM service_bookings WHERE preferred_date = ?', [$appointmentDay->format('Y-m-d')]) > 0) {
        $appointmentDay = $appointmentDay->modify('+1 day');
    }
    $date = $appointmentDay->format('Y-m-d');

    $checks->group('Public pages, directory aliases, and original links', function () use ($checks, $guest): void {
        foreach (['/', '/index.php', '/auth/', '/auth/index.php', '/auth/login.php', '/auth/register.php', '/services/', '/services/index.php', '/cart/', '/cart/index.php', '/search.php?term=solar'] as $path) {
            $response = $guest->request($path);
            $checks->that(in_array($response['status'], [200, 301, 302, 308], true), "$path is missing or failed ({$response['status']})");
            if ($response['status'] === 200) {
                $checks->that(str_contains(strtolower($response['body']), '<!doctype html>'), "$path did not render HTML");
            }
        }
        $response = $guest->request('/index.php');
        $checks->that(str_contains($response['body'], 'Apex Diurnal'), 'Original storefront brand missing');
        $checks->that(str_contains($response['body'], 'styles.css'), 'Original storefront stylesheet URL missing');
    });

    $checks->group('Every original frontend asset is served unchanged at its existing URL', function () use ($checks, $guest, $root): void {
        $manifest = json_decode((string)file_get_contents($root . '/config/preservation_manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['assets'] as $relative => $expectedHash) {
            $path = '/' . implode('/', array_map('rawurlencode', explode('/', $relative)));
            $response = $guest->request($path);
            $checks->that($response['status'] === 200, "Asset URL failed: $path ({$response['status']})");
            $checks->that(hash('sha256', $response['body']) === $expectedHash, "HTTP asset content changed: $path");
            if (str_ends_with($relative, '.css')) {
                $checks->that(str_contains($response['headers']['content-type'] ?? '', 'text/css'), "Stylesheet MIME type incorrect: $path");
            }
        }
    });

    $checks->group('Private implementation, secrets, SQL, and traversal stay inaccessible', function () use ($checks, $guest): void {
        foreach (['/config.php', '/auth.php', '/User.php', '/Cart.php', '/product_data.php', '/validation.php', '/sanitization.php', '/philippine_locations.php', '/cart/cart_functions.php', '/admin/auth.php', '/admin/inventory/sync.php', '/admin/includes/header.php', '/admin/includes/footer.php', '/admin/includes/sidebar.php', '/.env', '/.env.local', '/composer.json', '/composer.lock', '/database_setup.sql', '/database/schema.sql', '/legacy/index.php', '/src/Kernel.php', '/vendor/autoload.php', '/.git/config', '/var/log/prod.log', '/unknown.php', '/%2e%2e/config.php', '/legacy%2fconfig.php'] as $path) {
            status($checks, $guest->request($path), 404, $path);
        }
    });

    $checks->group('Guest guards, session ping, OPTIONS, and checkout destination', function () use ($checks, $guest): void {
        foreach (['/api/profile.php', '/api/user/orders/list.php', '/api/user/orders/view.php?id=1', '/admin/schedule/calendar.php'] as $path) {
            status($checks, $guest->request($path), 401, $path);
        }
        foreach (['/api/cart.php', '/api/profile.php', '/api/user/orders/list.php', '/api/user/orders/view.php'] as $path) {
            status($checks, $guest->request($path, 'OPTIONS'), 200, "OPTIONS $path");
        }
        foreach (['/account/index.php', '/account/settings.php', '/account/orders/index.php', '/account/orders/view.php', '/account/orders/action.php', '/cart/receipt.php', '/auth/change_password.php', '/admin/index.php', '/admin/orders/index.php', '/admin/orders/view.php', '/admin/orders/process.php', '/admin/orders/api.php', '/admin/inventory/index.php', '/admin/inventory/alerts.php', '/admin/inventory/edit.php', '/admin/quotes/index.php', '/admin/quotes/view.php', '/admin/quotes/respond.php', '/admin/quotes/convert.php', '/admin/schedule/index.php', '/admin/schedule/bookings.php', '/admin/schedule/appointments.php'] as $path) {
            $response = $guest->request($path);
            $checks->that(in_array($response['status'], [302, 401], true), "$path did not protect guest access ({$response['status']})");
        }
        $response = $guest->request('/cart/checkout.php?source=regression');
        status($checks, $response, 302, 'Guest checkout');
        $checks->that(str_starts_with($response['headers']['location'] ?? '', '/auth/register.php?redirect='), 'Checkout must redirect to public registration URL');
        $checks->that(str_contains(urldecode($response['headers']['location']), '/cart/checkout.php?source=regression'), 'Checkout return URL lost its query string');
        $response = $guest->request('/session.php?action=ping');
        status($checks, $response, 200, 'Session keepalive');
        $checks->that(($response['json']['status'] ?? '') === 'ok' && $response['json']['authenticated'] === false, 'Guest keepalive contract changed');
        $response = $guest->request('/cart/cart_action.php', 'POST', ['action' => 'add', 'product_id' => 'residential-arrays']);
        $checks->that(($response['json']['requiresAuth'] ?? false) === true, 'Storefront add-to-cart must keep registration prompt');
        status($checks, $guest->request('/services/commercial_consultation.php'), 405, 'Consultation rejects GET');
        status($checks, $guest->request('/services/check_availability.php'), 400, 'Availability validates date');
        $response = $guest->request('/services/commercial_consultation.php', 'POST', []);
        status($checks, $response, 422, 'Consultation validation');
        $checks->that(!empty($response['json']['errors']), 'Consultation field errors missing');
    });

    $checks->group('Registration, session cookies, profile API, and persistent cart', function () use ($checks, $customer, $db, $email, $password, &$userId): void {
        $response = $customer->request('/auth/register.php', 'POST', ['email' => 'invalid', 'password' => 'short', 'confirm_password' => 'different']);
        status($checks, $response, 200, 'Invalid registration');
        $checks->that(str_contains($response['body'], 'Passwords do not match'), 'Original registration validation missing');
        $response = $customer->request('/auth/register.php', 'POST', ['email' => $email, 'password' => $password, 'confirm_password' => $password]);
        status($checks, $response, 302, 'Customer registration');
        $userId = (int)scalar($db, 'SELECT id FROM users WHERE email = ?', [$email]);
        $checks->that($userId > 0, 'Registration did not persist a user');
        $checks->that(password_verify($password, (string)scalar($db, 'SELECT password_hash FROM users WHERE id = ?', [$userId])), 'Registration password hash changed');
        $profile = jsonSuccess($checks, $customer->request('/api/profile.php'), 'Profile after auto-login');
        $checks->that(($profile['data']['email'] ?? '') === $email, 'Auto-login did not preserve identity');
        $profile = jsonSuccess($checks, $customer->request('/api/profile.php', 'PATCH', ['full_name' => 'Migration Customer', 'phone' => '09171234567', 'street_address' => '123 Test Street', 'province' => 'Cavite', 'city' => 'Imus City', 'postal_code' => '4103'], true), 'Profile PATCH');
        $checks->that(($profile['data']['full_name'] ?? '') === 'Migration Customer', 'Profile update did not persist');
        $cart = jsonSuccess($checks, $customer->request('/api/cart.php', 'POST', ['product_id' => 'residential-arrays', 'quantity' => 2], true), 'Cart add JSON');
        $checks->that($cart['isAuthenticated'] === true && (int)$cart['itemCount'] === 2, 'Authenticated cart count incorrect');
        $checks->that((int)scalar($db, 'SELECT quantity FROM user_carts WHERE user_id = ? AND product_id = ?', [$userId, 'residential-arrays']) === 2, 'Cart was not stored in database');
        $cart = jsonSuccess($checks, $customer->request('/api/cart.php', 'POST', ['action' => 'update', 'product_id' => 'residential-arrays', 'quantity' => 1], true), 'Cart quantity update');
        $checks->that((int)$cart['itemCount'] === 1, 'Cart update count incorrect');
        jsonSuccess($checks, $customer->request('/api/cart.php', 'POST', ['product_id' => 'advanced-solar-inverter', 'quantity' => 1], true), 'Second cart product');
        $cart = jsonSuccess($checks, $customer->request('/api/cart.php', 'DELETE', ['product_id' => 'advanced-solar-inverter'], true), 'Cart DELETE JSON');
        $checks->that((int)$cart['itemCount'] === 1, 'Cart removal affected another item');
        status($checks, $customer->request('/auth/logout.php'), 302, 'Customer logout');
        status($checks, $customer->request('/api/profile.php'), 401, 'Logout invalidates session');
        status($checks, $customer->request('/auth/login.php', 'POST', ['email' => $email, 'password' => $password]), 302, 'Customer login');
        $cart = jsonSuccess($checks, $customer->request('/api/cart.php'), 'Rehydrated cart after login');
        $checks->that((int)$cart['itemCount'] === 1, 'Cart not preserved through logout/login');
        foreach (['/account/index.php', '/cart/index.php'] as $path) {
            status($checks, $customer->request($path), 302, $path);
        }
        foreach (['/account/settings.php', '/account/orders/index.php', '/cart/checkout.php'] as $path) {
            status($checks, $customer->request($path), 200, $path);
        }
    });

    $checks->group('Checkout stores order, line items, service booking, totals and receipt', function () use ($checks, $customer, $db, $email, &$userId, &$orderId): void {
        $response = $customer->request('/cart/checkout.php', 'POST', [
            'full_name' => 'Migration Customer', 'email' => $email, 'phone' => '09171234567',
            'street_address' => '123 Test Street', 'province' => 'Cavite', 'city' => 'Imus City', 'postal_code' => '4103',
            'property_type' => 'Residential', 'payment_method' => 'Cash on Delivery',
            'products' => ['Residential Arrays', 'Professional Installation Booking'],
            'quantities' => ['Residential Arrays' => 1, 'Professional Installation Booking' => 1],
        ]);
        status($checks, $response, 200, 'Checkout submission');
        $orderId = (int)scalar($db, 'SELECT id FROM orders WHERE user_id = ? AND property_type = ? ORDER BY id DESC LIMIT 1', [$userId, 'Residential']);
        $checks->that($orderId > 0, 'Checkout displayed success without persisting order');
        $checks->that((int)scalar($db, 'SELECT COUNT(*) FROM order_items WHERE order_id = ?', [$orderId]) === 2, 'Checkout line items missing');
        $checks->that(abs((float)scalar($db, 'SELECT total_amount FROM orders WHERE id = ?', [$orderId]) - 17841.60) < 0.01, 'Original 8% checkout total changed');
        $checks->that((int)scalar($db, 'SELECT COUNT(*) FROM service_bookings WHERE order_id = ?', [$orderId]) === 1, 'Installation booking did not synchronize');
        $cart = jsonSuccess($checks, $customer->request('/api/cart.php'), 'Cart after checkout');
        $checks->that((int)$cart['itemCount'] === 0, 'Checkout did not convert/clear active cart');
        $list = jsonSuccess($checks, $customer->request('/api/user/orders/list.php'), 'Order history');
        $checks->that(in_array($orderId, array_column($list['data']['orders'], 'id'), true), 'Order missing from customer history');
        $detail = jsonSuccess($checks, $customer->request('/api/user/orders/view.php?id=' . $orderId), 'Order details');
        $checks->that(count($detail['data']['order']['items']) === 2, 'Order detail line items incorrect');
        status($checks, $customer->request('/account/orders/view.php?id=' . $orderId), 200, 'Order detail page');
        $receipt = $customer->request('/cart/receipt.php?id=' . $orderId);
        status($checks, $receipt, 200, 'Digital receipt');
        $checks->that(str_contains($receipt['body'], $detail['data']['order']['order_number']), 'Receipt order reference missing');
    });

    $checks->group('Consultation creates booking and prevents duplicate appointment', function () use ($checks, $customer, $db, $email, $date, &$bookingId): void {
        $payload = ['lead_type' => 'consultation', 'company_name' => 'Migration Test Facility', 'consult_street' => '123 Test Street', 'consult_city' => 'Imus City', 'consult_province' => 'Cavite', 'consult_postal' => '4103', 'contact_person' => 'Migration Customer', 'corporate_email' => $email, 'phone_number' => '09171234567', 'best_call_time' => 'Morning', 'facility_type' => 'House / Residential', 'power_supply' => 'Single-Phase Supply', 'preferred_date' => $date, 'preferred_time_slot' => '8:00 AM - 10:00 AM', 'facility_size' => 100, 'current_monthly_bill' => 5000];
        $result = jsonSuccess($checks, $customer->request('/services/commercial_consultation.php', 'POST', $payload), 'Consultation submission');
        $checks->that(!empty($result['booking_reference']), 'Consultation did not synchronize to service bookings');
        $bookingId = (int)scalar($db, 'SELECT id FROM service_bookings WHERE booking_reference = ?', [$result['booking_reference']]);
        $checks->that($bookingId > 0, 'Consultation booking missing from database');
        $availability = jsonSuccess($checks, $customer->request('/services/check_availability.php?date=' . $date), 'Availability after booking');
        $checks->that(in_array('8:00 AM - 10:00 AM', $availability['booked_slots'], true), 'Reserved time still available');
        status($checks, $customer->request('/services/commercial_consultation.php', 'POST', $payload), 422, 'Double booking rejection');
    });

    $checks->group('Commercial RFQ creates quote and order, then customer confirms', function () use ($checks, $customer, $db, $email, &$quoteId, &$rfqOrderId): void {
        $result = jsonSuccess($checks, $customer->request('/services/commercial_consultation.php', 'POST', ['lead_type' => 'rfq', 'company_name' => 'Migration Test Corporation', 'business_registration_type' => 'Corporation (SEC Registered)', 'target_timeline' => 'Within 3 Months', 'consult_street' => '123 Test Street', 'consult_city' => 'Imus City', 'consult_province' => 'Cavite', 'consult_postal' => '4103', 'contact_person' => 'Migration Customer', 'corporate_email' => $email, 'phone_number' => '09171234567', 'best_call_time' => 'Morning', 'facility_size' => 1000, 'current_monthly_bill' => 50000]), 'RFQ submission');
        $checks->that(!empty($result['quote_number']) && !empty($result['order_id']), 'RFQ did not synchronize quote and order');
        $quoteId = (int)scalar($db, 'SELECT id FROM quote_requests WHERE quote_number = ?', [$result['quote_number']]);
        $rfqOrderId = (int)$result['order_id'];
        $checks->that($quoteId > 0, 'RFQ quote not persisted');
        status($checks, $customer->request('/account/orders/action.php', 'POST', ['order_id' => $rfqOrderId, 'action' => 'confirm']), 302, 'Customer commercial confirmation');
        $checks->that(scalar($db, 'SELECT status FROM orders WHERE id = ?', [$rfqOrderId]) === 'client_confirmed', 'Commercial order confirmation not stored');
        $checks->that(scalar($db, 'SELECT status FROM quote_requests WHERE id = ?', [$quoteId]) === 'client_confirmed', 'Commercial quote confirmation not synchronized');
    });

    $checks->group('Order ownership and changed password survive independent sessions', function () use ($checks, $other, $customer, $email, $password, $suffix, &$orderId): void {
        $guestCart = jsonSuccess($checks, $other->request('/api/cart.php', 'POST', ['product_id' => 'residential-arrays', 'quantity' => 1], true), 'Guest cart API');
        $checks->that($guestCart['isAuthenticated'] === false && (int)$guestCart['itemCount'] === 0, 'Guest API add must retain the existing sign-in requirement');
        // The original update endpoint can restore an existing guest session cart.
        $guestCart = jsonSuccess($checks, $other->request('/api/cart.php', 'POST', ['action' => 'update', 'product_id' => 'residential-arrays', 'quantity' => 1], true), 'Existing guest session cart update');
        $checks->that((int)$guestCart['itemCount'] === 1, 'Existing guest session cart update changed');
        status($checks, $other->request('/auth/register.php', 'POST', ['email' => "other-$suffix@example.test", 'password' => $password, 'confirm_password' => $password]), 302, 'Second customer registration');
        $merged = jsonSuccess($checks, $other->request('/api/cart.php'), 'Guest cart merge after registration');
        $checks->that($merged['isAuthenticated'] === true && (int)$merged['itemCount'] === 1, 'Guest cart did not merge into registered account');
        $empty = jsonSuccess($checks, $other->request('/api/cart.php', 'DELETE'), 'Clear entire cart');
        $checks->that((int)$empty['itemCount'] === 0, 'DELETE without product must clear cart');
        status($checks, $other->request('/api/user/orders/view.php?id=' . $orderId), 404, 'Another customer cannot read order');
        $result = jsonSuccess($checks, $other->request('/api/user/orders/list.php'), 'Other customer order history');
        $checks->that(count($result['data']['orders']) === 0, 'Order history leaked another account');
        $newPassword = $password . '-Changed';
        $response = $customer->request('/account/settings.php', 'POST', ['action' => 'change_password', 'current_password' => $password, 'new_password' => $newPassword, 'confirm_password' => $newPassword]);
        status($checks, $response, 200, 'Password settings submission');
        $checks->that(str_contains($response['body'], 'Your password has been successfully updated.'), 'Password settings success message missing');
        $customer->request('/auth/logout.php');
        status($checks, $customer->request('/auth/login.php', 'POST', ['email' => $email, 'password' => $password]), 200, 'Old password rejected');
        status($checks, $customer->request('/auth/login.php', 'POST', ['email' => $email, 'password' => $newPassword]), 302, 'New password accepted');
    });

    $checks->group('Admin login, dashboard, inventories, orders, quotes and schedules', function () use ($checks, $admin, $db, $suffix, $password, &$orderId, &$quoteId, &$bookingId): void {
        $username = 'migration_' . $suffix;
        $statement = $db->prepare('INSERT INTO admin_users (username, password_hash, email, role) VALUES (?, ?, ?, ?)');
        $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), "admin-$suffix@example.test", 'superadmin']);
        status($checks, $admin->request('/auth/login.php', 'POST', ['email' => $username, 'password' => $password]), 302, 'Admin unified login');
        foreach (['/admin/index.php', '/admin/orders/index.php', '/admin/orders/view.php?id=' . $orderId, '/admin/inventory/index.php', '/admin/inventory/alerts.php', '/admin/quotes/index.php', '/admin/quotes/view.php?id=' . $quoteId, '/admin/schedule/index.php', '/admin/schedule/bookings.php', '/admin/schedule/appointments.php?id=' . $bookingId] as $path) {
            status($checks, $admin->request($path), 200, $path);
        }
        $response = $admin->request('/admin/schedule/calendar.php');
        status($checks, $response, 200, 'Calendar JSON');
        $checks->that(is_array($response['json']), 'Calendar JSON did not decode');
        $response = $admin->request('/admin/orders/api.php?action=check_stock&order_id=' . $orderId);
        status($checks, $response, 200, 'Admin orders JSON');
        $checks->that(is_array($response['json']), 'Admin orders API did not return JSON');
    });

    $checks->group('Product and inventory CRUD updates storefront and checkout', function () use ($checks, $admin, $customer, $guest, $db, $suffix): void {
        $id = 'test-product-'.$suffix;
        $token = csrf($checks, $admin->request('/admin/inventory/index.php'));
        $input = [
            'mode' => 'create', 'product_id' => $id, 'name' => 'Test Solar Battery '.$suffix,
            'type' => 'physical', 'base_price' => '1399.50', 'description' => 'Test battery for catalog workflow.',
            'image' => 'assets/images/product-inverter.jpg', 'sku' => 'TEST-'.$suffix,
            'current_stock' => '6', 'min_stock_level' => '2', 'reorder_point' => '4', 'max_stock_level' => '20',
        ];
        status($checks, $admin->request('/admin/inventory/product.php', 'POST', $input), 302, 'Catalog CSRF rejection');
        $checks->that((int) scalar($db, 'SELECT COUNT(*) FROM products WHERE id = ?', [$id]) === 0, 'CSRF rejection wrote a product');
        $input['csrf_token'] = $token;
        $response = $admin->request('/admin/inventory/product.php', 'POST', $input);
        status($checks, $response, 302, 'Catalog create');
        $checks->that(str_contains($response['headers']['location'] ?? '', 'msg='), 'Catalog create failed: '.($response['headers']['location'] ?? ''));
        $checks->that((int) scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', [$id]) === 6, 'Opening stock missing');
        $checks->that((int) scalar($db, 'SELECT SUM(change_amount) FROM inventory_transactions WHERE product_id = ?', [$id]) === 6, 'Opening stock audit missing');
        $checks->that(str_contains($guest->request('/index.php')['body'], 'Test Solar Battery '.$suffix), 'New product missing from storefront');
        $cart = jsonSuccess($checks, $customer->request('/cart/cart_action.php', 'POST', ['action' => 'add', 'product_id' => $id, 'quantity' => 1]), 'Add new catalog product to cart');
        $checks->that((int) $cart['itemCount'] === 1, 'New catalog product did not enter cart');
        $checkoutResponse = $customer->request('/cart/checkout.php');
        status($checks, $checkoutResponse, 200, 'Checkout after catalog create');
        $checks->that(str_contains($checkoutResponse['body'], 'Test Solar Battery '.$suffix), 'New product missing from checkout');
        $input['mode'] = 'update';
        $input['name'] = 'Updated Solar Battery '.$suffix;
        $input['base_price'] = '1499.75';
        $input['description'] = 'Updated battery description.';
        $input['current_stock'] = '9';
        $input['expected_stock'] = '6';
        $response = $admin->request('/admin/inventory/product.php', 'POST', $input);
        status($checks, $response, 302, 'Catalog update');
        $checks->that(str_contains($response['headers']['location'] ?? '', 'msg='), 'Catalog update failed: '.($response['headers']['location'] ?? ''));
        $checks->that((float) scalar($db, 'SELECT base_price FROM products WHERE id = ?', [$id]) === 1499.75, 'Edited price missing from MySQL');
        $checks->that((int) scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', [$id]) === 9, 'Edited stock missing from MySQL');
        $checks->that((int) scalar($db, 'SELECT SUM(change_amount) FROM inventory_transactions WHERE product_id = ?', [$id]) === 9, 'Stock edit audit mismatch');
        $home = $guest->request('/index.php')['body'];
        $checks->that(str_contains($home, 'Updated Solar Battery '.$suffix) && str_contains($home, '1,499.75'), 'Storefront did not reflect product edit');
        $checkout = $customer->request('/cart/checkout.php')['body'];
        $checks->that(str_contains($checkout, 'Updated Solar Battery '.$suffix) && str_contains($checkout, '1,499.75'), 'Checkout did not reflect product edit');
        $response = $admin->request('/admin/inventory/product.php', 'POST', ['csrf_token' => $token, 'mode' => 'archive', 'product_id' => $id]);
        status($checks, $response, 302, 'Catalog archive');
        $checks->that((int) scalar($db, 'SELECT is_active FROM products WHERE id = ?', [$id]) === 0, 'Archive did not persist');
        $checks->that(!str_contains($guest->request('/index.php')['body'], 'Updated Solar Battery '.$suffix), 'Archived product remains on storefront');
        $response = $admin->request('/admin/inventory/product.php', 'POST', ['csrf_token' => $token, 'mode' => 'restore', 'product_id' => $id]);
        status($checks, $response, 302, 'Catalog restore');
        $checks->that(str_contains($guest->request('/index.php')['body'], 'Updated Solar Battery '.$suffix), 'Restored product missing from storefront');
    });

    $checks->group('Admin stock adjustment and order processing/restock persist audit trail', function () use ($checks, $admin, $db, &$orderId): void {
        $stock = (int)scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', ['residential-arrays']);
        $response = $admin->request('/admin/inventory/edit.php', 'POST', ['product_id' => 'residential-arrays', 'adjustment_type' => 'add', 'quantity' => 2]);
        status($checks, $response, 302, 'Missing CSRF blocked');
        $checks->that((int)scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', ['residential-arrays']) === $stock, 'Missing CSRF changed inventory');
        $token = csrf($checks, $admin->request('/admin/inventory/index.php'));
        $response = $admin->request('/admin/inventory/edit.php', 'POST', ['csrf_token' => $token, 'product_id' => 'residential-arrays', 'adjustment_type' => 'add', 'quantity' => 2, 'reason' => 'Migration regression fixture']);
        status($checks, $response, 302, 'Inventory adjustment');
        $checks->that(!str_contains($response['headers']['location'] ?? '', 'err='), 'Inventory adjustment returned an error');
        $checks->that((int)scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', ['residential-arrays']) === $stock + 2, 'Inventory adjustment not stored');
        foreach (['processing' => $stock + 1, 'cancelled' => $stock + 2] as $newStatus => $expectedStock) {
            $response = $admin->request('/admin/orders/process.php', 'POST', ['csrf_token' => $token, 'order_id' => $orderId, 'new_status' => $newStatus]);
            status($checks, $response, 302, 'Order status ' . $newStatus);
            $checks->that(!str_contains($response['headers']['location'] ?? '', 'err='), 'Order processing returned error: ' . ($response['headers']['location'] ?? ''));
            $checks->that(scalar($db, 'SELECT status FROM orders WHERE id = ?', [$orderId]) === $newStatus, 'Order status not stored');
            $checks->that((int)scalar($db, 'SELECT current_stock FROM inventory WHERE product_id = ?', ['residential-arrays']) === $expectedStock, 'Order stock deduction/restock incorrect');
        }
        $checks->that((int)scalar($db, 'SELECT COUNT(*) FROM order_status_history WHERE order_id = ?', [$orderId]) >= 2, 'Order status history trigger missing');
    });

    $checks->group('Admin quote assignment and consultation reschedule synchronize calendar', function () use ($checks, $admin, $db, $date, &$quoteId, &$rfqOrderId, &$bookingId): void {
        $token = csrf($checks, $admin->request('/admin/quotes/view.php?id=' . $quoteId));
        $response = $admin->request('/admin/quotes/respond.php', 'POST', ['csrf_token' => $token, 'quote_id' => $quoteId, 'status' => 'confirmed', 'installation_head' => 'Migration Engineer', 'installation_date' => $date, 'admin_notes' => 'Migration regression assignment']);
        status($checks, $response, 302, 'Quote assignment');
        $checks->that(!str_contains($response['headers']['location'] ?? '', 'err='), 'Quote assignment failed: ' . ($response['headers']['location'] ?? ''));
        $checks->that(scalar($db, 'SELECT installation_head FROM quote_requests WHERE id = ?', [$quoteId]) === 'Migration Engineer', 'Installation head not stored');
        $checks->that(scalar($db, 'SELECT installation_head FROM orders WHERE id = ?', [$rfqOrderId]) === 'Migration Engineer', 'Installation head not synchronized to customer order');
        $response = $admin->request('/admin/quotes/convert.php', 'POST', ['csrf_token' => $token, 'quote_id' => $quoteId]);
        status($checks, $response, 302, 'Existing quote conversion');
        $checks->that(str_contains($response['headers']['location'] ?? '', '../orders/view.php?id=' . $rfqOrderId), 'Quote conversion did not return existing order');
        $response = $admin->request('/admin/schedule/appointments.php', 'POST', ['csrf_token' => $token, 'booking_id' => $bookingId, 'status' => 'confirmed', 'assigned_technician' => 'Migration Technician', 'preferred_date' => $date, 'preferred_time_slot' => '1:00 PM - 3:00 PM', 'access_notes' => 'Migration regression reschedule']);
        status($checks, $response, 302, 'Consultation reschedule');
        $checks->that(scalar($db, 'SELECT assigned_technician FROM service_bookings WHERE id = ?', [$bookingId]) === 'Migration Technician', 'Technician assignment not stored');
        $response = $admin->request('/admin/schedule/calendar.php?start=' . $date . '&end=' . $date);
        status($checks, $response, 200, 'Calendar after assignment');
        $checks->that(str_contains($response['body'], 'Migration Technician'), 'Updated appointment missing from calendar');
        status($checks, $admin->request('/admin/logout.php'), 302, 'Admin logout');
        status($checks, $admin->request('/admin/schedule/calendar.php'), 401, 'Admin logout invalidates session');
    });
} catch (Throwable $error) {
    ++$checks->failed;
    fwrite(STDERR, "SETUP/EXECUTION FAILED: {$error->getMessage()}\n");
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
}

echo "\n{$checks->passed} assertions passed; {$checks->failed} failed.\n";
echo "HTTP server log: $runDir/server.log\n";
echo "Fixtures retained only in $database for inspection; no production database was changed.\n";
exit($checks->failed === 0 ? 0 : 1);
