<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\Customer\CustomerAccount;
use App\Service\Customer\CustomerCart;
use App\Service\Customer\CustomerOrders;
use App\Service\Customer\CustomerSession;
use App\Service\Customer\Locations;
use App\Service\Customer\ProfileValidation;
use App\View\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CustomerAccountController
{
    public function __construct(
        private readonly CustomerAccount $accounts,
        private readonly CustomerCart $cart,
        private readonly CustomerOrders $orders,
        private readonly CustomerSession $identity,
        private readonly Locations $locations,
        private readonly ProfileValidation $validation,
        private readonly PhpTemplateRenderer $templates,
    ) {
    }

    #[Route('/account', name: 'account_entry_slashless')]
    #[Route('/account/', name: 'account_entry_directory')]
    #[Route('/account/index.php', name: 'account_entry')]
    public function entry(): Response
    {
        return new RedirectResponse('/account/settings.php');
    }

    #[Route('/account/settings.php', name: 'account_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) {
            return new RedirectResponse('../auth/login.php');
        }
        $user = $this->accounts->findById($userId);
        if ($user === null) {
            return new RedirectResponse('../auth/login.php');
        }
        $profileErrors = $passwordErrors = [];
        $profileSuccess = $passwordSuccess = '';
        $currentPassword = $newPassword = $confirmPassword = '';
        if ($request->isMethod('POST')) {
            $input = $request->request->all();
            $action = (string) ($input['action'] ?? '');
            if ($action === 'update_profile') {
                $profileErrors = $this->validation->validate($input, true);
                if ($profileErrors === []) {
                    try {
                        $this->accounts->updateProfile($userId, $input);
                        $profileSuccess = 'Your profile has been successfully saved!';
                        $user = $this->accounts->findById($userId) ?? $user;
                    } catch (\Throwable) {
                        $profileErrors['general'] = 'Failed to update profile. Please try again.';
                    }
                }
            } elseif ($action === 'change_password' || array_key_exists('current_password', $input)) {
                $currentPassword = (string) ($input['current_password'] ?? '');
                $newPassword = (string) ($input['new_password'] ?? '');
                $confirmPassword = (string) ($input['confirm_password'] ?? '');
                if ($currentPassword === '') $passwordErrors['current_password'] = 'Current password is required';
                if ($newPassword === '') $passwordErrors['new_password'] = 'New password is required';
                elseif (mb_strlen($newPassword) < 6) $passwordErrors['new_password'] = 'New password must be at least 6 characters';
                if ($confirmPassword === '') $passwordErrors['confirm_password'] = 'Please confirm your new password';
                elseif ($newPassword !== $confirmPassword) $passwordErrors['confirm_password'] = 'New passwords do not match';
                if ($passwordErrors === []) {
                    if (!password_verify($currentPassword, (string) $user['password_hash'])) {
                        $passwordErrors['current_password'] = 'Current password is incorrect';
                    } else {
                        try {
                            $this->accounts->updatePassword($userId, $newPassword);
                            $passwordSuccess = 'Your password has been successfully updated.';
                            $currentPassword = $newPassword = $confirmPassword = '';
                        } catch (\Throwable) {
                            $passwordErrors['general'] = 'Failed to update password. Please try again.';
                        }
                    }
                }
            }
        }
        $cartSummary = $this->cart->summary($userId, true);
        return $this->templates->render('account/settings', [
            'email' => $user['email'], 'profile' => $this->accounts->profile($user),
            'profile_errors' => $profileErrors, 'profile_success_message' => $profileSuccess,
            'password_errors' => $passwordErrors, 'password_success_message' => $passwordSuccess,
            'current_password' => $currentPassword, 'new_password' => $newPassword, 'confirm_password' => $confirmPassword,
            'consult_provinces' => $this->locations->provinces(), 'consultProvinceCityMap' => $this->locations->map(),
            'cartSummary' => $cartSummary, 'cartInitial' => $this->cartInitial($cartSummary),
        ]);
    }

    #[Route('/account/orders/index.php', name: 'account_orders', methods: ['GET'])]
    public function orderList(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) return new RedirectResponse('../../auth/login.php');
        $email = $this->identity->email() ?? '';
        $this->orders->claimUnassigned($userId, $email);
        $list = $this->orders->list($userId, $request->query->all());
        $cartSummary = $this->cart->summary($userId, true);
        return $this->templates->render('account/orders/index', [
            'userEmail' => $email, 'initialOrders' => $list['orders'], 'totalOrders' => $list['total'],
            'totalPages' => $list['pages'], 'userTotalAllOrders' => $list['all'], 'limit' => $list['limit'],
            'cartSummary' => $cartSummary, 'cartInitial' => $this->cartInitial($cartSummary),
        ]);
    }

    #[Route('/account/orders/view.php', name: 'account_order_view', methods: ['GET'])]
    public function orderView(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) return new RedirectResponse('../../auth/login.php');
        $detail = $this->orders->detail($userId, (int) $request->query->get('id', 0));
        $order = $detail['order'] ?? null;
        $status = $order['status'] ?? 'pending';
        $stages = ['pending', 'client_confirmed', 'confirmed', 'processing', 'shipped', 'delivered'];
        $cartSummary = $this->cart->summary($userId, true);
        return $this->templates->render('account/orders/view', [
            'userEmail' => $this->identity->email() ?? 'U', 'order' => $order,
            'items' => $detail['items'] ?? [], 'commercialSpecs' => $detail['quote'] ?? [],
            'isCommercial' => ($order['property_type'] ?? '') === 'Commercial',
            'standardStages' => $stages, 'stageIndex' => array_search($status, $stages, true),
            'orderDisplayStatus' => match ($status) {
                'confirmed' => 'Already Confirmed',
                'client_confirmed' => 'Confirmed by Client',
                'pending' => ($order['property_type'] ?? '') === 'Commercial' ? 'Pending Client Confirmation' : 'Pending',
                default => ucfirst($status),
            },
            'progressWidthPercent' => match ($status) {'client_confirmed' => 12.5, 'confirmed' => 25, 'processing' => 50, 'shipped', 'delivered' => 75, default => 0},
            'cartSummary' => $cartSummary, 'cartInitial' => $this->cartInitial($cartSummary),
        ]);
    }

    #[Route('/account/orders/action.php', name: 'account_order_action', methods: ['GET', 'POST'])]
    public function orderAction(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) return new RedirectResponse('../../auth/login.php');
        if (!$request->isMethod('POST')) return new RedirectResponse('index.php');
        $orderId = (int) $request->request->get('order_id', 0);
        $action = trim((string) $request->request->get('action', ''));
        if ($orderId <= 0 || !in_array($action, ['confirm', 'cancel'], true)) return new RedirectResponse('index.php');
        try {
            $message = $this->orders->customerAction($userId, $orderId, $action);
            return new RedirectResponse('view.php?id='.$orderId.'&msg='.urlencode($message));
        } catch (\DomainException $error) {
            if ($error->getMessage() === 'Order not found or access denied.') {
                return new RedirectResponse('index.php?err='.urlencode($error->getMessage()));
            }
            return new RedirectResponse('view.php?id='.$orderId.'&err='.urlencode($error->getMessage()));
        } catch (\Throwable $error) {
            return new RedirectResponse('view.php?id='.$orderId.'&err='.urlencode('An error occurred: '.$error->getMessage()));
        }
    }

    #[Route('/api/profile.php', name: 'api_profile', methods: ['GET', 'PUT', 'PATCH', 'POST', 'OPTIONS'])]
    public function apiProfile(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) return $this->json([], 200, 'GET, PUT, PATCH, POST, OPTIONS');
        $userId = $this->identity->userId();
        if ($userId === null) return $this->json(['success' => false, 'error' => 'Authentication required to access profile.'], 401);
        try {
            $user = $this->accounts->findById($userId);
            if ($user === null) return $this->json(['success' => false, 'error' => 'User profile not found.'], 404);
            if ($request->isMethod('GET')) return $this->json(['success' => true, 'data' => $this->accounts->profile($user)]);
            $input = $this->input($request);
            if ($input === []) return $this->json(['success' => false, 'error' => 'No profile data provided for update.'], 400);
            $errors = $this->validation->validate($input);
            if ($errors !== []) return $this->json(['success' => false, 'error' => implode(' ', $errors), 'errors' => $errors], 400);
            $this->accounts->updateProfile($userId, $input);
            return $this->json(['success' => true, 'message' => 'Profile updated successfully.', 'data' => $this->accounts->profile($this->accounts->findById($userId) ?? $user)]);
        } catch (\Throwable $error) {
            return $this->json(['success' => false, 'error' => 'Server error: '.$error->getMessage()], 500);
        }
    }

    #[Route('/api/user/orders/list.php', name: 'api_user_orders', methods: ['GET', 'OPTIONS'])]
    public function apiOrders(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) return $this->json([], 200, 'GET, OPTIONS');
        $userId = $this->identity->userId();
        if ($userId === null) return $this->json(['success' => false, 'error' => 'Authentication required to view order history.'], 401);
        try {
            $this->orders->claimUnassigned($userId, $this->identity->email() ?? '');
            $list = $this->orders->list($userId, $request->query->all(), (int) $request->query->get('limit', 10));
            $rows = array_map(static fn (array $row): array => [
                'id' => (int) $row['id'], 'order_number' => $row['order_number'], 'created_at' => $row['created_at'],
                'status' => $row['status'], 'property_type' => $row['property_type'] ?: 'Residential',
                'total_amount' => (float) $row['total_amount'], 'item_count' => (int) $row['item_count'],
            ], $list['orders']);
            return $this->json(['success' => true, 'data' => ['orders' => $rows, 'pagination' => ['total' => $list['total'], 'page' => $list['page'], 'limit' => $list['limit'], 'pages' => $list['pages']]]]);
        } catch (\Throwable $error) {
            return $this->json(['success' => false, 'error' => 'Server error retrieving orders: '.$error->getMessage()], 500);
        }
    }

    #[Route('/api/user/orders/view.php', name: 'api_user_order', methods: ['GET', 'OPTIONS'])]
    public function apiOrder(Request $request): Response
    {
        if ($request->isMethod('OPTIONS')) return $this->json([], 200, 'GET, OPTIONS');
        $userId = $this->identity->userId();
        if ($userId === null) return $this->json(['success' => false, 'error' => 'Authentication required to view order details.'], 401);
        $orderId = (int) $request->query->get('id', 0);
        if ($orderId <= 0) return $this->json(['success' => false, 'error' => 'Valid order ID is required.'], 400);
        try {
            $detail = $this->orders->detail($userId, $orderId);
            if ($detail === null) return $this->json(['success' => false, 'error' => 'Order not found or access denied.'], 404);
            $order = $detail['order'];
            $publicOrder = [];
            foreach (['id', 'order_number', 'customer_name', 'customer_email', 'customer_phone', 'property_type', 'payment_method', 'status', 'subtotal', 'tax_amount', 'total_amount', 'shipping_address', 'billing_address', 'notes', 'created_at'] as $field) {
                $publicOrder[$field] = $field === 'billing_address' ? ($order[$field] ?: $order['shipping_address']) : $order[$field];
            }
            $publicOrder['id'] = (int) $order['id'];
            foreach (['subtotal', 'tax_amount', 'total_amount'] as $field) $publicOrder[$field] = (float) $publicOrder[$field];
            $publicOrder['items'] = array_map(static fn (array $item): array => [
                'id' => (int) $item['id'], 'product_id' => $item['product_id'], 'product_name' => $item['product_name'],
                'quantity' => (int) $item['quantity'], 'unit_price' => (float) $item['unit_price'], 'total_price' => (float) $item['total_price'],
                'image' => $item['image'] ?: 'assets/images/residential arrays.png',
            ], $detail['items']);
            $history = array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'status' => $row['status'], 'changed_at' => $row['changed_at'], 'notes' => $row['notes']], $detail['history']);
            return $this->json(['success' => true, 'data' => ['order' => $publicOrder, 'status_history' => $history]]);
        } catch (\Throwable $error) {
            return $this->json(['success' => false, 'error' => 'Server error retrieving order: '.$error->getMessage()], 500);
        }
    }

    private function input(Request $request): array
    {
        $json = json_decode($request->getContent(), true);
        return is_array($json) ? $json : $request->request->all();
    }

    private function cartInitial(array $summary): array
    {
        return ['success' => true, 'isAuthenticated' => true] + $summary;
    }

    private function json(array $data, int $status = 200, string $methods = 'GET, PUT, PATCH, POST, OPTIONS'): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', $methods);
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With');
        return $response;
    }
}
