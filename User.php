<?php
require_once __DIR__ . '/config.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $email;
    public $password_hash;
    public $full_name;
    public $phone;
    public $street_address;
    public $city;
    public $province;
    public $postal_code;
    public $is_default_shipping;
    public $is_default_billing;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Create a new user
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (email, password_hash) VALUES (:email, :password_hash)";
        $stmt = $this->conn->prepare($query);

        // Sanitize email (password_hash is created via password_hash() and must not be altered)
        $this->email = filter_var(trim($this->email), FILTER_SANITIZE_EMAIL);

        // Bind values (Slide 10)
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password_hash", $this->password_hash);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    private function populateFromRow(array $row): void {
        $this->id = (int)$row['id'];
        $this->email = $row['email'];
        $this->password_hash = $row['password_hash'];
        $this->full_name = $row['full_name'] ?? null;
        $this->phone = $row['phone'] ?? null;
        $this->street_address = $row['street_address'] ?? null;
        $this->city = $row['city'] ?? null;
        $this->province = $row['province'] ?? null;
        $this->postal_code = $row['postal_code'] ?? null;
        $this->is_default_shipping = isset($row['is_default_shipping']) ? (bool)$row['is_default_shipping'] : true;
        $this->is_default_billing = isset($row['is_default_billing']) ? (bool)$row['is_default_billing'] : true;
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }

    // Find user by email
    public function findByEmail($email) {
        $query = "SELECT id, email, password_hash, full_name, phone, street_address, city, province, postal_code, is_default_shipping, is_default_billing, created_at, updated_at FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->populateFromRow($row);
            return true;
        }

        return false;
    }

    // Find user by ID
    public function findById($id) {
        $query = "SELECT id, email, password_hash, full_name, phone, street_address, city, province, postal_code, is_default_shipping, is_default_billing, created_at, updated_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $id);

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->populateFromRow($row);
            return true;
        }

        return false;
    }

    /**
     * Update user profile & default address details
     * @param array $data ['full_name', 'phone', 'street_address', 'city', 'province', 'postal_code', 'is_default_shipping', 'is_default_billing']
     * @return bool
     */
    public function updateProfile(array $data): bool {
        $allowedFields = [
            'full_name', 'phone', 'street_address', 'city', 'province', 'postal_code',
            'is_default_shipping', 'is_default_billing'
        ];
        $setClauses = [];
        $params = [':id' => $this->id];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $placeholder = ':' . $field;
                $setClauses[] = "$field = $placeholder";
                $val = $data[$field];
                if ($field === 'is_default_shipping' || $field === 'is_default_billing') {
                    $params[$placeholder] = $val ? 1 : 0;
                    $this->$field = (bool)$val;
                } else {
                    $trimmed = ($val !== null) ? trim((string)$val) : null;
                    if ($field === 'phone' && $trimmed !== null) {
                        $trimmed = substr(preg_replace('/\D/', '', $trimmed), 0, 11);
                    }
                    $params[$placeholder] = $trimmed;
                    $this->$field = $trimmed;
                }
            }
        }

        if (empty($setClauses)) {
            return true;
        }

        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $setClauses) . ", updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Get user profile details as an associative array
     * @return array
     */
    public function getProfile(): array {
        $fields = [
            'full_name'           => $this->full_name ?? '',
            'email'               => $this->email ?? '',
            'phone'               => substr(preg_replace('/\D/', '', $this->phone ?? ''), 0, 11),
            'street_address'      => $this->street_address ?? '',
            'city'                => $this->city ?? '',
            'province'            => $this->province ?? '',
            'postal_code'         => $this->postal_code ?? '',
            'is_default_shipping' => isset($this->is_default_shipping) ? (bool)$this->is_default_shipping : true,
            'is_default_billing'  => isset($this->is_default_billing) ? (bool)$this->is_default_billing : true,
        ];

        // Format composite delivery address
        $addressParts = array_filter([$fields['street_address'], $fields['city'], $fields['province']]);
        $formattedAddress = implode(', ', $addressParts);
        if ($fields['postal_code'] !== '') {
            $formattedAddress .= ' ' . $fields['postal_code'];
        }
        $fields['formatted_address'] = $formattedAddress;

        // Calculate completeness
        $requiredForCheckout = ['full_name', 'phone', 'street_address', 'province', 'city', 'postal_code'];
        $filled = 0;
        foreach ($requiredForCheckout as $reqKey) {
            if (!empty($fields[$reqKey])) {
                $filled++;
            }
        }
        $fields['is_complete'] = ($filled === count($requiredForCheckout));
        $fields['completion_percentage'] = (int)round(($filled / count($requiredForCheckout)) * 100);

        return $fields;
    }

    // Update password
    public function updatePassword($new_password_hash) {
        $query = "UPDATE " . $this->table_name . " SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        // Bind values (hash should not be altered with htmlspecialchars)
        $stmt->bindParam(":password_hash", $new_password_hash);
        $stmt->bindParam(":id", $this->id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Validate password (used during login)
    public function validatePassword($password) {
        return password_verify($password, $this->password_hash);
    }

    // Get all users (for testing purposes only)
    public static function getAllUsers($db) {
        $query = "SELECT id, email, created_at FROM users ORDER BY id";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>