<?php
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../validation.php';

$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $response['message'] = 'Invalid request method. Only POST is allowed.';
    echo json_encode($response);
    exit;
}

// Determine lead mode: 'consultation' or 'rfq'
$raw_lead_type = strtolower(trim($_POST['lead_type'] ?? 'consultation'));
$lead_type = ($raw_lead_type === 'rfq') ? 'rfq' : 'consultation';

// Retrieve and sanitize common fields
$company_name     = trim($_POST['company_name'] ?? '');
$consult_street   = trim($_POST['consult_street'] ?? '');
$consult_city     = trim($_POST['consult_city'] ?? '');
$consult_province = trim($_POST['consult_province'] ?? '');
$consult_postal   = trim($_POST['consult_postal'] ?? '');
$contact_person   = trim($_POST['contact_person'] ?? '');
$corporate_email  = trim($_POST['corporate_email'] ?? '');
$phone_number     = trim($_POST['phone_number'] ?? '');
$best_call_time   = trim($_POST['best_call_time'] ?? '');

// Normalize ASCII aliases to canonical UTF-8 city/municipality names
$cityAliasMap = [
  'Binan City' => 'Biñan City',
  'Dasmarinas City' => 'Dasmariñas City',
  'Las Pinas' => 'Las Piñas',
  'Paranaque' => 'Parañaque',
  'Munoz City' => 'Science City of Muñoz',
  'Muñoz City' => 'Science City of Muñoz',
  'Science City of Munoz' => 'Science City of Muñoz',
  'Santo Tomas City' => 'Sto. Tomas City',
  'Samal City' => 'Island Garden City of Samal',
  'Cagayan de Oro City' => 'Cagayan De Oro City',
  'San Jose del Monte City' => 'San Jose Del Monte City',
  'Pasay' => 'Pasay City',
];
if (isset($cityAliasMap[$consult_city])) {
    $consult_city = $cityAliasMap[$consult_city];
}

// Fallback: legacy single field `property_address`
$legacy_address = trim($_POST['property_address'] ?? '');
if ($consult_street === '' && $consult_city === '' && $consult_province === '' && $consult_postal === '' && $legacy_address !== '') {
    $consult_street = $legacy_address;
}
$property_address = trim(implode(', ', array_filter([$consult_street, $consult_city, $consult_province])) . ($consult_postal !== '' ? ' ' . $consult_postal : ''));
if ($property_address === '' && $legacy_address !== '') {
    $property_address = $legacy_address;
}

// Philippine province/city validation data
require_once __DIR__ . '/../philippine_locations.php';

$allowed_provinces = array_keys($consultProvinceCityMap);
$allowed_cities = $philippine_cities;

$errors = [];

