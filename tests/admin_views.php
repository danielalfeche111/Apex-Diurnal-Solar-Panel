<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\View\PhpTemplateRenderer;
use App\View\ViewContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});

$root = dirname(__DIR__);
$stack = new RequestStack();
$request = Request::create('/admin/index.php');
$request->setSession(new Session(new MockArraySessionStorage()));
$stack->push($request);
$renderer = new PhpTemplateRenderer(new ViewContext($stack, $root), $root);
$templates = [
    'index', 'inventory/index', 'inventory/alerts', 'orders/index', 'orders/view',
    'quotes/index', 'quotes/view', 'schedule/index', 'schedule/bookings', 'schedule/appointments',
];

foreach ($templates as $template) {
    $source = file_get_contents($root.'/templates/admin/'.$template.'.php');
    preg_match_all(<<<'REGEX'
~\$(order|quote|booking)\[['"]([A-Za-z_]+)['"]\]~
REGEX, $source, $matches, PREG_SET_ORDER);
    $detail = ['order' => [], 'quote' => [], 'booking' => []];
    foreach ($matches as $match) {
        $detail[$match[1]][$match[2]] = '0';
    }
    $detail['order'] = array_replace($detail['order'], ['id' => 1, 'order_number' => 'TEST-1', 'status' => 'pending']);
    $detail['quote'] = array_replace($detail['quote'], ['id' => 1, 'quote_number' => 'RFQ-1', 'status' => 'new']);
    $detail['booking'] = array_replace($detail['booking'], ['id' => 1, 'booking_reference' => 'BOOK-1', 'status' => 'pending']);

    $variables = array_merge($detail, [
        'page_title' => 'Admin Test', 'active_nav' => 'dashboard',
        'admin_base' => str_contains($template, '/') ? '../' : './',
        'admin' => ['id' => 1, 'username' => 'Admin', 'role' => 'superadmin'],
        'badge_counts' => [], 'csrf_token' => 'test-token',
        'extra_head' => '', 'extra_scripts' => '', 'msg' => '', 'err' => '',
        'total_revenue' => 0, 'pending_orders' => 0, 'low_stock_items' => 0,
        'pending_bookings' => 0, 'new_quotes' => 0, 'recent_orders' => [],
        'upcoming_bookings' => [], 'recent_quotes' => [], 'inventory_health' => [],
        'items' => [], 'alerts' => [], 'transactions' => [],
        'orders' => [], 'quotes' => [], 'bookings' => [],
        'kpi_total_orders' => 0, 'kpi_pending' => 0, 'kpi_processing' => 0,
        'kpi_revenue' => 0, 'kpi_total' => 0, 'kpi_new' => 0,
        'kpi_confirmed' => 0, 'kpi_in_progress' => 0,
        'kpi_completed' => 0, 'kpi_cancelled' => 0,
        'status_filter' => 'all', 'service_filter' => 'all', 'search' => '',
        'totalPhysicalUnits' => 0, 'lowStockCount' => 0,
        'outOfStockCount' => 0, 'physicalItemCount' => 0,
        'status' => 'pending', 'badgeClass' => 'badge-pending',
        'statusLabel' => 'Pending Review', 'discounts' => [],
        'linkedOrder' => null, 'linkedBooking' => null,
        'stockCheck' => ['can_fulfill' => true, 'shortages' => [], 'items' => []],
    ]);
    preg_match_all(<<<'REGEX'
~\$(ord|q|b|item|a|t|inv|ub)\[['"]([A-Za-z_]+)['"]\]~
REGEX, $source, $rowMatches, PREG_SET_ORDER);
    $rows = ['ord' => [], 'q' => [], 'b' => [], 'item' => [], 'a' => [], 't' => [], 'inv' => [], 'ub' => []];
    foreach ($rowMatches as $match) {
        $rows[$match[1]][$match[2]] = '0';
    }
    foreach ($rows as &$row) {
        $row = array_replace($row, [
            'id' => 1, 'status' => 'pending', 'type' => 'physical', 'product_type' => 'physical',
            'order_number' => 'TEST-1', 'quote_number' => 'RFQ-1',
            'booking_reference' => 'BOOK-1', 'current_stock' => 5,
            'reorder_point' => 10, 'max_stock_level' => 100,
            'preferred_date' => '2026-11-01', 'created_at' => '2026-10-19 12:00:00',
        ]);
    }
    unset($row);
    $populated = array_replace($variables, [
        'recent_orders' => [$rows['ord']], 'recent_quotes' => [$rows['q']],
        'upcoming_bookings' => [$rows['ub']], 'inventory_health' => [$rows['inv']],
        'items' => [$rows['item']], 'alerts' => [$rows['a']], 'transactions' => [$rows['t']],
        'orders' => [$rows['ord']], 'quotes' => [$rows['q']], 'bookings' => [$rows['b']],
    ]);
    foreach (['empty' => $variables, 'populated' => $populated] as $scenario => $data) {
        $html = $renderer->render('admin/'.$template, $data)->getContent();
        if (!str_contains($html, '<!DOCTYPE html>') || !str_contains($html, '</html>')) {
            throw new RuntimeException('Admin view rendered incomplete HTML: '.$template.' ('.$scenario.')');
        }
        echo "Rendered admin/$template ($scenario)\n";
    }
}
