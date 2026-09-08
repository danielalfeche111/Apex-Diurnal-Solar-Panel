<?php
/**
 * sanitization.php - Dedicated Sanitization & Filter Functions
 * 
 * Conforms strictly to Week 7 Learning Module:
 * Slide 7: Sanitization & Filter Functions
 * - trim($v)
 * - htmlspecialchars($v)
 * - filter_var($v, FILTER_VALIDATE_EMAIL)
 * - filter_var($v, FILTER_VALIDATE_INT)
 * - filter_var($v, FILTER_SANITIZE_SPECIAL_CHARS)
 * - strip_tags($v)
 */

/**
 * Universal recursive sanitization function for strings or arrays (e.g. $_POST).
 * Trims whitespace and strips tags.
 * Slide 7: trim($v) & strip_tags($v)
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return strip_tags(trim((string)$data));
}

/**
 * Sanitize a single input string by trimming and stripping HTML tags.
 * Slide 7: trim($v) & strip_tags($v)
 */
function sanitize_input($data): string {
    if (is_array($data)) {
        return '';
    }
    return strip_tags(trim((string)$data));
}

/**
 * Escape string for safe HTML rendering to prevent XSS.
 * Slide 7 & Slide 14: htmlspecialchars($v)
 */
function sanitize_output(?string $data): string {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input using FILTER_SANITIZE_SPECIAL_CHARS.
 * Slide 7: filter_var($v, FILTER_SANITIZE_SPECIAL_CHARS)
 */
function sanitize_special_chars(string $data): string {
    return filter_var($data, FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
}

/**
 * Sanitize email input using filter_var.
 * Slide 7: filter_var($v, FILTER_SANITIZE_EMAIL)
 */
function sanitize_email(string $email): string {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL) ?: '';
}

/**
 * Sanitize and cast input to integer.
 * Slide 7: filter_var($v, FILTER_VALIDATE_INT)
 */
function sanitize_int($value, int $default = 0): int {
    $filtered = filter_var($value, FILTER_VALIDATE_INT);
    return ($filtered !== false) ? $filtered : $default;
}
?>