// =========================================================================
// MODE 1: CORPORATE RFQ / QUOTE SUBMISSION
// =========================================================================
if ($lead_type === 'rfq') {
    $business_registration_type = trim($_POST['business_registration_type'] ?? '');
    $target_timeline            = trim($_POST['target_timeline'] ?? '');

    $allowed_registration_types = [
        'Sole Proprietorship',
        'Partnership',
        'Corporation (SEC Registered)',
        'One Person Corporation (OPC)',
        'Cooperative',
        'Government Agency / LGU'
    ];

    $allowed_timelines = [
        'Immediate',
        'Within 3 Months',
        '6+ Months'
    ];

    // Step 1: Company Details
    if ($company_name === '') {
        $errors['company_name'] = 'Company Name is required.';
    } elseif (mb_strlen($company_name) < 2) {
        $errors['company_name'] = 'Company Name must be at least 2 characters.';
    }

    if ($business_registration_type === '') {
        $errors['business_registration_type'] = 'Please select a business registration type.';
    } elseif (!in_array($business_registration_type, $allowed_registration_types, true)) {
        $errors['business_registration_type'] = 'Please select a valid business registration type.';
    }

    // Step 2: Installation Address
    if ($consult_street === '') {
        $errors['consult_street'] = 'Installation street address is required.';
    } elseif (mb_strlen($consult_street) < 5) {
        $errors['consult_street'] = 'Please provide a complete street address (at least 5 characters).';
    }

    if ($consult_province === '') {
        $errors['consult_province'] = 'Please select a province from the dropdown.';
    } elseif (!in_array($consult_province, $allowed_provinces, true)) {
        $errors['consult_province'] = 'Invalid province selected. Please choose from the dropdown list.';
    }

    if ($consult_city === '') {
        $errors['consult_city'] = 'Please select a city from the dropdown.';
    } elseif (!in_array($consult_city, $allowed_cities, true)) {
        $errors['consult_city'] = 'Invalid city selected. Please choose from the dropdown list.';
    }

    // Geographic cross-validation
    if ($consult_province !== '' && $consult_city !== '' && isset($consultProvinceCityMap[$consult_province])) {
        if (!checkCityMatchesProvince($consult_city, $consult_province, $consultProvinceCityMap)) {
            $errors['consult_city'] = $consult_city . ' is not located in ' . $consult_province . '. Please select a valid city.';
        }
    }

    if ($consult_postal === '') {
        $errors['consult_postal'] = 'Postal code is required.';
    } elseif (!preg_match('/^\d{4}$/', $consult_postal)) {
        $errors['consult_postal'] = 'Please enter a valid 4-digit postal code (e.g. 1000).';
    }

    // Step 3: Project Timeline & Contact
    if ($target_timeline === '') {
        $errors['target_timeline'] = 'Please select a target project completion timeline.';
    } elseif (!in_array($target_timeline, $allowed_timelines, true)) {
        $errors['target_timeline'] = 'Please select a valid project timeline.';
    }

    if ($contact_person === '') {
        $errors['contact_person'] = 'Contact person name is required.';
    } elseif (mb_strlen($contact_person) < 2) {
        $errors['contact_person'] = 'Contact person name must be at least 2 characters.';
    }

    if ($corporate_email === '') {
        $errors['corporate_email'] = 'Corporate email address is required.';
    } elseif (!filter_var($corporate_email, FILTER_VALIDATE_EMAIL)) {
        $errors['corporate_email'] = 'Please enter a valid corporate email address.';
    }

    $cleanPhone = preg_replace('/[^\d]/', '', $phone_number);
    if ($phone_number === '') {
        $errors['phone_number'] = 'Contact phone number is required.';
    } elseif (strlen($cleanPhone) !== 11) {
        $errors['phone_number'] = 'Please enter a valid 11-digit mobile number (e.g., 09171234567).';
    } else {
        $phone_number = $cleanPhone;
    }

    $allowed_call_times = ['Morning', 'Afternoon', 'Anytime'];
    if ($best_call_time !== '' && !in_array($best_call_time, $allowed_call_times, true)) {
        $errors['best_call_time'] = 'Please select a valid preferred contact time.';
    }

    // Step 1 additions: Facility specifications for RFQ
    $facility_size_raw = trim($_POST['facility_size'] ?? '');
    $current_bill_raw  = trim($_POST['current_monthly_bill'] ?? '');

    if ($facility_size_raw === '' || !is_numeric($facility_size_raw) || (float)$facility_size_raw <= 0) {
        $errors['facility_size'] = 'Facility Rooftop / Land Area (in sqm) is required.';
    } else {
        $facility_size = (float)$facility_size_raw;
    }

    if ($current_bill_raw === '' || !is_numeric($current_bill_raw) || (float)$current_bill_raw <= 0) {
        $errors['current_monthly_bill'] = 'Current Monthly Electricity Bill (in ₱) is required.';
    } else {
        $current_monthly_bill = (float)$current_bill_raw;
    }

    if (!empty($errors)) {
        http_response_code(422);
        $response['success'] = false;
        $response['message'] = 'Please correct the highlighted fields and try again.';
        $response['errors'] = $errors;
        echo json_encode($response);
        exit;
    }

    // Facility specs & solar engineering projections
    $facility_type = 'Commercial';
    $power_supply = null;

    if ($facility_size > 0 && $current_monthly_bill > 0) {
        $rate_per_kwh = 12.00; // Standard Philippine commercial grid tariff
        $monthly_kwh = $current_monthly_bill / $rate_per_kwh;
        $daily_kwh = $monthly_kwh / 30.0;
        $peak_sun_hours = 4.5; // Average solar irradiance in the Philippines
        $needed_kw = ($daily_kwh / $peak_sun_hours) * 0.70; // 70% daytime solar offset
        $max_roof_kw = $facility_size / 7.5; // Approx 7.5 sqm per 1 kWp installed
        $calculated_kw = round(max(5.0, min($needed_kw, $max_roof_kw, 1000.0)), 1);

        $estimated_system_size = $calculated_kw;
        $estimated_installation_cost = round($calculated_kw * 48000.0, 2); // ~₱48k per kW commercial turnkey
        $estimated_annual_savings = round($calculated_kw * $peak_sun_hours * 365 * 11.50, 2);
        $estimated_payback_period = ($estimated_annual_savings > 0) ? round($estimated_installation_cost / $estimated_annual_savings, 1) : 4.0;
        $estimated_installation_timeline = ($calculated_kw > 100) ? '8-12 Weeks' : '4-6 Weeks';
    } else {
        $estimated_system_size = null;
        $estimated_installation_cost = null;
        $estimated_annual_savings = null;
        $estimated_payback_period = null;
        $estimated_installation_timeline = null;
    }
    $applicable_discounts_json = null;
    $contact_title = trim($_POST['contact_title'] ?? '');
    if ($contact_title === '') $contact_title = null;
    if ($best_call_time === '') $best_call_time = null;
    $preferred_date = null;
    $preferred_time_slot = null;
    $access_notes = trim($_POST['access_notes'] ?? '');
    if ($access_notes === '') $access_notes = null;

// =========================================================================
// MODE 2: ON-SITE CONSULTATION SCHEDULING
// =========================================================================
} else {
    $facility_type       = trim($_POST['facility_type'] ?? '');
    $power_supply        = trim($_POST['power_supply'] ?? '');
    $contact_title       = trim($_POST['contact_title'] ?? '');
    $preferred_date      = trim($_POST['preferred_date'] ?? '');
    $preferred_time_slot = trim($_POST['preferred_time_slot'] ?? '');
    $access_notes        = trim($_POST['access_notes'] ?? '');
    $facility_size_raw   = trim($_POST['facility_size'] ?? '');
    $current_bill_raw    = trim($_POST['current_monthly_bill'] ?? '');

    $business_registration_type = null;
    $target_timeline = null;

    $allowed_facilities = ['Manufacturing Plant', 'Commercial Building', 'Warehouse', 'Agricultural', 'School', 'House', 'House / Residential'];
    $allowed_power_supplies = ['Single-Phase Supply', 'Three-Phase Supply'];
    $allowed_call_times = ['Morning', 'Afternoon', 'Anytime'];
    $allowed_time_slots = ['Morning', 'Afternoon'];

    // Step 1: Facility Specs
    if ($company_name === '') {
        $errors['company_name'] = 'Full Name or Facility Name is required.';
    } elseif (mb_strlen($company_name) < 2) {
        $errors['company_name'] = 'Name must be at least 2 characters.';
    }

    if ($consult_street === '') {
        $errors['consult_street'] = 'Street address is required (house #, street, barangay).';
    } elseif (mb_strlen($consult_street) < 5) {
        $errors['consult_street'] = 'Please provide a complete street address (at least 5 characters).';
    }

    if ($consult_province === '') {
        $errors['consult_province'] = 'Please select a province from the dropdown.';
    } elseif (!in_array($consult_province, $allowed_provinces, true)) {
        $errors['consult_province'] = 'Invalid province selected. Please choose from the dropdown list.';
    }

    if ($consult_city === '') {
        $errors['consult_city'] = 'Please select a city from the dropdown.';
    } elseif (!in_array($consult_city, $allowed_cities, true)) {
        $errors['consult_city'] = 'Invalid city selected. Please choose from the dropdown list.';
    }

    if ($consult_province !== '' && $consult_city !== '' && isset($consultProvinceCityMap[$consult_province])) {
        if (!checkCityMatchesProvince($consult_city, $consult_province, $consultProvinceCityMap)) {
            $errors['consult_city'] = $consult_city . ' is not located in ' . $consult_province . '. Please select a valid city.';
        }
    }

    if ($consult_postal === '') {
        $errors['consult_postal'] = 'Postal code is required.';
    } elseif (!preg_match('/^\d{4}$/', $consult_postal)) {
        $errors['consult_postal'] = 'Please enter a valid 4-digit postal code (e.g. 1000).';
    }

    if (!in_array($facility_type, $allowed_facilities, true)) {
        $errors['facility_type'] = 'Please select a valid facility type from the list.';
    }

    if (!in_array($power_supply, $allowed_power_supplies, true)) {
        $errors['power_supply'] = 'Please select a valid power supply connection (Single-Phase or Three-Phase).';
    }

    // Step 2: Contact Details
    if ($contact_person === '') {
        $errors['contact_person'] = 'Contact person name is required.';
    } elseif (mb_strlen($contact_person) < 2) {
        $errors['contact_person'] = 'Contact person name must be at least 2 characters.';
    }

    if ($corporate_email === '') {
        $errors['corporate_email'] = 'Corporate or personal email address is required.';
    } elseif (!filter_var($corporate_email, FILTER_VALIDATE_EMAIL)) {
        $errors['corporate_email'] = 'Please enter a valid email address.';
    }

    $cleanPhone = preg_replace('/[^\d]/', '', $phone_number);
    if ($phone_number === '') {
        $errors['phone_number'] = 'Phone number is required for pre-inspection contact.';
    } elseif (strlen($cleanPhone) !== 11) {
        $errors['phone_number'] = 'Please enter a valid 11-digit mobile number (e.g., 09171234567).';
    } else {
        $phone_number = $cleanPhone;
    }

    if (!in_array($best_call_time, $allowed_call_times, true)) {
        $errors['best_call_time'] = 'Please select your preferred time for the pre-inspection call.';
    }

    // Step 3: Scheduling
    if ($preferred_date !== '') {
        $ts = strtotime(str_replace('/', '-', $preferred_date));
        if ($ts) {
            $preferred_date = date('Y-m-d', $ts);
        }
    }

    if ($preferred_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferred_date)) {
        $errors['preferred_date'] = 'Please select a valid site visit date.';
    } else {
        $today = date('Y-m-d');
        if ($preferred_date < $today) {
            $errors['preferred_date'] = 'Site visit date cannot be in the past.';
        }
    }

    if (!in_array($preferred_time_slot, $allowed_time_slots, true)) {
        $errors['preferred_time_slot'] = 'Please select a preferred on-site audit time slot.';
    }

    $facility_size = is_numeric($facility_size_raw) ? (float)$facility_size_raw : 0.0;
    $current_monthly_bill = is_numeric($current_bill_raw) ? (float)$current_bill_raw : 0.0;

    if (!empty($errors)) {
        http_response_code(422);
        $response['success'] = false;
        $response['message'] = 'Please correct the highlighted fields and try again.';
        $response['errors'] = $errors;
        echo json_encode($response);
        exit;
    }

    $estimated_system_size = null;
    $estimated_installation_cost = null;
    $estimated_annual_savings = null;
    $estimated_payback_period = null;
    $applicable_discounts_json = null;
    $estimated_installation_timeline = null;
}

