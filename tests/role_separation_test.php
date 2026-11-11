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
assert($dashboardStaffGeneral->getStatusCode() === 302, 'Staff visiting /admin/ should redirect to staff schedule workspace');
assert($dashboardStaffGeneral->headers->get('Location') === '/admin/schedule/index.php', 'Redirect target must be schedule workspace');

// 5. Test Staff login redirect
$loginStaffReq = Request::create('/admin/login.php');
$loginStaffReq->setSession($staffSession);
$loginStaffResp = $controller->login($loginStaffReq);
assert($loginStaffResp->getStatusCode() === 302, 'Already logged-in staff at login.php should redirect');
assert($loginStaffResp->headers->get('Location') === '/admin/schedule/index.php', 'Redirect target must be schedule workspace');

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

// Admin Update Staff/Engineer + Reset Password
$updateStaffReq = Request::create('/admin/staffs/action.php', 'POST', [
    'csrf_token' => $adminCsrf,
    'mode' => 'update',
    'staff_id' => $newStaffId,
    'username' => 'Engr. Test CRUD Updated (Senior Grid Specialist)',
    'email' => $testEmail,
    'role' => 'staff',
    'password' => 'ResetPass456!',
]);
$updateStaffReq->setSession($adminSession);
$updateStaffResp = $actionController->staffAction($updateStaffReq);
assert($updateStaffResp->getStatusCode() === 302, 'Admin update staff should redirect');
assert(
    $db->fetchOne('SELECT username FROM admin_users WHERE id = :id', ['id' => $newStaffId]) === 'Engr. Test CRUD Updated (Senior Grid Specialist)',
    'Staff username must be updated in admin_users'
);
$resetHash = (string) $db->fetchOne('SELECT password_hash FROM admin_users WHERE id = :id', ['id' => $newStaffId]);
assert(password_verify('ResetPass456!', $resetHash), 'Admin password reset must update password_hash');

// 9. Test Profile View & Staff-Only Own Password Change (#4)
$staffSession->set('admin_id', $newStaffId);
$staffSession->set('admin_username', 'Engr. Test CRUD Updated (Senior Grid Specialist)');
$staffSession->set('admin_email', $testEmail);
$staffSession->set('admin_role', 'staff');

$staffProfileReq = Request::create('/admin/profile/index.php');
$staffProfileReq->setSession($staffSession);
$stack->push($staffProfileReq);
$staffProfileResp = $controller->profile($staffProfileReq);
assert($staffProfileResp->getStatusCode() === 200, 'Staff must be able to view own profile');
assert(str_contains((string) $staffProfileResp->getContent(), 'Change Own Password'), 'Staff profile must display Change Own Password form');

$adminProfileReq = Request::create('/admin/profile/index.php');
$adminProfileReq->setSession($adminSession);
$stack->push($adminProfileReq);
$adminProfileResp = $controller->profile($adminProfileReq);
assert($adminProfileResp->getStatusCode() === 200, 'Admin must be able to view own profile');
assert(!str_contains((string) $adminProfileResp->getContent(), 'id="staff-change-password-form"'), 'Admin profile must not display staff self-service password form');

// Admin blocked from POST /admin/profile/action.php
$adminChangePassReq = Request::create('/admin/profile/action.php', 'POST', [
    'csrf_token' => $adminCsrf,
    'current_password' => 'ResetPass456!',
    'new_password' => 'AdminShouldFail123',
    'confirm_password' => 'AdminShouldFail123',
]);
$adminChangePassReq->setSession($adminSession);
$adminChangePassResp = $actionController->profileAction($adminChangePassReq);
assert($adminChangePassResp->getStatusCode() === 403, 'Admin must be blocked (403) from changing password via staff-only profile action');

// Staff succeeds in changing own password via POST /admin/profile/action.php
$staffCsrf = $auth->token($staffSession);
$staffChangePassReq = Request::create('/admin/profile/action.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'current_password' => 'ResetPass456!',
    'new_password' => 'StaffNewOwnPass789!',
    'confirm_password' => 'StaffNewOwnPass789!',
]);
$staffChangePassReq->setSession($staffSession);
$staffChangePassResp = $actionController->profileAction($staffChangePassReq);
assert($staffChangePassResp->getStatusCode() === 302, 'Staff change own password should redirect');
assert(str_contains((string) $staffChangePassResp->headers->get('Location'), 'msg='), 'Staff change own password should succeed');
$newOwnHash = (string) $db->fetchOne('SELECT password_hash FROM admin_users WHERE id = :id', ['id' => $newStaffId]);
assert(password_verify('StaffNewOwnPass789!', $newOwnHash), 'Staff own password change must persist hashed password');

