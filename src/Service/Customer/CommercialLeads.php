<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Doctrine\DBAL\Connection;

final class CommercialLeads
{
    public const SLOTS = ['8:00 AM - 10:00 AM', '10:00 AM - 12:00 PM', '1:00 PM - 3:00 PM', '3:00 PM - 5:00 PM'];

    public function __construct(private readonly Connection $db, private readonly Locations $locations)
    {
    }

    public function availability(string $date): array
    {
        $past = $date < date('Y-m-d');
        if ($past) {
            return ['success' => true, 'date' => $date, 'is_past' => true, 'booked_slots' => self::SLOTS, 'available_slots' => []];
        }
        $raw = $this->db->fetchFirstColumn("SELECT preferred_time_slot FROM service_bookings WHERE preferred_date = :date AND status NOT IN ('cancelled')", ['date' => $date]);
        $booked = [];
        foreach ($raw as $slot) {
            foreach ($this->expandSlot((string) $slot) as $standard) $booked[$standard] = true;
        }
        $bookedSlots = array_values(array_filter(self::SLOTS, static fn (string $slot): bool => isset($booked[$slot])));
        return ['success' => true, 'date' => $date, 'is_past' => false, 'booked_slots' => $bookedSlots, 'available_slots' => array_values(array_diff(self::SLOTS, $bookedSlots)), 'all_slots' => self::SLOTS];
    }

    private function expandSlot(string $slot): array
    {
        $slot = trim($slot);
        if (strcasecmp($slot, 'morning') === 0) return array_slice(self::SLOTS, 0, 2);
        if (strcasecmp($slot, 'afternoon') === 0) return array_slice(self::SLOTS, 2, 2);
        foreach (self::SLOTS as $standard) if (strcasecmp($slot, $standard) === 0) return [$standard];
        return [];
    }

