<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Controller\AdminActionController;
use App\Controller\AdminController;
use App\Controller\AdminQuoteController;
use App\Kernel;
use App\Service\AdminAuth;
use App\Service\AdminInventory;
use App\Service\AdminQuote;
use App\Service\AdminStaff;
use App\View\PhpTemplateRenderer;
use App\View\ViewContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

echo "Running Role Separation Tests...\n";

require dirname(__DIR__).'/config/bootstrap.php';
$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer();

/** @var Connection $db */
$db = $container->get('doctrine.dbal.default_connection');

$auth = new AdminAuth($db);

$root = dirname(__DIR__);
$stack = new RequestStack();
$viewContext = new ViewContext($stack, $root);
$renderer = new PhpTemplateRenderer($viewContext, $root);

$inventory = new AdminInventory($db);
$controller = new AdminController($db, $auth, $inventory, $renderer, $viewContext);

$adminSession = new Session(new MockArraySessionStorage());
$adminSession->set('admin_id', 1);
$adminSession->set('admin_username', 'SuperAdmin');
$adminSession->set('admin_email', 'admin@example.com');
$adminSession->set('admin_role', 'superadmin');

$staffSession = new Session(new MockArraySessionStorage());
$staffSession->set('admin_id', 2);
$staffSession->set('admin_username', 'StaffUser');
$staffSession->set('admin_email', 'staff@example.com');
$staffSession->set('admin_role', 'staff');

// 1. Test AdminAuth role methods
assert($auth->isAdmin($adminSession) === true, 'AdminAuth::isAdmin should return true for superadmin');
assert($auth->isStaff($adminSession) === false, 'AdminAuth::isStaff should return false for superadmin');

assert($auth->isAdmin($staffSession) === false, 'AdminAuth::isAdmin should return false for staff');
assert($auth->isStaff($staffSession) === true, 'AdminAuth::isStaff should return true for staff');

// 2. Test guard role checks
$staffReq = Request::create('/admin/index.php');
$staffReq->setSession($staffSession);
$guardResponse = $auth->guard($staffReq, false, ['admin', 'superadmin']);
assert($guardResponse instanceof Response, 'Guard should block staff from admin-only roles');
assert($guardResponse->getStatusCode() === 403, 'Guard should return 403 for unauthorized staff');

$adminReq = Request::create('/admin/index.php');
$adminReq->setSession($adminSession);
$adminGuardResponse = $auth->guard($adminReq, false, ['admin', 'superadmin']);
assert($adminGuardResponse === null, 'Guard should allow superadmin/admin');

// 3. Test Staff direct bypass URL to /admin/index.php
$dashboardStaffDirect = $controller->dashboard($staffReq);
assert($dashboardStaffDirect->getStatusCode() === 403, 'Direct access by staff to /admin/index.php must return 403');
assert(str_contains((string) $dashboardStaffDirect->getContent(), 'Access Denied'), 'Response must explain access denied');

// 4. Test Staff entry via /admin or /admin/
$staffGeneralReq = Request::create('/admin/');
$staffGeneralReq->setSession($staffSession);
$dashboardStaffGeneral = $controller->dashboard($staffGeneralReq);
assert($dashboardStaffGeneral->getStatusCode() === 302, 'Staff visiting /admin/ should redirect to staff workspace');
assert($dashboardStaffGeneral->headers->get('Location') === '/admin/orders/index.php', 'Redirect target must be orders workspace');

// 5. Test Staff login redirect
$loginStaffReq = Request::create('/admin/login.php');
$loginStaffReq->setSession($staffSession);
$loginStaffResp = $controller->login($loginStaffReq);
assert($loginStaffResp->getStatusCode() === 302, 'Already logged-in staff at login.php should redirect');
assert($loginStaffResp->headers->get('Location') === '/admin/orders/index.php', 'Redirect target must be orders workspace');

// 6. Test Admin login redirect
$loginAdminReq = Request::create('/admin/login.php');
$loginAdminReq->setSession($adminSession);
$loginAdminResp = $controller->login($loginAdminReq);
assert($loginAdminResp->getStatusCode() === 302, 'Already logged-in admin at login.php should redirect to dashboard');
assert($loginAdminResp->headers->get('Location') === '/admin/index.php', 'Admin redirect target must be admin dashboard');

