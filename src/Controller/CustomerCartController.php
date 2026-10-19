<?php

declare(strict_types=1);

namespace App\Controller;

use App\Data\ProductCatalog as SiteProductCatalog;
use App\Service\Customer\CheckoutOrder;
use App\Service\Customer\CustomerAccount;
use App\Service\Customer\CustomerCart;
use App\Service\Customer\CustomerOrders;
use App\Service\Customer\CustomerSession;
use App\Service\Customer\Locations;
use App\View\PhpTemplateRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CustomerCartController
{
    public function __construct(
        private readonly CustomerCart $cart,
        private readonly CustomerSession $identity,
        private readonly CustomerAccount $accounts,
        private readonly CustomerOrders $orders,
        private readonly CheckoutOrder $checkoutOrders,
        private readonly SiteProductCatalog $siteCatalog,
        private readonly Locations $locations,
        private readonly PhpTemplateRenderer $templates,
    ) {
    }

    #[Route('/cart', name: 'cart_entry_slashless')]
    #[Route('/cart/', name: 'cart_entry_directory')]
    #[Route('/cart/index.php', name: 'cart_entry')]
    public function entry(): Response
    {
        return new RedirectResponse('/cart/checkout.php');
    }

    #[Route('/cart/cart_action.php', name: 'cart_action', methods: ['GET', 'POST'])]
    public function action(Request $request): Response
    {
        $data = $request->isMethod('POST') ? $request->request->all() : $request->query->all();
        $action = (string) ($data['action'] ?? '');
        $productId = (string) ($data['product_id'] ?? '');
        $quantity = (int) ($data['quantity'] ?? 1);
        $userId = $this->identity->userId();
        if ($action === 'add' && $userId === null) {
            $this->identity->flash('info', 'Please create an account or sign in to add products to your cart.');
            return new JsonResponse(['success' => false, 'requiresAuth' => true, 'redirect' => 'auth/register.php', 'message' => 'Please create an account to add products to your cart.']);
        }
        try {
            $summary = $this->cart->summary($userId, $userId !== null);
            $notices = $summary['notices'];
            if (in_array($action, ['add', 'update', 'remove', 'clear'], true)) {
                $summary = $this->cart->apply($action, $productId, $quantity, $userId);
            }
            $summary['notices'] = array_merge($notices, $summary['notices']);
            return new JsonResponse(['success' => true, 'isAuthenticated' => $userId !== null] + $summary);
        } catch (\Throwable $error) {
            return new JsonResponse(['success' => false, 'error' => $error->getMessage()], 500);
        }
    }

    #[Route('/api/cart.php', name: 'api_cart', methods: ['GET', 'POST', 'DELETE', 'OPTIONS'])]
    public function api(Request $request): Response
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*', 'Access-Control-Allow-Methods' => 'GET, POST, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
        ];
        if ($request->isMethod('OPTIONS')) return new Response('', 200, $headers);
        $userId = $this->identity->userId();
        try {
            $summary = $this->cart->summary($userId, $request->isMethod('GET') && $userId !== null);
            if ($request->isMethod('POST')) {
                $data = $this->input($request);
                if (($data['action'] ?? '') === 'merge' && isset($data['items']) && is_array($data['items'])) {
                    if ($userId !== null) {
                        $summary = $this->cart->merge($userId, $data['items']);
                    } else {
                        foreach ($data['items'] as $id => $quantity) {
                            $this->cart->apply('add', (string) $id, (int) $quantity, null);
                        }
                        $summary = $this->cart->summary();
                    }
                } else {
                    $action = (string) ($data['action'] ?? 'add');
                    if (!in_array($action, ['add', 'update', 'remove', 'clear'], true)) $action = 'add';
                    $summary = $this->cart->apply($action, (string) ($data['product_id'] ?? ''), (int) ($data['quantity'] ?? 1), $userId);
                }
            } elseif ($request->isMethod('DELETE')) {
                $data = $this->input($request);
                $id = (string) $request->query->get('product_id', $data['product_id'] ?? '');
                $summary = $this->cart->apply($id !== '' ? 'remove' : 'clear', $id, 0, $userId);
            }
            return new JsonResponse(['success' => true, 'isAuthenticated' => $userId !== null] + $summary, 200, $headers);
        } catch (\Throwable $error) {
            return new JsonResponse(['success' => false, 'error' => $error->getMessage()], 500, $headers);
        }
    }

    #[Route('/cart/checkout.php', name: 'cart_checkout', methods: ['GET', 'POST'])]
    public function checkout(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) {
            $this->identity->flash('info', 'Please create an account or sign in to complete your checkout.');
            $this->identity->rememberDestination($request->getRequestUri());
            return new RedirectResponse('/auth/register.php?redirect='.urlencode($request->getRequestUri()));
        }
        $prices = $this->checkoutOrders->prices();
        $details = [
            'full_name' => '', 'email' => '', 'phone' => '', 'street_address' => '', 'city' => '', 'province' => '',
            'postal_code' => '', 'property_type' => 'Residential', 'payment_method' => 'Credit Card',
            'card_name' => '', 'card_number' => '', 'card_expiry' => '', 'card_cvv' => '', 'masked_card' => '', 'notes' => '',
        ];
        $selected = [];
        $errors = [];
        $success = false;
        $prefilled = false;
        $orderNumber = '';
        $orderDate = '';
        if ($request->isMethod('POST')) {
            $posted = $request->request->all();
            foreach ($details as $field => $_) {
                if ($field !== 'masked_card' && isset($posted[$field]) && is_scalar($posted[$field])) {
                    $details[$field] = trim((string) $posted[$field]);
                }
            }
            $details['city'] = $this->canonicalCity($details['city']);
            $postedProducts = $posted['products'] ?? [];
            $quantities = $posted['quantities'] ?? [];
            if (is_array($postedProducts)) {
                foreach ($postedProducts as $name) {
                    if (is_string($name) && isset($prices[$name])) {
                        $selected[$name] = max(1, min(99, (int) ($quantities[$name] ?? 1)));
                    }
                }
            }
            $errors = $this->validateCheckout($details, $selected);
            if ($details['payment_method'] === 'Credit Card') {
                $digits = preg_replace('/\D/', '', $details['card_number']);
                if (strlen($digits) >= 4) {
                    $brand = match (substr($digits, 0, 1)) {'4' => 'Visa', '5' => 'Mastercard', '3' => 'Amex', default => 'Card'};
                    $details['masked_card'] = $brand.' ending in '.substr($digits, -4);
                }
            }
        } else {
            $this->cart->summary($userId, true);
            $productNames = array_column($this->siteCatalog->all(), 'title', 'id');
            foreach ($this->cart->sessionCart() as $id => $quantity) {
                if (isset($productNames[$id]) && (int) $quantity > 0) {
                    $selected[$productNames[$id]] = max(1, min(99, (int) $quantity));
                }
            }
            $user = $this->accounts->findById($userId);
            if ($user !== null) {
                foreach (['full_name', 'email', 'phone', 'street_address', 'city', 'province', 'postal_code'] as $field) {
                    if (!empty($user[$field])) {
                        $details[$field] = (string) $user[$field];
                        $prefilled = true;
                    }
                }
            }
        }
        $subtotal = 0.0;
        foreach ($selected as $name => $quantity) $subtotal += $prices[$name] * $quantity;
        $tax = $subtotal * .08;
        $total = $subtotal + $tax;
        if ($request->isMethod('POST') && $errors === []) {
            try {
                $orderNumber = $this->checkoutOrders->create($userId, $details, $selected, $subtotal, $tax, $total);
                $orderDate = date('F j, Y - g:i A');
                $success = true;
            } catch (\Throwable) {
                $errors['general'] = 'We could not save your order. Please try again.';
            }
        }
        $provinceMap = $this->locations->map();
        $productDetails = [
            'Residential Arrays' => ['description' => 'High-efficiency monocrystalline solar panels engineered for residential rooftops.', 'image' => '../assets/images/residential arrays.png', 'badge' => 'Bestseller'],
            'Advanced Solar Inverter' => ['description' => 'Pure sine-wave hybrid smart inverter with 98.4% peak grid conversion efficiency.', 'image' => '../assets/images/advance power inverter.png', 'badge' => 'Smart Tech'],
            'Professional Installation Booking' => ['description' => 'Certified master technician site assessment, 3D solar layout modeling & turnkey mounting.', 'image' => '../assets/images/product-booking.png', 'badge' => 'Full Service'],
        ];
        foreach ($this->siteCatalog->all() as $product) {
            if (!isset($productDetails[$product['title']])) {
                $productDetails[$product['title']] = [
                    'description' => $product['description'],
                    'image' => $product['image'] === '' ? '' : '../'.$product['image'],
                    'badge' => $product['badge'],
                ];
            }
        }
        $address = trim(implode(', ', array_filter([$details['street_address'], $details['city'], $details['province']])).($details['postal_code'] !== '' ? ' '.$details['postal_code'] : ''));
        return $this->templates->render('cart/checkout', $details + [
            'address' => $address, 'catalog_products' => $prices, 'product_details' => $productDetails,
            'allowed_payment_methods' => ['Credit Card' => 'Credit / Debit Card (Visa, Mastercard, Amex)', 'Cash on Delivery' => 'Cash on Delivery'],
            'allowed_provinces' => $this->locations->provinces(), 'provinceCityMap' => $provinceMap,
            'selected_items' => $selected, 'errors' => $errors, 'is_post' => $request->isMethod('POST'),
            'is_success' => $success, 'is_prefilled' => $prefilled, 'subtotal' => $subtotal,
            'tax_amount' => $tax, 'grand_total' => $total, 'order_id' => $orderNumber, 'order_date' => $orderDate,
            'safe' => static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'),
        ]);
    }

    #[Route('/cart/receipt.php', name: 'cart_receipt', methods: ['GET'])]
    public function receipt(Request $request): Response
    {
        $userId = $this->identity->userId();
        if ($userId === null) return new RedirectResponse('../auth/login.php?redirect='.urlencode($request->getRequestUri()));
        $orderId = (int) $request->query->get('id', 0);
        $detail = $this->orders->receipt($userId, $orderId > 0 ? $orderId : null, trim((string) $request->query->get('order', '')));
        $order = $detail['order'] ?? null;
        return $this->templates->render('cart/receipt', [
            'order' => $order, 'items' => $detail['items'] ?? [],
            'pageTitle' => $order ? 'Receipt '.htmlspecialchars($order['order_number'], ENT_QUOTES, 'UTF-8') : 'Receipt Not Found',
            'safe' => static fn (mixed $value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8'),
        ]);
    }

    private function input(Request $request): array
    {
        if (str_contains(strtolower((string) $request->headers->get('Content-Type')), 'application/json')) {
            $decoded = json_decode($request->getContent(), true);
            return is_array($decoded) ? $decoded : [];
        }
        $decoded = json_decode($request->getContent(), true);
        return is_array($decoded) ? $decoded : $request->request->all();
    }

    private function canonicalCity(string $city): string
    {
        return [
            'Binan City' => 'Biñan City', 'Dasmarinas City' => 'Dasmariñas City', 'Las Pinas' => 'Las Piñas',
            'Paranaque' => 'Parañaque', 'Munoz City' => 'Muñoz City', 'Science City of Munoz' => 'Muñoz City',
        ][$city] ?? $city;
    }

    private function validateCheckout(array $d, array $selected): array
    {
        $errors = [];
        foreach ([
            'full_name' => 'Full Name is required.', 'email' => 'Email address is required.', 'phone' => 'Phone number is required.',
            'street_address' => 'Street address is required (house #, street, barangay).', 'city' => 'Please select a city from the dropdown.',
            'province' => 'Please select a province from the dropdown.', 'postal_code' => 'Postal code is required.',
            'property_type' => 'Please select a Property Type (Residential).', 'payment_method' => 'Please select a preferred Payment Method.',
        ] as $field => $message) {
            if ($d[$field] === '') $errors[$field] = $message;
        }
        if ($d['full_name'] !== '' && mb_strlen($d['full_name']) < 3) $errors['full_name'] = 'Full Name must be at least 3 characters.';
        elseif ($d['full_name'] !== '' && !preg_match("/^[a-zA-Z\s\.\'\-]+$/", $d['full_name'])) $errors['full_name'] = 'Full Name contains invalid characters.';
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address (e.g., alex@example.com).';
        if ($d['phone'] !== '' && !preg_match('/^\d{11}$/', $d['phone'])) $errors['phone'] = 'Please enter a valid 11-digit mobile number (e.g., 09171234567).';
        if ($d['street_address'] !== '' && mb_strlen($d['street_address']) < 5) $errors['street_address'] = 'Please provide a complete street address (at least 5 characters).';
        if ($d['province'] !== '' && !in_array($d['province'], $this->locations->provinces(), true)) $errors['province'] = 'Invalid province selected. Please choose from the dropdown list.';
        if ($d['city'] !== '' && !$this->locations->cityMatchesProvince($d['city'], $d['province'])) $errors['city'] = $d['city'].' is not located in '.$d['province'].'. Please select a valid city or municipality.';
        if ($d['postal_code'] !== '' && !preg_match('/^\d{4}$/', $d['postal_code'])) $errors['postal_code'] = 'Please enter a valid 4-digit postal code (e.g., 1000).';
        if ($d['property_type'] !== '' && $d['property_type'] !== 'Residential') $errors['property_type'] = 'Invalid Property Type selected.';
        if ($d['payment_method'] !== '' && !in_array($d['payment_method'], ['Credit Card', 'Cash on Delivery'], true)) $errors['payment_method'] = 'Invalid Payment Method selected.';
        if ($d['payment_method'] === 'Credit Card') {
            if ($d['card_name'] === '') $errors['card_name'] = 'Cardholder Name is required.';
            elseif (mb_strlen($d['card_name']) < 3) $errors['card_name'] = 'Cardholder Name must be at least 3 characters.';
            elseif (!preg_match("/^[a-zA-Z\s\.\'\-]+$/", $d['card_name'])) $errors['card_name'] = 'Cardholder Name contains invalid characters.';
            $digits = preg_replace('/\D/', '', $d['card_number']);
            if ($digits === '') $errors['card_number'] = 'Card Number is required.';
            elseif (strlen($digits) < 15 || strlen($digits) > 16) $errors['card_number'] = 'Please enter a valid 15 or 16-digit card number.';
            if ($d['card_expiry'] === '') $errors['card_expiry'] = 'Card expiration date is required.';
            elseif (!preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $d['card_expiry'], $match)) $errors['card_expiry'] = 'Please enter a valid expiration date in MM/YY format.';
            elseif ((2000 + (int) $match[2]) * 12 + (int) $match[1] < (int) date('Y') * 12 + (int) date('n')) $errors['card_expiry'] = 'The card expiration date has already passed.';
            if ($d['card_cvv'] === '') $errors['card_cvv'] = 'CVV / security code is required.';
            elseif (!preg_match('/^\d{3,4}$/', $d['card_cvv'])) $errors['card_cvv'] = 'CVV must be 3 or 4 digits.';
        }
        if ($selected === []) $errors['products'] = 'You cannot checkout without an order! Please select at least one product or service to complete checkout.';
        return $errors;
    }
}