    /** @return array{0: array, 1: array} Normalized fields and field errors. */
    public function validate(array $raw): array
    {
        $d = [];
        foreach ([
            'lead_type', 'company_name', 'business_registration_type', 'consult_street', 'consult_city', 'consult_province',
            'consult_postal', 'property_address', 'target_timeline', 'facility_type', 'power_supply', 'facility_size',
            'current_monthly_bill', 'contact_person', 'contact_title', 'corporate_email', 'phone_number',
            'best_call_time', 'preferred_date', 'preferred_time_slot', 'access_notes',
        ] as $field) $d[$field] = isset($raw[$field]) && is_scalar($raw[$field]) ? trim((string) $raw[$field]) : '';
        $d['lead_type'] = strtolower($d['lead_type']) === 'rfq' ? 'rfq' : 'consultation';
        $d['consult_city'] = [
            'Binan City' => 'Biñan City', 'Dasmarinas City' => 'Dasmariñas City', 'Las Pinas' => 'Las Piñas',
            'Paranaque' => 'Parañaque', 'Munoz City' => 'Science City of Muñoz', 'Muñoz City' => 'Science City of Muñoz',
            'Science City of Munoz' => 'Science City of Muñoz', 'Santo Tomas City' => 'Sto. Tomas City',
            'Samal City' => 'Island Garden City of Samal', 'Cagayan de Oro City' => 'Cagayan De Oro City',
            'San Jose del Monte City' => 'San Jose Del Monte City', 'Pasay' => 'Pasay City',
        ][$d['consult_city']] ?? $d['consult_city'];
        if ($d['consult_street'] === '' && $d['consult_city'] === '' && $d['consult_province'] === '' && $d['consult_postal'] === '' && $d['property_address'] !== '') {
            $d['consult_street'] = $d['property_address'];
        }
        $d['property_address'] = trim(implode(', ', array_filter([$d['consult_street'], $d['consult_city'], $d['consult_province']])).($d['consult_postal'] !== '' ? ' '.$d['consult_postal'] : '')) ?: $d['property_address'];
        $errors = [];
        $rfq = $d['lead_type'] === 'rfq';
        if ($d['company_name'] === '') $errors['company_name'] = $rfq ? 'Company Name is required.' : 'Full Name or Facility Name is required.';
        elseif (mb_strlen($d['company_name']) < 2) $errors['company_name'] = $rfq ? 'Company Name must be at least 2 characters.' : 'Name must be at least 2 characters.';
        if ($d['consult_street'] === '') $errors['consult_street'] = $rfq ? 'Installation street address is required.' : 'Street address is required (house #, street, barangay).';
        elseif (mb_strlen($d['consult_street']) < 5) $errors['consult_street'] = 'Please provide a complete street address (at least 5 characters).';
        if ($d['consult_province'] === '') $errors['consult_province'] = 'Please select a province from the dropdown.';
        elseif (!in_array($d['consult_province'], $this->locations->provinces(), true)) $errors['consult_province'] = 'Invalid province selected. Please choose from the dropdown list.';
        if ($d['consult_city'] === '') $errors['consult_city'] = 'Please select a city from the dropdown.';
        elseif (!in_array($d['consult_city'], $this->locations->cities(), true)) $errors['consult_city'] = 'Invalid city selected. Please choose from the dropdown list.';
        elseif ($d['consult_province'] !== '' && !$this->locations->cityMatchesProvince($d['consult_city'], $d['consult_province'])) $errors['consult_city'] = $d['consult_city'].' is not located in '.$d['consult_province'].'. Please select a valid city.';
        if ($d['consult_postal'] === '') $errors['consult_postal'] = 'Postal code is required.';
        elseif (!preg_match('/^\d{4}$/', $d['consult_postal'])) $errors['consult_postal'] = 'Please enter a valid 4-digit postal code (e.g. 1000).';
        if ($d['contact_person'] === '') $errors['contact_person'] = 'Contact person name is required.';
        elseif (mb_strlen($d['contact_person']) < 2) $errors['contact_person'] = 'Contact person name must be at least 2 characters.';
        if ($d['corporate_email'] === '') $errors['corporate_email'] = $rfq ? 'Corporate email address is required.' : 'Corporate or personal email address is required.';
        elseif (!filter_var($d['corporate_email'], FILTER_VALIDATE_EMAIL)) $errors['corporate_email'] = $rfq ? 'Please enter a valid corporate email address.' : 'Please enter a valid email address.';
        $phone = preg_replace('/\D/', '', $d['phone_number']);
        if ($d['phone_number'] === '') $errors['phone_number'] = $rfq ? 'Contact phone number is required.' : 'Phone number is required for pre-inspection contact.';
        elseif (strlen($phone) !== 11) $errors['phone_number'] = 'Please enter a valid 11-digit mobile number (e.g., 09171234567).';
        else $d['phone_number'] = $phone;
        $d['facility_size'] = is_numeric($d['facility_size']) ? (float) $d['facility_size'] : 0.0;
        $d['current_monthly_bill'] = is_numeric($d['current_monthly_bill']) ? (float) $d['current_monthly_bill'] : 0.0;
        if ($rfq) {
            if (!in_array($d['business_registration_type'], ['Sole Proprietorship', 'Partnership', 'Corporation (SEC Registered)', 'One Person Corporation (OPC)', 'Cooperative', 'Government Agency / LGU'], true)) {
                $errors['business_registration_type'] = $d['business_registration_type'] === '' ? 'Please select a business registration type.' : 'Please select a valid business registration type.';
            }
            if (!in_array($d['target_timeline'], ['Immediate', 'Within 3 Months', '6+ Months'], true)) {
                $errors['target_timeline'] = $d['target_timeline'] === '' ? 'Please select a target project completion timeline.' : 'Please select a valid project timeline.';
            }
            if ($d['facility_size'] <= 0) $errors['facility_size'] = 'Facility Rooftop / Land Area (in sqm) is required.';
            if ($d['current_monthly_bill'] <= 0) $errors['current_monthly_bill'] = 'Current Monthly Electricity Bill (in ₱) is required.';
            if ($d['best_call_time'] !== '' && !in_array($d['best_call_time'], ['Morning', 'Afternoon', 'Anytime'], true)) $errors['best_call_time'] = 'Please select a valid preferred contact time.';
        } else {
            if (!in_array($d['facility_type'], ['Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential'], true)) $errors['facility_type'] = 'Please select a valid facility type from the list.';
            if (!in_array($d['power_supply'], ['Single-Phase Supply', 'Three-Phase Supply'], true)) $errors['power_supply'] = 'Please select a valid power supply connection (Single-Phase or Three-Phase).';
            if ($d['best_call_time'] !== '' && !in_array($d['best_call_time'], [...self::SLOTS, 'Anytime', 'Morning', 'Afternoon'], true)) $errors['best_call_time'] = 'Please select your preferred time for the pre-inspection call.';
            if ($d['preferred_date'] !== '') {
                $timestamp = strtotime(str_replace('/', '-', $d['preferred_date']));
                if ($timestamp !== false) $d['preferred_date'] = date('Y-m-d', $timestamp);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['preferred_date'])) $errors['preferred_date'] = 'Please select a valid site visit date.';
            elseif ($d['preferred_date'] < date('Y-m-d')) $errors['preferred_date'] = 'Site visit date cannot be in the past.';
            if (!in_array($d['preferred_time_slot'], [...self::SLOTS, 'Morning', 'Afternoon'], true)) $errors['preferred_time_slot'] = 'Please select a preferred on-site audit inspection time.';
            if (!isset($errors['preferred_date']) && !isset($errors['preferred_time_slot'])) {
                $availability = $this->availability($d['preferred_date']);
                $selectedSlots = $this->expandSlot($d['preferred_time_slot']);
                if (array_intersect($selectedSlots, $availability['booked_slots']) !== []) $errors['preferred_time_slot'] = 'The selected inspection time is already booked for this date. Please select another time or date.';
            }
        }
        return [$d, $errors];
    }

    public function create(array $d, ?int $userId): array
    {
        $rfq = $d['lead_type'] === 'rfq';
        $estimate = ['system' => null, 'cost' => null, 'savings' => null, 'payback' => null, 'timeline' => null];
        if ($rfq) {
            $monthlyKwh = $d['current_monthly_bill'] / 12.0;
            $dailyKwh = $monthlyKwh / 30.0;
            $size = round(max(5.0, min(($dailyKwh / 4.5) * .70, $d['facility_size'] / 7.5, 1000.0)), 1);
            $cost = round($size * 48000.0, 2);
            $savings = round($size * 4.5 * 365 * 11.5, 2);
            $estimate = ['system' => $size, 'cost' => $cost, 'savings' => $savings, 'payback' => $savings > 0 ? round($cost / $savings, 1) : 4.0, 'timeline' => $size > 100 ? '8-12 Weeks' : '4-6 Weeks'];
        }
        $this->db->beginTransaction();
        try {
            $this->db->insert('commercial_leads', [
                'user_id' => $userId, 'lead_type' => $d['lead_type'], 'company_name' => $d['company_name'],
                'business_registration_type' => $rfq ? $d['business_registration_type'] : null,
                'property_address' => $d['property_address'], 'target_timeline' => $rfq ? $d['target_timeline'] : null,
                'facility_type' => $rfq ? 'Commercial' : $d['facility_type'], 'power_supply' => $rfq ? null : $d['power_supply'],
                'facility_size' => $d['facility_size'], 'current_monthly_bill' => $d['current_monthly_bill'],
                'estimated_system_size' => $estimate['system'], 'estimated_installation_cost' => $estimate['cost'],
                'estimated_annual_savings' => $estimate['savings'], 'estimated_payback_period' => $estimate['payback'],
                'applicable_discounts' => null, 'estimated_installation_timeline' => $estimate['timeline'],
                'contact_person' => $d['contact_person'], 'contact_title' => $d['contact_title'] ?: null,
                'corporate_email' => $d['corporate_email'], 'phone_number' => $d['phone_number'],
                'best_call_time' => $d['best_call_time'] ?: null,
                'preferred_date' => $rfq ? null : $d['preferred_date'],
                'preferred_time_slot' => $rfq ? null : $d['preferred_time_slot'],
                'access_notes' => $d['access_notes'] ?: null,
            ]);
            $leadId = (int) $this->db->lastInsertId();
            $result = ['success' => true, 'lead_id' => $leadId, 'lead_type' => $d['lead_type'], 'company_name' => $d['company_name']];
            if ($rfq) {
                $linkedUser = $userId ?: $this->db->fetchOne('SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1', ['email' => $d['corporate_email']]);
                $linkedUser = $linkedUser ? (int) $linkedUser : null;
                $quoteNumber = 'RFQ-'.date('Ymd').'-'.sprintf('%04d', $leadId);
                $this->db->insert('quote_requests', [
                    'user_id' => $linkedUser, 'lead_id' => $leadId, 'quote_number' => $quoteNumber,
                    'company_name' => $d['company_name'], 'contact_person' => $d['contact_person'],
                    'email' => $d['corporate_email'], 'phone' => $d['phone_number'], 'facility_type' => 'Commercial',
                    'facility_size' => $d['facility_size'], 'current_monthly_bill' => $d['current_monthly_bill'],
                    'target_timeline' => $d['target_timeline'], 'estimated_system_size' => $estimate['system'],
                    'estimated_installation_cost' => $estimate['cost'], 'estimated_annual_savings' => $estimate['savings'],
                    'estimated_payback_period' => $estimate['payback'], 'applicable_discounts' => null,
                    'installation_address' => $d['property_address'], 'access_notes' => $d['access_notes'], 'status' => 'new',
                ]);
                $orderNumber = 'APD-INST-'.$quoteNumber;
                $total = (float) $estimate['cost'];
                $subtotal = round($total / 1.12, 2);
                $tax = $total - $subtotal;
                $notes = "Commercial Grid Turnkey Solar Installation\nRFQ Reference: {$quoteNumber}\nFacility Type: Commercial Facility\nFacility Area: ".number_format($d['facility_size'], 2)." sqm\nCurrent Monthly Electricity Bill: ₱".number_format($d['current_monthly_bill'], 2)."\nEstimated System Capacity: ".number_format($estimate['system'], 2)." kWp\nEstimated Annual Bill Savings: ₱".number_format($estimate['savings'], 2)."\nEstimated Payback Period: ".number_format($estimate['payback'], 1)." years\nTarget Timeline: {$d['target_timeline']}\n".($d['access_notes'] !== '' ? 'Notes: '.$d['access_notes'] : '');
                $this->db->insert('orders', [
                    'order_number' => $orderNumber, 'user_id' => $linkedUser,
                    'customer_name' => $d['company_name'].' ('.$d['contact_person'].')',
                    'customer_email' => $d['corporate_email'], 'customer_phone' => $d['phone_number'],
                    'property_type' => 'Commercial', 'payment_method' => 'Commercial Installation Contract', 'status' => 'pending',
                    'subtotal' => $subtotal, 'tax_amount' => $tax, 'total_amount' => $total,
                    'shipping_address' => $d['property_address'], 'notes' => $notes,
                ]);
                $orderId = (int) $this->db->lastInsertId();
                $this->db->insert('order_items', [
                    'order_id' => $orderId, 'product_id' => 'commercial-grids',
                    'product_name' => 'Commercial Grid Turnkey Solar Installation - '.$d['company_name'].' ('.number_format($estimate['system'], 2).' kWp)',
                    'quantity' => 1, 'unit_price' => $subtotal, 'total_price' => $subtotal,
                ]);
                $result += ['quote_number' => $quoteNumber, 'order_id' => $orderId, 'order_number' => $orderNumber,
                    'message' => 'Commercial grid proposal successfully generated and added to your Orders! You can review full project pricing and details in My Orders.',
                    'disclaimer' => 'This is a preliminary quote request. Final pricing requires site assessment and detailed system design.'];
            } else {
                $bookingRef = 'COMM-'.date('Ymd').'-'.sprintf('%04d', $leadId);
                $this->db->insert('service_bookings', [
                    'booking_reference' => $bookingRef, 'customer_name' => $d['contact_person'] ?: $d['company_name'],
                    'customer_email' => $d['corporate_email'], 'customer_phone' => $d['phone_number'],
                    'service_type' => 'consultation', 'preferred_date' => $d['preferred_date'],
                    'preferred_time_slot' => $d['preferred_time_slot'] ?: self::SLOTS[0], 'status' => 'pending',
                    'address' => $d['property_address'], 'access_notes' => $d['access_notes'],
                ]);
                $result += ['booking_reference' => $bookingRef, 'message' => 'Commercial consultation successfully scheduled! Our engineering team will contact you shortly.'];
            }
            $this->db->commit();
            return $result;
        } catch (\Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }
}