// 7. Test Engineer Management (Add, Update, Delete) & Staff Role Restriction
$quoteService = new AdminQuote($db);
$quoteController = new AdminQuoteController($auth, $quoteService);

$staffCsrf = $auth->token($staffSession);
$staffEngrReq = Request::create('/admin/quotes/respond.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'target_type' => 'quote',
    'target_id' => 1,
    'engineer_action' => 'add',
    'installation_head' => 'Engr. Test',
]);
$staffEngrReq->setSession($staffSession);
$staffEngrResp = $quoteController->respond($staffEngrReq);
assert($staffEngrResp->getStatusCode() === 403, 'Staff should be blocked (403) from admin engineer management action');

// Test database-backed Add, Update, Delete on a Quote project
$sampleQuoteId = (int) $db->fetchOne('SELECT id FROM quote_requests ORDER BY id DESC LIMIT 1');
if ($sampleQuoteId > 0) {
    $originalHead = $db->fetchOne('SELECT installation_head FROM quote_requests WHERE id = :id', ['id' => $sampleQuoteId]);
    $adminCsrf = $auth->token($adminSession);

    // Add Engineer
    $addReq = Request::create('/admin/quotes/respond.php', 'POST', [
        'csrf_token' => $adminCsrf,
        'target_type' => 'quote',
        'target_id' => $sampleQuoteId,
        'engineer_action' => 'add',
        'installation_head' => 'Engr. Test Alpha (Lead Solar Engineer)',
        'redirect_to' => '/admin/index.php',
    ]);
    $addReq->setSession($adminSession);
    $addResp = $quoteController->respond($addReq);
    assert($addResp->getStatusCode() === 302, 'Admin add engineer should redirect');
    assert(str_contains((string) $addResp->headers->get('Location'), 'msg='), 'Admin add engineer should return success msg');
    assert($db->fetchOne('SELECT installation_head FROM quote_requests WHERE id = :id', ['id' => $sampleQuoteId]) === 'Engr. Test Alpha (Lead Solar Engineer)', 'Engineer must be stored after add');

    // Update Engineer
    $updReq = Request::create('/admin/quotes/respond.php', 'POST', [
        'csrf_token' => $adminCsrf,
        'target_type' => 'quote',
        'target_id' => $sampleQuoteId,
        'engineer_action' => 'update',
        'installation_head' => 'Engr. Test Beta (Senior PV Specialist)',
        'redirect_to' => '/admin/index.php',
    ]);
    $updReq->setSession($adminSession);
    $updResp = $quoteController->respond($updReq);
    assert($updResp->getStatusCode() === 302, 'Admin update engineer should redirect');
    assert($db->fetchOne('SELECT installation_head FROM quote_requests WHERE id = :id', ['id' => $sampleQuoteId]) === 'Engr. Test Beta (Senior PV Specialist)', 'Engineer must be updated');

    // Delete Engineer
    $delReq = Request::create('/admin/quotes/respond.php', 'POST', [
        'csrf_token' => $adminCsrf,
        'target_type' => 'quote',
        'target_id' => $sampleQuoteId,
        'engineer_action' => 'delete',
        'installation_head' => '',
        'redirect_to' => '/admin/index.php',
    ]);
    $delReq->setSession($adminSession);
    $delResp = $quoteController->respond($delReq);
    assert($delResp->getStatusCode() === 302, 'Admin delete engineer should redirect');
    assert($db->fetchOne('SELECT installation_head FROM quote_requests WHERE id = :id', ['id' => $sampleQuoteId]) === null, 'Engineer must be null after delete');

    // Restore original value
    if ($originalHead !== null && $originalHead !== false && $originalHead !== '') {
        $quoteService->manageEngineer('quote', $sampleQuoteId, 'update', (string) $originalHead, 1);
    }
}

// 8. Test Staffs Module Role Restriction & CRUD (Create, Update, Delete)
$actionController = $container->get(AdminActionController::class);

