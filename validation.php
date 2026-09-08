<?php
/**
 * validation.php - Centralized Validation & Sanitization Module
 * 
 * Implements Week 7 Learning Module:
 * - Slide 5: Reading Submitted Values Safely
 * - Slide 6: Core Validation Techniques (Required, Format, Length, Pattern)
 * - Slide 7: Sanitization & Filter Functions (trim, htmlspecialchars, filter_var)
 * - Slide 8: Putting Validation Together ($errors array, collect, validate, sanitize)
 */

// Load dedicated sanitization module (Slide 7)
require_once __DIR__ . '/sanitization.php';

// =============================================================================
// PROCEDURAL VALIDATION FUNCTIONS (Slide 6 & 8)
// =============================================================================

/**
 * Validate that a field is not empty.
 * Slide 6: empty($_POST['x'])
 */
function validate_required($value, string $field, array &$errors, ?string $message = null): bool {
    $str = is_string($value) ? trim($value) : $value;
    if ($str === '' || $str === null || (is_array($str) && empty($str))) {
        $errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' is required.';
        return false;
    }
    return true;
}

/**
 * Validate email address shape.
 * Slide 6: filter_var($v, FILTER_VALIDATE_EMAIL)
 */
function validate_email($email, string $field, array &$errors, ?string $message = null): bool {
    $clean = trim((string)$email);
    if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
        $errors[$field] = $message ?? 'Please enter a valid email address.';
        return false;
    }
    return true;
}

/**
 * Validate string length within min/max bounds.
 * Slide 6: strlen($v) <= 50
 */
function validate_length($value, ?int $min, ?int $max, string $field, array &$errors, ?string $message = null): bool {
    $len = mb_strlen(trim((string)$value));
    if ($min !== null && $len < $min) {
        $errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.";
        return false;
    }
    if ($max !== null && $len > $max) {
        $errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . " must not exceed {$max} characters.";
        return false;
    }
    return true;
}

/**
 * Validate regex pattern match.
 * Slide 6: preg_match('/^[A-Za-z]+$/', $v)
 */
function validate_pattern($value, string $pattern, string $field, array &$errors, ?string $message = null): bool {
    if (!preg_match($pattern, (string)$value)) {
        $errors[$field] = $message ?? ucfirst(str_replace('_', ' ', $field)) . ' format is invalid.';
        return false;
    }
    return true;
}

// =============================================================================
// 3. CENTRALIZED VALIDATOR CLASS (Slide 8 - Putting Validation Together)
// =============================================================================

class Validator {
    private array $data = [];
    private array $sanitized = [];
    private array $errors = [];

    /**
     * Initialize validator with request data (defaults to $_POST).
     * Slide 5: reading submitted values safely
     */
    public function __construct(?array $source = null) {
        $raw = $source ?? $_POST;
        foreach ($raw as $key => $val) {
            $this->data[$key] = $val;
            if (is_string($val)) {
                $this->sanitized[$key] = trim($val);
            } else {
                $this->sanitized[$key] = $val;
            }
        }
    }

    /**
     * Get raw or sanitized value with null coalescing fallback.
     */
    public function get(string $key, $default = '') {
        return $this->sanitized[$key] ?? $default;
    }

    /**
     * Validate one or multiple required fields.
     * Supports: required('email') or required(['email' => 'Custom message', 'name'])
     */
    public function required($fields, ?string $defaultMessage = null): self {
        $fieldsList = is_array($fields) ? $fields : [$fields];
        foreach ($fieldsList as $key => $val) {
            $fieldName = is_int($key) ? $val : $key;
            $msg = is_string($val) && !is_int($key) ? $val : $defaultMessage;
            $value = $this->sanitized[$fieldName] ?? '';
            
            if ($value === '' || $value === null || (is_array($value) && empty($value))) {
                $this->addError($fieldName, $msg ?? (ucfirst(str_replace('_', ' ', $fieldName)) . ' is required.'));
            }
        }
        return $this;
    }

    /**
     * Validate email format with filter_var.
     */
    public function email(string $field, ?string $message = null): self {
        $val = $this->sanitized[$field] ?? '';
        if ($val !== '' && !filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, $message ?? 'Please enter a valid email address.');
        }
        return $this;
    }

    /**
     * Validate minimum length.
     */
    public function minLength(string $field, int $min, ?string $message = null): self {
        $val = $this->sanitized[$field] ?? '';
        if ($val !== '' && mb_strlen($val) < $min) {
            $this->addError($field, $message ?? ucfirst(str_replace('_', ' ', $field)) . " must be at least {$min} characters.");
        }
        return $this;
    }

    /**
     * Validate maximum length.
     */
    public function maxLength(string $field, int $max, ?string $message = null): self {
        $val = $this->sanitized[$field] ?? '';
        if ($val !== '' && mb_strlen($val) > $max) {
            $this->addError($field, $message ?? ucfirst(str_replace('_', ' ', $field)) . " cannot exceed {$max} characters.");
        }
        return $this;
    }

    /**
     * Validate custom regex pattern.
     */
    public function pattern(string $field, string $regex, ?string $message = null): self {
        $val = $this->sanitized[$field] ?? '';
        if ($val !== '' && !preg_match($regex, $val)) {
            $this->addError($field, $message ?? ucfirst(str_replace('_', ' ', $field)) . ' contains invalid characters.');
        }
        return $this;
    }

    /**
     * Validate that value exists in allowed set.
     */
    public function in(string $field, array $allowed, ?string $message = null): self {
        $val = $this->sanitized[$field] ?? '';
        if ($val !== '' && !in_array($val, $allowed, true)) {
            $this->addError($field, $message ?? 'Invalid selection.');
        }
        return $this;
    }

    /**
     * Validate field matches another field (e.g. password confirmation).
     */
    public function matches(string $field, string $matchField, ?string $message = null): self {
        $val1 = $this->data[$field] ?? '';
        $val2 = $this->data[$matchField] ?? '';
        if ($val1 !== '' && $val1 !== $val2) {
            $this->addError($matchField, $message ?? 'Fields do not match.');
        }
        return $this;
    }

    /**
     * Manually record an error.
     */
    public function addError(string $field, string $message): self {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    /**
     * Check if validation passed.
     */
    public function passes(): bool {
        return empty($this->errors);
    }

    /**
     * Check if validation failed.
     */
    public function fails(): bool {
        return !empty($this->errors);
    }

    /**
     * Return all accumulated errors.
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Return first error message, if any.
     */
    public function firstError(): ?string {
        if (empty($this->errors)) {
            return null;
        }
        return reset($this->errors);
    }

    /**
     * Return all sanitized data.
     */
    public function sanitized(): array {
        return $this->sanitized;
    }
}
?>