// 10. Test Staff Orders Blocked (#4) & Inventory Read-Only (#5)
$staffOrdersReq = Request::create('/admin/orders/index.php');
$staffOrdersReq->setSession($staffSession);
$staffOrdersResp = $controller->orders($staffOrdersReq);
assert($staffOrdersResp->getStatusCode() === 403, 'Staff must be blocked (403) from /admin/orders/index.php');

$staffOrderProcReq = Request::create('/admin/orders/process.php', 'POST', ['csrf_token' => $staffCsrf, 'order_id' => 1, 'status' => 'confirmed']);
$staffOrderProcReq->setSession($staffSession);
$staffOrderProcResp = $actionController->orderProcess($staffOrderProcReq);
assert($staffOrderProcResp->getStatusCode() === 403, 'Staff must be blocked (403) from accepting/processing orders');

$staffQuoteConvertReq = Request::create('/admin/quotes/convert.php', 'POST', ['csrf_token' => $staffCsrf, 'quote_id' => 1]);
$staffQuoteConvertReq->setSession($staffSession);
$staffQuoteConvertResp = $quoteController->convert($staffQuoteConvertReq);
assert($staffQuoteConvertResp->getStatusCode() === 403, 'Staff must be blocked (403) from converting quotes into orders');

// Staff can view inventory, but is blocked from editing or adding products
$staffInvViewReq = Request::create('/admin/inventory/index.php');
$staffInvViewReq->setSession($staffSession);
$stack->push($staffInvViewReq);
$staffInvViewResp = $controller->inventory($staffInvViewReq);
assert($staffInvViewResp->getStatusCode() === 200, 'Staff must be able to view inventory');

$staffInvProdReq = Request::create('/admin/inventory/product.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'create',
    'product_id' => 'staff-blocked-prod',
    'name' => 'Blocked Product',
]);
$staffInvProdReq->setSession($staffSession);
$staffInvProdResp = $actionController->product($staffInvProdReq);
assert($staffInvProdResp->getStatusCode() === 403, 'Staff must be blocked (403) from adding/editing/deleting inventory products');

$staffInvEditReq = Request::create('/admin/inventory/edit.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'product_id' => 'residential-starter',
    'transaction_type' => 'restock',
    'quantity' => 5,
]);
$staffInvEditReq->setSession($staffSession);
$staffInvEditResp = $actionController->inventoryEdit($staffInvEditReq);
assert($staffInvEditResp->getStatusCode() === 403, 'Staff must be blocked (403) from adjusting inventory stock');

// 11. Test Staff Assigned Task Visibility & Full CRUD + Mark Done (#1, #2, #3)
$staffName = 'Engr. Test CRUD Updated (Senior Grid Specialist)';
$otherStaffName = 'Engr. Other Staff (Test Only)';
$db->executeStatement('DELETE FROM service_bookings WHERE assigned_technician IN (:s1, :s2)', ['s1' => $staffName, 's2' => $otherStaffName]);

// Insert a task assigned to OTHER staff
$db->insert('service_bookings', [
    'booking_reference' => 'SRV-OTHER-999',
    'customer_name' => 'Other Staff Client',
    'customer_email' => 'other@example.com',
    'customer_phone' => '09170000001',
    'service_type' => 'maintenance',
    'preferred_date' => date('Y-m-d'),
    'preferred_time_slot' => '8:00 AM - 10:00 AM',
    'status' => 'confirmed',
    'address' => 'Other Staff Site Address',
    'access_notes' => 'Do not touch',
    'assigned_technician' => $otherStaffName,
]);
$otherBookingId = (int) $db->lastInsertId();

// Staff creates their own assigned task (mode=create)
$staffCreateTaskReq = Request::create('/admin/schedule/appointments.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'create',
    'customer_name' => 'My Assigned Client',
    'customer_email' => 'myclient@example.com',
    'customer_phone' => '09171234567',
    'service_type' => 'installation',
    'preferred_date' => date('Y-m-d'),
    'preferred_time_slot' => '10:00 AM - 12:00 PM',
    'status' => 'confirmed',
    'address' => '123 Solar Street, Makati',
    'access_notes' => 'Initial task notes',
]);
$staffCreateTaskReq->setSession($staffSession);
$staffCreateTaskResp = $actionController->appointmentUpdate($staffCreateTaskReq);
assert($staffCreateTaskResp->getStatusCode() === 302, 'Staff create assigned task should redirect');
assert(str_contains((string) $staffCreateTaskResp->headers->get('Location'), 'msg='), 'Staff create assigned task should succeed');

