<?php

declare(strict_types=1);

namespace App\Service\Customer;

use Doctrine\DBAL\Connection;

final class CustomerAccount
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM users WHERE email = :email LIMIT 1', ['email' => $email]) ?: null;
    }

    public function findById(int $id): ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM users WHERE id = :id LIMIT 1', ['id' => $id]) ?: null;
    }

    public function findAdmin(string $identity): ?array
    {
        return $this->db->fetchAssociative('SELECT * FROM admin_users WHERE username = :identity OR email = :email LIMIT 1', ['identity' => $identity, 'email' => $identity]) ?: null;
    }

    public function create(string $email, string $password): int
    {
        $this->db->insert('users', [
            'email' => filter_var(trim($email), FILTER_SANITIZE_EMAIL),
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateProfile(int $id, array $input): void
    {
        $allowed = ['full_name', 'phone', 'street_address', 'city', 'province', 'postal_code', 'is_default_shipping', 'is_default_billing'];
        $data = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            if (str_starts_with($field, 'is_default_')) {
                $data[$field] = $input[$field] ? 1 : 0;
            } elseif ($field === 'phone') {
                $data[$field] = substr(preg_replace('/\D/', '', (string) $input[$field]), 0, 11);
            } else {
                $data[$field] = trim((string) $input[$field]);
            }
        }
        if ($data !== []) {
            $data['updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $this->db->update('users', $data, ['id' => $id]);
        }
    }

    public function updatePassword(int $id, string $password): void
    {
        $this->db->update('users', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $id]);
    }

    public function profile(array $user): array
    {
        $fields = [
            'full_name' => $user['full_name'] ?? '',
            'email' => $user['email'] ?? '',
            'phone' => substr(preg_replace('/\D/', '', (string) ($user['phone'] ?? '')), 0, 11),
            'street_address' => $user['street_address'] ?? '',
            'city' => $user['city'] ?? '',
            'province' => $user['province'] ?? '',
            'postal_code' => $user['postal_code'] ?? '',
            'is_default_shipping' => isset($user['is_default_shipping']) ? (bool) $user['is_default_shipping'] : true,
            'is_default_billing' => isset($user['is_default_billing']) ? (bool) $user['is_default_billing'] : true,
        ];
        $fields['formatted_address'] = implode(', ', array_filter([$fields['street_address'], $fields['city'], $fields['province']]));
        if ($fields['postal_code'] !== '') {
            $fields['formatted_address'] .= ' '.$fields['postal_code'];
        }
        $required = ['full_name', 'phone', 'street_address', 'province', 'city', 'postal_code'];
        $filled = count(array_filter($required, static fn (string $key): bool => !empty($fields[$key])));
        $fields['is_complete'] = $filled === count($required);
        $fields['completion_percentage'] = (int) round($filled / count($required) * 100);
        return $fields;
    }
}
