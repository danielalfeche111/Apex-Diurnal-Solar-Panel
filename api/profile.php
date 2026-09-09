<?php
/**
 * api/profile.php - Apex Diurnal User Profile Management REST API
 * 
 * Supported Methods:
 * - GET: Fetch current authenticated user's profile and default address
 * - PUT / PATCH / POST: Update user's profile details & shipping/billing defaults
 */

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../validation.php';
require_once __DIR__ . '/../User.php';
require_once __DIR__ . '/../philippine_locations.php';

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, PATCH, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication Guard
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentication required to access profile.'
    ]);
    exit;
}

$userId = getCurrentUserId();

try {
    $db = getConnection();
    if (!$db) {
        throw new Exception('Database service unavailable.');
    }

    $user = new User($db);
    if (!$user->findById($userId)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'User profile not found.'
        ]);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'];

    // -------------------------------------------------------------
    // GET: Retrieve Profile Data
    // -------------------------------------------------------------
    if ($method === 'GET') {
        echo json_encode([
            'success' => true,
            'data' => $user->getProfile()
        ]);
        exit;
    }

    // -------------------------------------------------------------
    // PUT / PATCH / POST: Update Profile Data
    // -------------------------------------------------------------
    if (in_array($method, ['PUT', 'PATCH', 'POST'], true)) {
        // Parse input (supports JSON payloads and form-encoded data)
        $rawInput = file_get_contents('php://input');
        $inputData = json_decode($rawInput, true);
        if (!is_array($inputData)) {
            $inputData = $_POST;
        }

        if (empty($inputData)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No profile data provided for update.'
            ]);
            exit;
        }

        // Validate payload using central Validator
        $validator = new Validator($inputData);
        $clean = $validator->sanitized();

        if (isset($clean['full_name'])) {
            $validator->minLength('full_name', 3, 'Full Name must be at least 3 characters.');
            $validator->pattern('full_name', "/^[a-zA-Z\s\.\'\-]+$/", 'Full Name contains invalid characters.');
        }

        if (isset($clean['phone'])) {
            $validator->pattern('phone', '/^\d{11}$/', 'Please enter a valid 11-digit mobile number (e.g., 09171234567).');
        }

        if (isset($clean['street_address'])) {
            $validator->minLength('street_address', 5, 'Street address must be at least 5 characters.');
        }

        if (isset($clean['province'])) {
            $validator->in('province', $consult_provinces, 'Invalid province selected. Please choose from the predefined list.');
        }

        if (isset($clean['city'])) {
            $prov = $clean['province'] ?? $user->province ?? '';
            if ($prov !== '' && isset($consultProvinceCityMap[$prov])) {
                if (!checkCityMatchesProvince($clean['city'], $prov, $consultProvinceCityMap)) {
                    $validator->addError('city', $clean['city'] . ' does not belong to ' . $prov . '. Please select a valid city or municipality.');
                }
            } else {
                $validator->in('city', $consult_cities, 'Invalid city or municipality selected.');
            }
        }

        if (isset($clean['postal_code'])) {
            $validator->pattern('postal_code', '/^\d{4}$/', 'Please enter a valid 4-digit postal code.');
        }

        $errors = $validator->errors();
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => implode(' ', $errors),
                'errors' => $errors
            ]);
            exit;
        }

        // Update database
        $success = $user->updateProfile($clean);
        if (!$success) {
            throw new Exception('Failed to update user profile in database.');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => $user->getProfile()
        ]);
        exit;
    }

    // Any other method
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed.'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
    exit;
}