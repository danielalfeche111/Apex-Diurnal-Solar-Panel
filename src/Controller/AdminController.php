<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAuth;
use App\Service\AdminInventory;
use App\View\PhpTemplateRenderer;
use App\View\ViewContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController
{
    public function __construct(
        private readonly Connection $database,
        private readonly AdminAuth $auth,
        private readonly AdminInventory $inventory,
        private readonly PhpTemplateRenderer $renderer,
        private readonly ViewContext $viewContext,
    ) {
    }

    private function view(Request $request, string $template, array $variables, string $title, string $nav, string $head = '', string $scripts = ''): Response
    {
        return $this->renderer->render('admin/'.$template, $variables + [
            'page_title' => $title,
            'active_nav' => $nav,
            'admin_base' => str_contains($template, '/') ? '../' : './',
            'admin' => $this->auth->user($request->getSession()),
            'badge_counts' => $this->auth->badgeCounts(),
            'csrf_token' => $this->auth->token($request->getSession()),
            'extra_head' => $head,
            'extra_scripts' => $scripts,
            'msg' => (string) $request->query->get('msg', ''),
            'err' => (string) $request->query->get('err', ''),
        ]);
    }

    private function redirect(string $url): Response
    {
        return new Response('', 302, ['Location' => $url]);
    }

    #[Route('/admin', name: 'admin_root')]
    #[Route('/admin/', name: 'admin_directory')]
    #[Route('/admin/index.php', name: 'admin_dashboard')]
    public function dashboard(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $metrics = [
            'total_revenue' => (float) $this->database->fetchOne("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('cancelled', 'refunded')"),
            'pending_orders' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
            'low_stock_items' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM inventory WHERE current_stock <= reorder_point'),
            'pending_bookings' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'"),
            'new_quotes' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
            'recent_orders' => $this->database->fetchAllAssociative('SELECT o.id, o.order_number, o.customer_name, o.total_amount, o.status, o.created_at, COUNT(oi.id) AS item_count FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT 6'),
            'upcoming_bookings' => $this->database->fetchAllAssociative("SELECT * FROM service_bookings WHERE preferred_date >= CURDATE() AND status != 'cancelled' ORDER BY preferred_date ASC, preferred_time_slot ASC LIMIT 5"),
            'recent_quotes' => $this->database->fetchAllAssociative("SELECT * FROM quote_requests ORDER BY (status = 'new') DESC, created_at DESC LIMIT 5"),
            'inventory_health' => $this->database->fetchAllAssociative('SELECT p.name, p.image, i.sku, i.current_stock, i.min_stock_level, i.max_stock_level, i.reorder_point FROM inventory i JOIN products p ON i.product_id = p.id ORDER BY (i.current_stock <= i.reorder_point) DESC, i.current_stock ASC'),
        ];
        return $this->view($request, 'index', $metrics, 'Dashboard Overview', 'dashboard');
    }

    #[Route('/admin/login.php', name: 'admin_login')]
    public function login(Request $request): Response
    {
        if ($this->auth->user($request->getSession())) return $this->redirect('/admin/index.php');
        $redirect = (string) $request->query->get('redirect', $request->getSession()->get('admin_redirect_after_login', '/admin/index.php'));
        $request->getSession()->set('admin_redirect_after_login', $redirect);
        return $this->redirect('/auth/login.php?redirect='.rawurlencode($redirect));
    }

    #[Route('/admin/logout.php', name: 'admin_logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        foreach (['admin_id', 'admin_username', 'admin_email', 'admin_role', 'admin_redirect_after_login', 'admin_csrf_token'] as $key) {
            $session->remove($key);
        }
        $session->migrate(true);
        return $this->redirect('/auth/login.php');
    }

    #[Route('/admin/orders', name: 'admin_orders_directory')]
    #[Route('/admin/orders/', name: 'admin_orders_slash')]
    #[Route('/admin/orders/index.php', name: 'admin_orders')]
    public function orders(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $status = trim((string) $request->query->get('status', 'all'));
        $search = trim((string) $request->query->get('search', ''));
        $where = []; $parameters = [];
        if (in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'], true)) {
            $where[] = 'o.status = :status'; $parameters['status'] = $status;
        }
        if ($search !== '') {
            $where[] = '(o.order_number LIKE :s1 OR o.customer_name LIKE :s2 OR o.customer_email LIKE :s3 OR o.shipping_address LIKE :s4)';
            foreach (['s1', 's2', 's3', 's4'] as $key) $parameters[$key] = '%'.$search.'%';
        }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $data = [
            'status_filter' => $status, 'search' => $search,
            'orders' => $this->database->fetchAllAssociative("SELECT o.*, COUNT(oi.id) AS item_count, GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') AS item_summary FROM orders o LEFT JOIN order_items oi ON o.id = oi.order_id $whereSql GROUP BY o.id ORDER BY o.created_at DESC", $parameters),
            'kpi_total_orders' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM orders'),
            'kpi_pending' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM orders WHERE status = 'pending'"),
            'kpi_processing' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM orders WHERE status IN ('processing', 'shipped')"),
            'kpi_revenue' => (float) $this->database->fetchOne("SELECT SUM(total_amount) FROM orders WHERE status NOT IN ('cancelled', 'refunded')"),
        ];
        $cssVersion = $this->viewContext->assetExists('admin/orders/orders.css')
            ? $this->viewContext->assetMtime('admin/orders/orders.css')
            : time();
        return $this->view($request, 'orders/index', $data, 'Order Management', 'orders', '<link rel="stylesheet" href="orders.css?v='.$cssVersion.'">', '<script src="orders.js" defer></script>');
    }

    #[Route('/admin/orders/view.php', name: 'admin_order_view')]
    public function orderView(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $id = (int) $request->query->get('id', 0);
        $order = $this->database->fetchAssociative('SELECT * FROM orders WHERE id = :id', ['id' => $id]);
        if (!$order) return $this->redirect('index.php?err='.rawurlencode('Order not found.'));
        $status = $order['status'];
        $badgeClass = match ($status) {
            'processing' => 'badge-processing', 'shipped' => 'badge-shipped',
            'delivered' => 'badge-delivered', 'cancelled', 'refunded' => 'badge-cancelled',
            default => 'badge-pending',
        };
        return $this->view($request, 'orders/view', [
            'order' => $order, 'status' => $status, 'badgeClass' => $badgeClass,
            'items' => $this->database->fetchAllAssociative('SELECT oi.*, p.image, p.type AS product_type, COALESCE(i.current_stock, 0) AS current_stock FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id LEFT JOIN inventory i ON p.id = i.product_id WHERE oi.order_id = :id', ['id' => $id]),
            'linkedBooking' => $this->database->fetchAssociative('SELECT * FROM service_bookings WHERE order_id = :id LIMIT 1', ['id' => $id]),
            'stockCheck' => $this->inventory->availability($id),
        ], 'Order '.$order['order_number'], 'orders', '<link rel="stylesheet" href="orders.css">', '<script src="orders.js" defer></script>');
    }

    #[Route('/admin/inventory', name: 'admin_inventory_directory')]
    #[Route('/admin/inventory/', name: 'admin_inventory_slash')]
    #[Route('/admin/inventory/index.php', name: 'admin_inventory')]
    public function inventory(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $items = $this->inventory->items();
        $units = 0; $low = 0; $out = 0; $physical = 0;
        foreach ($items as $item) {
            if ($item['product_type'] !== 'physical') continue;
            ++$physical; $stock = (int) $item['current_stock']; $units += $stock;
            if ($stock === 0) ++$out;
            elseif ($stock <= (int) $item['reorder_point']) ++$low;
        }
        return $this->view($request, 'inventory/index', [
            'items' => $items, 'totalPhysicalUnits' => $units,
            'lowStockCount' => $low, 'outOfStockCount' => $out, 'physicalItemCount' => $physical,
        ], 'Inventory Control', 'inventory', '<link rel="stylesheet" href="inventory.css">', '<script src="inventory.js" defer></script><script src="catalog.js" defer></script>');
    }

    #[Route('/admin/inventory/alerts.php', name: 'admin_inventory_alerts')]
    public function inventoryAlerts(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        return $this->view($request, 'inventory/alerts', [
            'alerts' => $this->inventory->alerts(),
            'transactions' => $this->database->fetchAllAssociative("SELECT t.*, p.name AS product_name, COALESCE(u.username, 'System') AS admin_name FROM inventory_transactions t JOIN products p ON t.product_id = p.id LEFT JOIN admin_users u ON t.created_by = u.id ORDER BY t.created_at DESC LIMIT 50"),
        ], 'Stock Alerts & Audit Log', 'inventory');
    }

    #[Route('/admin/schedule', name: 'admin_schedule_directory')]
    #[Route('/admin/schedule/', name: 'admin_schedule_slash')]
    #[Route('/admin/schedule/index.php', name: 'admin_schedule')]
    public function schedule(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        return $this->view($request, 'schedule/index', [
            'kpi_total' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM service_bookings'),
            'kpi_pending' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'"),
            'kpi_confirmed' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'confirmed'"),
            'kpi_completed' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM service_bookings WHERE status = 'completed'"),
        ], 'Service Scheduling Calendar', 'schedule', '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css"><link rel="stylesheet" href="schedule.css">', '<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script><script src="schedule.js" defer></script>');
    }

    #[Route('/admin/schedule/bookings.php', name: 'admin_schedule_bookings')]
    public function bookings(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $status = trim((string) $request->query->get('status', 'all'));
        $service = trim((string) $request->query->get('service_type', 'all'));
        $search = trim((string) $request->query->get('search', ''));
        $where = []; $parameters = [];
        if (in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $where[] = 'status = :st'; $parameters['st'] = $status;
        }
        if (in_array($service, ['consultation', 'installation', 'maintenance'], true)) {
            $where[] = 'service_type = :stype'; $parameters['stype'] = $service;
        }
        if ($search !== '') {
            $where[] = '(booking_reference LIKE :s1 OR customer_name LIKE :s2 OR customer_email LIKE :s3 OR assigned_technician LIKE :s4)';
            foreach (['s1', 's2', 's3', 's4'] as $key) $parameters[$key] = '%'.$search.'%';
        }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        return $this->view($request, 'schedule/bookings', [
            'bookings' => $this->database->fetchAllAssociative("SELECT * FROM service_bookings $whereSql ORDER BY preferred_date DESC, created_at DESC", $parameters),
            'status_filter' => $status, 'service_filter' => $service, 'search' => $search,
        ], 'Service Bookings List', 'schedule');
    }

    #[Route('/admin/schedule/appointments.php', name: 'admin_schedule_appointments', methods: ['GET'])]
    public function appointment(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $id = (int) $request->query->get('id', 0);
        $booking = $this->database->fetchAssociative('SELECT * FROM service_bookings WHERE id = :id', ['id' => $id]);
        if (!$booking) return $this->redirect('bookings.php?err='.rawurlencode('Booking not found.'));
        return $this->view($request, 'schedule/appointments', [
            'booking' => $booking,
            'linkedOrder' => !empty($booking['order_id']) ? $this->database->fetchAssociative('SELECT id, order_number, total_amount, status FROM orders WHERE id = :id', ['id' => $booking['order_id']]) : null,
        ], 'Appointment '.$booking['booking_reference'], 'schedule');
    }

    #[Route('/admin/quotes', name: 'admin_quotes_directory')]
    #[Route('/admin/quotes/', name: 'admin_quotes_slash')]
    #[Route('/admin/quotes/index.php', name: 'admin_quotes')]
    public function quotes(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $status = trim((string) $request->query->get('status', 'all'));
        $search = trim((string) $request->query->get('search', ''));
        $where = []; $parameters = [];
        $map = ['new' => "status IN ('new', 'pending')", 'confirmed' => "status IN ('confirmed', 'accepted')", 'in_progress' => "status = 'in_progress'", 'completed' => "status = 'completed'", 'cancelled' => "status IN ('cancelled', 'rejected', 'expired')"];
        if (isset($map[$status])) $where[] = $map[$status];
        elseif (in_array($status, ['reviewed', 'quoted', 'accepted', 'rejected', 'expired', 'client_confirmed'], true)) {
            $where[] = 'status = :st'; $parameters['st'] = $status;
        }
        if ($search !== '') {
            $where[] = '(quote_number LIKE :s1 OR company_name LIKE :s2 OR contact_person LIKE :s3 OR email LIKE :s4)';
            foreach (['s1', 's2', 's3', 's4'] as $key) $parameters[$key] = '%'.$search.'%';
        }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        return $this->view($request, 'quotes/index', [
            'status_filter' => $status, 'search' => $search,
            'quotes' => $this->database->fetchAllAssociative("SELECT * FROM quote_requests $whereSql ORDER BY (status = 'new') DESC, created_at DESC", $parameters),
            'kpi_total' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM quote_requests'),
            'kpi_new' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status IN ('new', 'pending')"),
            'kpi_confirmed' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status IN ('confirmed', 'accepted')"),
            'kpi_in_progress' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status = 'in_progress'"),
            'kpi_completed' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status = 'completed'"),
            'kpi_cancelled' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM quote_requests WHERE status IN ('cancelled', 'rejected', 'expired')"),
        ], 'Quote Requests Queue', 'quotes', '<link rel="stylesheet" href="quotes.css">', '<script src="quotes.js" defer></script>');
    }

    #[Route('/admin/quotes/view.php', name: 'admin_quote_view')]
    public function quoteView(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        $id = (int) $request->query->get('id', 0);
        $quote = $this->database->fetchAssociative('SELECT q.*, u.username AS quoted_by_name FROM quote_requests q LEFT JOIN admin_users u ON q.quoted_by = u.id WHERE q.id = :id', ['id' => $id]);
        if (!$quote) return $this->redirect('index.php?err='.rawurlencode('Quote request not found.'));
        $linked = $this->database->fetchAssociative('SELECT id, order_number, status, installation_head, installation_date FROM orders WHERE order_number = :num OR notes LIKE :text LIMIT 1', ['num' => 'APD-INST-'.$quote['quote_number'], 'text' => '%'.$quote['quote_number'].'%']);
        $status = $quote['status'];
        if ($linked && $linked['status'] === 'client_confirmed' && in_array($status, ['new', 'pending'], true)) $status = 'client_confirmed';
        [$badge, $label] = match ($status) {
            'confirmed' => ['badge-accepted', 'Confirmed for Installation'],
            'client_confirmed' => ['badge-processing', 'Confirmed by Client'],
            'in_progress' => ['badge-processing', 'Installation In Progress'],
            'completed' => ['badge-accepted', 'Installation Completed'],
            'cancelled', 'rejected', 'expired' => ['badge-rejected', ucfirst($status)],
            'reviewed' => ['badge-processing', 'Reviewed'],
            'quoted' => ['badge-quoted', 'Quoted'],
            'accepted' => ['badge-accepted', 'Accepted'],
            default => ['badge-pending', 'Pending Review'],
        };
        return $this->view($request, 'quotes/view', [
            'quote' => $quote, 'linkedOrder' => $linked, 'status' => $status,
            'badgeClass' => $badge, 'statusLabel' => $label,
            'discounts' => is_array(json_decode((string) ($quote['applicable_discounts'] ?? ''), true)) ? json_decode((string) $quote['applicable_discounts'], true) : [],
        ], 'Quote '.$quote['quote_number'], 'quotes', '<link rel="stylesheet" href="quotes.css">', '<script src="quotes.js" defer></script>');
    }
}
