<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Controller\AdminController;
use App\Kernel;
use App\Service\AdminAuth;
use App\Service\AdminInventory;
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

$kernel->shutdown();

echo "ALL 6 ROLE SEPARATION TESTS PASSED!\n";