// =========================================================================
// DATABASE INSERTION
// =========================================================================
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        http_response_code(500);
        $response['message'] = 'Database service unavailable. Please try again shortly.';
        echo json_encode($response);
        exit;
    }

    $query = "INSERT INTO commercial_leads (
        lead_type, company_name, business_registration_type, property_address, target_timeline,
        facility_type, power_supply, facility_size, current_monthly_bill,
        estimated_system_size, estimated_installation_cost, estimated_annual_savings, estimated_payback_period, applicable_discounts, estimated_installation_timeline,
        contact_person, contact_title, corporate_email, phone_number,
        best_call_time, preferred_date, preferred_time_slot, access_notes
    ) VALUES (
        :lead_type, :company_name, :business_registration_type, :property_address, :target_timeline,
        :facility_type, :power_supply, :facility_size, :current_monthly_bill,
        :estimated_system_size, :estimated_installation_cost, :estimated_annual_savings, :estimated_payback_period, :applicable_discounts, :estimated_installation_timeline,
        :contact_person, :contact_title, :corporate_email, :phone_number,
        :best_call_time, :preferred_date, :preferred_time_slot, :access_notes
    )";

    $stmt = $db->prepare($query);

    $stmt->bindParam(':lead_type', $lead_type);
    $stmt->bindParam(':company_name', $company_name);
    $stmt->bindParam(':business_registration_type', $business_registration_type);
    $stmt->bindParam(':property_address', $property_address);
    $stmt->bindParam(':target_timeline', $target_timeline);
    $stmt->bindParam(':facility_type', $facility_type);
    $stmt->bindParam(':power_supply', $power_supply);
    $stmt->bindParam(':facility_size', $facility_size);
    $stmt->bindParam(':current_monthly_bill', $current_monthly_bill);
    $stmt->bindParam(':estimated_system_size', $estimated_system_size);
    $stmt->bindParam(':estimated_installation_cost', $estimated_installation_cost);
    $stmt->bindParam(':estimated_annual_savings', $estimated_annual_savings);
    $stmt->bindParam(':estimated_payback_period', $estimated_payback_period);
    $stmt->bindParam(':applicable_discounts', $applicable_discounts_json);
    $stmt->bindParam(':estimated_installation_timeline', $estimated_installation_timeline);
    $stmt->bindParam(':contact_person', $contact_person);
    $stmt->bindParam(':contact_title', $contact_title);
    $stmt->bindParam(':corporate_email', $corporate_email);
    $stmt->bindParam(':phone_number', $phone_number);
    $stmt->bindParam(':best_call_time', $best_call_time);
    $stmt->bindParam(':preferred_date', $preferred_date);
    $stmt->bindParam(':preferred_time_slot', $preferred_time_slot);
    $stmt->bindParam(':access_notes', $access_notes);

    if ($stmt->execute()) {
        $lead_id = (int) $db->lastInsertId();
        $response['success'] = true;
        $response['lead_id'] = $lead_id;
        $response['lead_type'] = $lead_type;
        $response['company_name'] = $company_name;

        // Synchronize with Admin Dashboard Subsystems
        try {
            if ($lead_type === 'rfq') {
                $quoteNum = 'RFQ-' . date('Ymd') . '-' . sprintf('%04d', $lead_id);
                $stmtQ = $db->prepare("
                    INSERT INTO quote_requests (
                        quote_number, company_name, contact_person, email, phone, facility_type,
                        facility_size, current_monthly_bill, target_timeline, estimated_system_size,
                        estimated_installation_cost, estimated_annual_savings, estimated_payback_period,
                        applicable_discounts, installation_address, access_notes, status
                    ) VALUES (
                        :qnum, :cname, :cperson, :email, :phone, :ftype,
                        :fsize, :cbill, :ttime, :ssize,
                        :icost, :asav, :pback,
                        :disc, :addr, :notes, 'new'
                    )
                ");
                $stmtQ->execute([
                    ':qnum' => $quoteNum,
                    ':cname' => $company_name,
                    ':cperson' => $contact_person,
                    ':email' => $corporate_email,
                    ':phone' => $phone_number,
                    ':ftype' => $facility_type,
                    ':fsize' => $facility_size,
                    ':cbill' => $current_monthly_bill,
                    ':ttime' => $target_timeline,
                    ':ssize' => $estimated_system_size,
                    ':icost' => $estimated_installation_cost,
                    ':asav' => $estimated_annual_savings,
                    ':pback' => $estimated_payback_period,
                    ':disc' => $applicable_discounts_json,
                    ':addr' => $property_address,
                    ':notes' => $access_notes
                ]);

                // Immediately create order so it directly appears in the client's "My Orders"
                $orderUserId = null;
                if (function_exists('isLoggedIn') && isLoggedIn()) {
                    $orderUserId = getCurrentUserId();
                }
                if (!$orderUserId && !empty($corporate_email)) {
                    $uStmt = $db->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                    $uStmt->execute([':email' => $corporate_email]);
                    $orderUserId = $uStmt->fetchColumn() ?: null;
                }

                $orderRef = 'APD-INST-' . $quoteNum;
                $orderTotal = (float) $estimated_installation_cost;
                $orderSubtotal = round($orderTotal / 1.12, 2);
                $orderTax = $orderTotal - $orderSubtotal;

                $orderNotes = "Commercial Grid Turnkey Solar Installation\n"
                    . "RFQ Reference: " . $quoteNum . "\n"
                    . "Facility Type: " . ($facility_type ?: 'Commercial Facility') . "\n"
                    . "Facility Area: " . number_format((float)$facility_size, 2) . " sqm\n"
                    . "Current Monthly Electricity Bill: ₱" . number_format((float)$current_monthly_bill, 2) . "\n"
                    . "Estimated System Capacity: " . number_format((float)$estimated_system_size, 2) . " kWp\n"
                    . "Estimated Annual Bill Savings: ₱" . number_format((float)$estimated_annual_savings, 2) . "\n"
                    . "Estimated Payback Period: " . number_format((float)$estimated_payback_period, 1) . " years\n"
                    . "Target Timeline: " . ($target_timeline ?: 'Immediate') . "\n"
                    . ($access_notes ? "Notes: " . $access_notes : "");

                $stmtOrder = $db->prepare("
                    INSERT INTO orders (
                        order_number, user_id, customer_name, customer_email, customer_phone,
                        property_type, payment_method, status, subtotal, tax_amount, total_amount,
                        shipping_address, notes
                    ) VALUES (
                        :ord_num, :uid, :name, :email, :phone,
                        'Commercial', 'Commercial Installation Contract', 'pending', :subtotal, :tax, :total,
                        :addr, :notes
                    )
                ");
                $stmtOrder->execute([
                    ':ord_num' => $orderRef,
                    ':uid' => $orderUserId,
                    ':name' => $company_name . ' (' . $contact_person . ')',
                    ':email' => $corporate_email,
                    ':phone' => $phone_number,
                    ':subtotal' => $orderSubtotal,
                    ':tax' => $orderTax,
                    ':total' => $orderTotal,
                    ':addr' => $property_address,
                    ':notes' => $orderNotes
                ]);
                $newOrderId = (int) $db->lastInsertId();

                $stmtItem = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, quantity, unit_price, total_price
                    ) VALUES (
                        :oid, 'commercial-grids', :pname, 1, :price, :price
                    )
                ");
                $itemLabel = "Commercial Grid Turnkey Solar Installation - " . $company_name . " (" . number_format((float)$estimated_system_size, 2) . " kWp)";
                $stmtItem->execute([
                    ':oid' => $newOrderId,
                    ':pname' => $itemLabel,
                    ':price' => $orderSubtotal
                ]);

                $response['order_id'] = $newOrderId;
                $response['order_number'] = $orderRef;
            } else {
                $bookingRef = 'COMM-' . date('Ymd') . '-' . sprintf('%04d', $lead_id);
                $stmtB = $db->prepare("
                    INSERT INTO service_bookings (
                        booking_reference, customer_name, customer_email, customer_phone,
                        service_type, preferred_date, preferred_time_slot, status, address, access_notes
                    ) VALUES (
                        :bref, :cname, :email, :phone,
                        'consultation', :pdate, :pslot, 'pending', :addr, :notes
                    )
                ");
                $stmtB->execute([
                    ':bref' => $bookingRef,
                    ':cname' => $contact_person ?: $company_name,
                    ':email' => $corporate_email,
                    ':phone' => $phone_number,
                    ':pdate' => $preferred_date,
                    ':pslot' => strtolower($preferred_time_slot ?: 'morning'),
                    ':addr' => $property_address,
                    ':notes' => $access_notes
                ]);
            }
        } catch (Exception $syncEx) {
            error_log('Admin sync error in commercial_consultation.php: ' . $syncEx->getMessage());
        }

        if ($lead_type === 'rfq') {
            $response['message'] = 'Commercial grid proposal successfully generated and added to your Orders! You can review full project pricing and confirm or cancel the request in My Orders.';
            $response['disclaimer'] = 'This is a preliminary quote request. Final pricing requires site assessment and detailed system design.';
        } else {
            $response['message'] = 'Commercial consultation successfully scheduled! Our engineering team will contact you shortly.';
        }
    } else {
        http_response_code(500);
        $response['message'] = 'Unable to save details. Please try again.';
    }
} catch (PDOException $e) {
    error_log('Commercial consultation/RFQ error: ' . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'A server error occurred while processing your request.';
}

echo json_encode($response);