$staffPageReq = Request::create('/admin/staffs/index.php');
$staffPageReq->setSession($staffSession);
$staffPageResp = $controller->staffs($staffPageReq);
assert($staffPageResp->getStatusCode() === 403, 'Staff role must be blocked (403) from /admin/staffs/index.php');

$adminPageReq = Request::create('/admin/staffs/index.php');
$adminPageReq->setSession($adminSession);
$stack->push($adminPageReq);
$adminPageResp = $controller->staffs($adminPageReq);
assert($adminPageResp->getStatusCode() === 200, 'Superadmin must be able to access /admin/staffs/index.php');
assert(str_contains((string) $adminPageResp->getContent(), 'Staffs &amp; Engineers Management'), 'Staffs page must render management header');

$staffActionDeniedReq = Request::create('/admin/staffs/action.php', 'POST', [
    'csrf_token' => $auth->token($staffSession),
    'mode' => 'create',
    'username' => 'Unauthorized Staff',
    'email' => 'unauth@apexdiurnal.com',
    'role' => 'staff',
]);
$staffActionDeniedReq->setSession($staffSession);
$staffActionDeniedResp = $actionController->staffAction($staffActionDeniedReq);
assert($staffActionDeniedResp->getStatusCode() === 403, 'Staff role must be blocked (403) from /admin/staffs/action.php');

// Admin Create Staff/Engineer
$adminCsrf = $auth->token($adminSession);
$testEmail = 'engr.test.crud@apexdiurnal.com';
$db->executeStatement('DELETE FROM admin_users WHERE email = :email', ['email' => $testEmail]);

$createStaffReq = Request::create('/admin/staffs/action.php', 'POST', [
    'csrf_token' => $adminCsrf,
    'mode' => 'create',
    'username' => 'Engr. Test CRUD (Grid Specialist)',
    'email' => $testEmail,
    'role' => 'staff',
    'password' => 'TestPass123!',
]);
$createStaffReq->setSession($adminSession);
$createStaffResp = $actionController->staffAction($createStaffReq);
assert($createStaffResp->getStatusCode() === 302, 'Admin create staff should redirect');
$newStaffId = (int) $db->fetchOne('SELECT id FROM admin_users WHERE email = :email', ['email' => $testEmail]);
assert($newStaffId > 0, 'Created staff member must exist in admin_users');

// Admin Update Staff/Engineer
$updateStaffReq = Request::create('/admin/staffs/action.php', 'POST', [
    'csrf_token' => $adminCsrf,
    'mode' => 'update',
    'staff_id' => $newStaffId,
    'username' => 'Engr. Test CRUD Updated (Senior Grid Specialist)',
    'email' => $testEmail,
    'role' => 'staff',
    'password' => '',
]);
$updateStaffReq->setSession($adminSession);
$updateStaffResp = $actionController->staffAction($updateStaffReq);
assert($updateStaffResp->getStatusCode() === 302, 'Admin update staff should redirect');
assert(
    $db->fetchOne('SELECT username FROM admin_users WHERE id = :id', ['id' => $newStaffId]) === 'Engr. Test CRUD Updated (Senior Grid Specialist)',
    'Staff username must be updated in admin_users'
);

// Admin Delete Staff/Engineer
$deleteStaffReq = Request::create('/admin/staffs/action.php', 'POST', [
    'csrf_token' => $adminCsrf,
    'mode' => 'delete',
    'staff_id' => $newStaffId,
]);
$deleteStaffReq->setSession($adminSession);
$deleteStaffResp = $actionController->staffAction($deleteStaffReq);
assert($deleteStaffResp->getStatusCode() === 302, 'Admin delete staff should redirect');
assert(
    (int) $db->fetchOne('SELECT COUNT(*) FROM admin_users WHERE id = :id', ['id' => $newStaffId]) === 0,
    'Staff member must be removed from admin_users after delete'
);

$kernel->shutdown();

echo "ALL ROLE SEPARATION, STAFFS MODULE & ENGINEER ASSIGNMENT TESTS PASSED!\n";