$ownBookingId = (int) $db->fetchOne('SELECT id FROM service_bookings WHERE assigned_technician = :tech ORDER BY id DESC LIMIT 1', ['tech' => $staffName]);
assert($ownBookingId > 0, 'Staff created task must be assigned to the staff member');

// Staff views bookings list -> sees own task, does NOT see other staff task
$staffBookingsReq = Request::create('/admin/schedule/bookings.php');
$staffBookingsReq->setSession($staffSession);
$stack->push($staffBookingsReq);
$staffBookingsResp = $controller->bookings($staffBookingsReq);
assert($staffBookingsResp->getStatusCode() === 200, 'Staff bookings view must return 200');
assert(str_contains((string) $staffBookingsResp->getContent(), 'My Assigned Client'), 'Staff must see their own assigned task');
assert(!str_contains((string) $staffBookingsResp->getContent(), 'Other Staff Client'), 'Staff must NOT see tasks assigned to other staff');

// Staff updates their own assigned task (mode=update)
$staffUpdateTaskReq = Request::create('/admin/schedule/appointments.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'update',
    'booking_id' => $ownBookingId,
    'status' => 'confirmed',
    'preferred_date' => date('Y-m-d'),
    'preferred_time_slot' => '1:00 PM - 3:00 PM',
    'access_notes' => 'Updated site notes by assigned staff',
    'customer_name' => 'My Assigned Client Updated',
    'address' => '123 Solar Street, Makati',
]);
$staffUpdateTaskReq->setSession($staffSession);
$staffUpdateTaskResp = $actionController->appointmentUpdate($staffUpdateTaskReq);
assert($staffUpdateTaskResp->getStatusCode() === 302, 'Staff update own assigned task should redirect');
assert(
    $db->fetchOne('SELECT access_notes FROM service_bookings WHERE id = :id', ['id' => $ownBookingId]) === 'Updated site notes by assigned staff',
    'Staff update must persist access_notes'
);

// Staff marks their assigned task as Done (mode=complete)
$staffCompleteTaskReq = Request::create('/admin/schedule/appointments.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'complete',
    'booking_id' => $ownBookingId,
    'redirect_source' => 'bookings',
]);
$staffCompleteTaskReq->setSession($staffSession);
$staffCompleteTaskResp = $actionController->appointmentUpdate($staffCompleteTaskReq);
assert($staffCompleteTaskResp->getStatusCode() === 302, 'Staff mark task done should redirect');
assert(
    $db->fetchOne('SELECT status FROM service_bookings WHERE id = :id', ['id' => $ownBookingId]) === 'completed',
    'Staff must be able to mark their assigned task as completed'
);

// Staff tries to edit or delete OTHER staff's task -> BLOCKED (403)
$staffHackOtherReq = Request::create('/admin/schedule/appointments.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'complete',
    'booking_id' => $otherBookingId,
]);
$staffHackOtherReq->setSession($staffSession);
$staffHackOtherResp = $actionController->appointmentUpdate($staffHackOtherReq);
assert($staffHackOtherResp->getStatusCode() === 403, 'Staff must be blocked (403) from modifying another staff member\'s task');

// Staff deletes their own assigned task (mode=delete) -> ALLOWED
$staffDeleteTaskReq = Request::create('/admin/schedule/appointments.php', 'POST', [
    'csrf_token' => $staffCsrf,
    'mode' => 'delete',
    'booking_id' => $ownBookingId,
]);
$staffDeleteTaskReq->setSession($staffSession);
$staffDeleteTaskResp = $actionController->appointmentUpdate($staffDeleteTaskReq);
assert($staffDeleteTaskResp->getStatusCode() === 302, 'Staff delete own assigned task should redirect');
assert(
    (int) $db->fetchOne('SELECT COUNT(*) FROM service_bookings WHERE id = :id', ['id' => $ownBookingId]) === 0,
    'Staff own assigned task must be deleted'
);

// Clean up other staff test booking
$db->executeStatement('DELETE FROM service_bookings WHERE id = :id', ['id' => $otherBookingId]);

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
