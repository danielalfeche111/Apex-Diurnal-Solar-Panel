<?php

declare(strict_types=1);

namespace App\Service\Customer;

final class ProfileValidation
{
    public function __construct(private readonly Locations $locations)
    {
    }

    public function validate(array $input, bool $required = false): array
    {
        $errors = [];
        $messages = [
            'full_name' => 'Full Name is required.',
            'phone' => 'Phone number is required.',
            'street_address' => 'Street address is required.',
            'province' => 'Please select a province.',
            'city' => 'Please select a city or municipality.',
            'postal_code' => 'Postal code is required.',
        ];
        foreach ($messages as $field => $message) {
            if ($required && trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = $message;
            }
        }
        $name = trim((string) ($input['full_name'] ?? ''));
        if ($name !== '' && mb_strlen($name) < 3) {
            $errors['full_name'] = 'Full Name must be at least 3 characters.';
        } elseif ($name !== '' && !preg_match("/^[a-zA-Z\s\.\'\-]+$/", $name)) {
            $errors['full_name'] = 'Full Name contains invalid characters.';
        }
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^\d{11}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid 11-digit mobile number (e.g., 09171234567).';
        }
        $street = trim((string) ($input['street_address'] ?? ''));
        if ($street !== '' && mb_strlen($street) < 5) {
            $errors['street_address'] = 'Street address must be at least 5 characters.';
        }
        $province = trim((string) ($input['province'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        if ($province !== '' && !in_array($province, $this->locations->provinces(), true)) {
            $errors['province'] = 'Invalid province selected.';
        }
        if ($city !== '' && $province !== '' && !$this->locations->cityMatchesProvince($city, $province)) {
            $errors['city'] = $city.' does not belong to '.$province.'.';
        } elseif ($city !== '' && $province === '' && !in_array($city, $this->locations->cities(), true)) {
            $errors['city'] = 'Invalid city or municipality selected.';
        }
        $postal = trim((string) ($input['postal_code'] ?? ''));
        if ($postal !== '' && !preg_match('/^\d{4}$/', $postal)) {
            $errors['postal_code'] = 'Please enter a valid 4-digit postal code.';
        }
        return $errors;
    }
}
