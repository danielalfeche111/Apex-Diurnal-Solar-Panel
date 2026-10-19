<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AdminAuth;
use App\Service\AdminInventory;
use App\Service\AdminOrder;
use App\Service\AdminProduct;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminActionController
{
    public function __construct(
        private readonly Connection $database,
        private readonly AdminAuth $auth,
        private readonly AdminInventory $inventory,
        private readonly AdminOrder $orders,
        private readonly AdminProduct $products,
    ) {
    }

    #[Route('/admin/inventory/product.php', name: 'admin_inventory_product', methods: ['POST'])]
    public function product(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) {
            return $this->redirect('index.php', 'err', 'Security token expired. Please try again.');
        }
        $input = $request->request->all();
        try {
            $mode = (string) ($input['mode'] ?? '');
            $message = match ($mode) {
                'create', 'update' => $this->products->save($input, $this->auth->user($request->getSession())['id']),
                'archive' => $this->products->setActive((string) ($input['product_id'] ?? ''), false),
                'restore' => $this->products->setActive((string) ($input['product_id'] ?? ''), true),
                default => throw new \InvalidArgumentException('Unknown product action.'),
            };
            return $this->redirect('index.php', 'msg', $message);
        } catch (\Throwable $error) {
            return $this->redirect('index.php', 'err', $error->getMessage());
        }
    }

    private function redirect(string $path, string $key = '', string $message = ''): Response
    {
        return new Response('', 302, ['Location' => $path.($key === '' ? '' : (str_contains($path, '?') ? '&' : '?').$key.'='.rawurlencode($message))]);
    }

    #[Route('/admin/inventory/edit.php', name: 'admin_inventory_edit')]
    public function inventoryEdit(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        if (!$request->isMethod('POST')) return $this->redirect('index.php');
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) return $this->redirect('index.php', 'err', 'Security token expired. Please try again.');
        $productId = trim((string) $request->request->get('product_id', ''));
        $quantity = (int) $request->request->get('quantity', 0);
        if ($productId === '' || $quantity <= 0) return $this->redirect('index.php', 'err', 'Invalid quantity or product specified.');
        $delta = $request->request->get('adjustment_type', 'add') === 'add' ? $quantity : -$quantity;
        try {
            $this->inventory->adjust($productId, $delta, trim((string) $request->request->get('reason', 'Manual stock adjustment')), $this->auth->user($request->getSession())['id']);
            return $this->redirect('index.php', 'msg', sprintf('Stock successfully adjusted by %s%d units.', $delta > 0 ? '+' : '', $delta));
        } catch (\Throwable $error) {
            return $this->redirect('index.php', 'err', $error->getMessage());
        }
    }

    #[Route('/admin/orders/process.php', name: 'admin_order_process')]
    public function orderProcess(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        if (!$request->isMethod('POST')) return $this->redirect('index.php');
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) return $this->redirect('index.php', 'err', 'Security token expired. Please try again.');
        $id = (int) $request->request->get('order_id', 0);
        $source = $request->request->get('redirect_source', 'index') === 'view' ? 'view.php?id='.$id : 'index.php';
        $status = trim((string) $request->request->get('new_status', ''));
        $admin = $this->auth->user($request->getSession());
        try {
            $outcome = $this->orders->updateStatus($id, $status, $admin['id'], $admin['username'], trim((string) $request->request->get('admin_note', '')));
            $message = $outcome['changed'] ? "Order {$outcome['order']['order_number']} status updated to ".ucfirst($status).'.' : 'No change in status.';
            return $this->redirect($source, 'msg', $message);
        } catch (\Throwable $error) {
            return $this->redirect($source, 'err', $error->getMessage());
        }
    }

    #[Route('/admin/orders/api.php', name: 'admin_order_api')]
    public function orderApi(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request, true)) return $blocked;
        if ($request->isMethod('POST') && !$this->auth->verify($request->getSession(), $request->request->get('csrf_token', $request->headers->get('X-CSRF-Token')))) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
        }
        $action = $request->request->get('action', $request->query->get('action', ''));
        if ($action === 'check_stock') {
            return new JsonResponse(['success' => true, 'data' => $this->inventory->availability((int) $request->query->get('order_id', 0))]);
        }
        if ($action === 'quick_update_status') {
            $id = (int) $request->request->get('order_id', 0);
            $status = trim((string) $request->request->get('new_status', ''));
            try {
                $admin = $this->auth->user($request->getSession());
                $result = $this->orders->updateStatus($id, $status, $admin['id']);
                return new JsonResponse(['success' => true, 'message' => "Order {$result['order']['order_number']} status updated to ".ucfirst($status), 'new_status' => $status]);
            } catch (\Throwable $error) {
                return new JsonResponse(['success' => false, 'message' => $error->getMessage()], 400);
            }
        }
        return new JsonResponse(['success' => false, 'message' => 'Unknown endpoint action'], 404);
    }

    #[Route('/admin/schedule/appointments.php', name: 'admin_appointment_update', methods: ['POST'])]
    public function appointmentUpdate(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request)) return $blocked;
        if (!$this->auth->verify($request->getSession(), $request->request->get('csrf_token'))) return $this->redirect('index.php', 'err', 'Security token expired.');
        $id = (int) $request->request->get('booking_id', 0);
        if ($id <= 0) return $this->redirect('index.php', 'err', 'Invalid booking reference.');
        $status = trim((string) $request->request->get('status', 'pending'));
        if (!in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) return $this->redirect('appointments.php?id='.$id, 'err', 'Invalid status.');
        try {
            $this->database->executeStatement('UPDATE service_bookings SET status = :status, assigned_technician = :tech, preferred_date = :date, preferred_time_slot = :slot, access_notes = :notes WHERE id = :id', [
                'status' => $status,
                'tech' => trim((string) $request->request->get('assigned_technician', '')) ?: null,
                'date' => trim((string) $request->request->get('preferred_date', '')) ?: date('Y-m-d'),
                'slot' => trim((string) $request->request->get('preferred_time_slot', '')) ?: '8:00 AM - 10:00 AM',
                'notes' => trim((string) $request->request->get('access_notes', '')),
                'id' => $id,
            ]);
            $path = $request->request->get('redirect_source', 'detail') === 'index' ? 'index.php' : 'appointments.php?id='.$id;
            return $this->redirect($path, 'msg', 'Appointment updated successfully.');
        } catch (\Throwable $error) {
            return $this->redirect('appointments.php?id='.$id, 'err', $error->getMessage());
        }
    }

    #[Route('/admin/schedule/calendar.php', name: 'admin_calendar')]
    public function calendar(Request $request): Response
    {
        if ($blocked = $this->auth->guard($request, true)) return new JsonResponse(['error' => 'Unauthorized'], 401);
        $start = substr((string) $request->query->get('start', date('Y-m-01')), 0, 10);
        $end = substr((string) $request->query->get('end', date('Y-m-t', strtotime('+2 months'))), 0, 10);
        $where = ['preferred_date BETWEEN :start AND :end']; $parameters = ['start' => $start, 'end' => $end];
        $service = (string) $request->query->get('service_type', '');
        $status = (string) $request->query->get('status', '');
        if (in_array($service, ['consultation', 'installation', 'maintenance'], true)) {
            $where[] = 'service_type = :stype'; $parameters['stype'] = $service;
        }
        if (in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $where[] = 'status = :status'; $parameters['status'] = $status;
        }
        $rows = $this->database->fetchAllAssociative('SELECT * FROM service_bookings WHERE '.implode(' AND ', $where).' ORDER BY preferred_date ASC', $parameters);
        $events = [];
        foreach ($rows as $booking) {
            $slot = trim((string) $booking['preferred_time_slot']);
            [$timeStart, $timeEnd] = match (true) {
                str_contains($slot, '8:00') => ['08:00:00', '10:00:00'],
                str_contains($slot, '10:00') => ['10:00:00', '12:00:00'],
                str_contains($slot, '1:00') => ['13:00:00', '15:00:00'],
                str_contains($slot, '3:00') => ['15:00:00', '17:00:00'],
                strtolower($slot) === 'afternoon' => ['13:30:00', '16:00:00'],
                default => ['09:00:00', '11:30:00'],
            };
            [$background, $border] = match ($booking['status']) {
                'pending' => ['#f59e0b', '#d97706'],
                'confirmed' => ['#0284c7', '#0369a1'],
                'completed' => ['#10b981', '#059669'],
                'cancelled' => ['#ef4444', '#dc2626'],
                default => ['#1b335f', '#1b335f'],
            };
            $events[] = [
                'id' => (string) $booking['id'], 'title' => strtoupper($booking['service_type']).': '.$booking['customer_name'],
                'start' => $booking['preferred_date'].'T'.$timeStart, 'end' => $booking['preferred_date'].'T'.$timeEnd,
                'backgroundColor' => $background, 'borderColor' => $border, 'textColor' => '#ffffff',
                'extendedProps' => [
                    'booking_reference' => $booking['booking_reference'], 'customer_name' => $booking['customer_name'],
                    'customer_email' => $booking['customer_email'], 'customer_phone' => $booking['customer_phone'],
                    'service_type' => $booking['service_type'], 'status' => $booking['status'],
                    'time_slot' => ucfirst($booking['preferred_time_slot']), 'address' => $booking['address'],
                    'access_notes' => $booking['access_notes'] ?? '',
                    'assigned_technician' => $booking['assigned_technician'] ?? 'Unassigned', 'order_id' => $booking['order_id'] ?? null,
                ],
            ];
        }
        return new JsonResponse($events);
    }
}
