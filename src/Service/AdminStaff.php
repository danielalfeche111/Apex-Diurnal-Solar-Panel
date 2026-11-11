<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;

final class AdminStaff
{
    private const DEFAULT_ENGINEERS = [
        [
            'username' => 'Engr. Mark Santos (Lead Solar Engineer)',
            'email' => 'mark.santos@apexdiurnal.com',
            'role' => 'staff',
        ],
        [
            'username' => 'Engr. David Reyes (Project Lead)',
            'email' => 'david.reyes@apexdiurnal.com',
            'role' => 'staff',
        ],
        [
            'username' => 'Lead Tech Alex Cruz (Site Operations)',
            'email' => 'alex.cruz@apexdiurnal.com',
            'role' => 'staff',
        ],
        [
            'username' => 'Engr. Juan Dela Cruz (Electrical Systems)',
            'email' => 'juan.delacruz@apexdiurnal.com',
            'role' => 'staff',
        ],
    ];

    public function __construct(private readonly Connection $database)
    {
    }

    public function ensureSeeded(): void
    {
        try {
            $autoIncrement = (int) $this->database->fetchOne(
                "SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_users'"
            );
            $totalCount = (int) $this->database->fetchOne('SELECT COUNT(*) FROM admin_users');
            $staffCount = (int) $this->database->fetchOne("SELECT COUNT(*) FROM admin_users WHERE role = 'staff'");
            if ($staffCount === 0 && $totalCount <= 2 && $autoIncrement <= 3) {
                $defaultHash = password_hash('staff123', PASSWORD_DEFAULT);
                foreach (self::DEFAULT_ENGINEERS as $eng) {
                    $exists = $this->database->fetchOne(
                        'SELECT id FROM admin_users WHERE username = :u OR email = :e LIMIT 1',
                        ['u' => $eng['username'], 'e' => $eng['email']]
                    );
                    if (!$exists) {
                        $this->database->insert('admin_users', [
                            'username' => $eng['username'],
                            'email' => $eng['email'],
                            'password_hash' => $defaultHash,
                            'role' => $eng['role'],
                        ]);
                    }
                }
            }
        } catch (\Throwable) {
        }
    }

    public function all(string $roleFilter = 'all', string $search = ''): array
    {
        $this->ensureSeeded();
        $where = [];
        $params = [];

        if (in_array($roleFilter, ['superadmin', 'manager', 'staff'], true)) {
            $where[] = 'u.role = :role';
            $params['role'] = $roleFilter;
        }
        if ($search !== '') {
            $where[] = '(u.username LIKE :s1 OR u.email LIKE :s2 OR u.role LIKE :s3)';
            $params['s1'] = '%'.$search.'%';
            $params['s2'] = '%'.$search.'%';
            $params['s3'] = '%'.$search.'%';
        }

        $whereSql = $where !== [] ? 'WHERE '.implode(' AND ', $where) : '';
        $rows = $this->database->fetchAllAssociative(
            "SELECT u.id, u.username, u.email, u.role, u.created_at, u.updated_at,
                    (SELECT COUNT(*) FROM quote_requests q WHERE q.installation_head = u.username AND q.status NOT IN ('cancelled', 'rejected', 'expired')) AS quote_assignments,
                    (SELECT COUNT(*) FROM service_bookings b WHERE b.assigned_technician = u.username AND b.status != 'cancelled') AS booking_assignments
             FROM admin_users u
             $whereSql
             ORDER BY FIELD(u.role, 'superadmin', 'manager', 'staff'), u.id ASC",
            $params
        );

        return $rows;
    }

    public function metrics(): array
    {
        $this->ensureSeeded();

        return [
            'kpi_total' => (int) $this->database->fetchOne('SELECT COUNT(*) FROM admin_users'),
            'kpi_staff' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM admin_users WHERE role = 'staff'"),
            'kpi_admins' => (int) $this->database->fetchOne("SELECT COUNT(*) FROM admin_users WHERE role IN ('superadmin', 'manager')"),
            'kpi_assigned_projects' => (int) $this->database->fetchOne(
                "SELECT (SELECT COUNT(*) FROM quote_requests WHERE installation_head IS NOT NULL AND installation_head != '' AND status NOT IN ('cancelled', 'rejected', 'expired'))
                      + (SELECT COUNT(*) FROM service_bookings WHERE assigned_technician IS NOT NULL AND assigned_technician != '' AND LOWER(assigned_technician) != 'unassigned' AND status != 'cancelled')"
            ),
        ];
    }

    /** @return list<string> */
    public function engineerNames(): array
    {
        $this->ensureSeeded();
        $names = [];
        try {
            $rows = $this->database->fetchFirstColumn(
                "SELECT username FROM admin_users WHERE role = 'staff' ORDER BY id ASC"
            );
            foreach ($rows as $row) {
                $name = trim((string) $row);
                if ($name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        } catch (\Throwable) {
        }

        return $names;
    }

    public function create(string $username, string $email, string $role, string $password = ''): array
    {
        $username = trim($username);
        $email = trim($email);
        $role = strtolower(trim($role));
        $password = trim($password);

        if ($username === '') {
            throw new \InvalidArgumentException('Personnel name or username is required.');
        }
        if (mb_strlen($username) > 50) {
            throw new \InvalidArgumentException('Personnel name/username must not exceed 50 characters.');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }
        if (!in_array($role, ['staff', 'manager', 'superadmin'], true)) {
            $role = 'staff';
        }
        if ($password !== '' && mb_strlen($password) < 6) {
            throw new \InvalidArgumentException('Password must be at least 6 characters long.');
        }
        $effectivePassword = $password !== '' ? $password : 'staff123';

        $dupUser = $this->database->fetchOne('SELECT id FROM admin_users WHERE LOWER(username) = LOWER(:u) LIMIT 1', ['u' => $username]);
        if ($dupUser) {
            throw new \InvalidArgumentException("A staff member or engineer named \"$username\" already exists.");
        }
        $dupEmail = $this->database->fetchOne('SELECT id FROM admin_users WHERE LOWER(email) = LOWER(:e) LIMIT 1', ['e' => $email]);
        if ($dupEmail) {
            throw new \InvalidArgumentException("The email address \"$email\" is already registered to another staff member.");
        }

        $this->database->insert('admin_users', [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($effectivePassword, PASSWORD_DEFAULT),
            'role' => $role,
        ]);

        return [
            'id' => (int) $this->database->lastInsertId(),
            'message' => "Personnel \"$username\" has been added successfully.",
        ];
    }

    public function update(int $id, string $username, string $email, string $role, string $password = ''): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid staff record ID.');
        }
        $existing = $this->database->fetchAssociative('SELECT * FROM admin_users WHERE id = :id', ['id' => $id]);
        if (!$existing) {
            throw new \RuntimeException('Staff or engineer record was not found.');
        }

        $username = trim($username);
        $email = trim($email);
        $role = strtolower(trim($role));
        $password = trim($password);

        if ($username === '') {
            throw new \InvalidArgumentException('Personnel name or username is required.');
        }
        if (mb_strlen($username) > 50) {
            throw new \InvalidArgumentException('Personnel name/username must not exceed 50 characters.');
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }
        if (!in_array($role, ['staff', 'manager', 'superadmin'], true)) {
            $role = (string) $existing['role'];
        }
        if ($password !== '' && mb_strlen($password) < 6) {
            throw new \InvalidArgumentException('New password must be at least 6 characters long.');
        }

        $dupUser = $this->database->fetchOne(
            'SELECT id FROM admin_users WHERE LOWER(username) = LOWER(:u) AND id != :id LIMIT 1',
            ['u' => $username, 'id' => $id]
        );
        if ($dupUser) {
            throw new \InvalidArgumentException("Another staff member or engineer already uses \"$username\".");
        }
        $dupEmail = $this->database->fetchOne(
            'SELECT id FROM admin_users WHERE LOWER(email) = LOWER(:e) AND id != :id LIMIT 1',
            ['e' => $email, 'id' => $id]
        );
        if ($dupEmail) {
            throw new \InvalidArgumentException("Another staff member already uses the email \"$email\".");
        }

        return $this->database->transactional(function () use ($id, $existing, $username, $email, $role, $password): array {
            $updateData = [
                'username' => $username,
                'email' => $email,
                'role' => $role,
            ];
            if ($password !== '') {
                $updateData['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }
            $this->database->update('admin_users', $updateData, ['id' => $id]);

            $oldName = (string) $existing['username'];
            if ($oldName !== '' && $oldName !== $username) {
                $this->database->executeStatement(
                    'UPDATE quote_requests SET installation_head = :new WHERE installation_head = :old',
                    ['new' => $username, 'old' => $oldName]
                );
                $this->database->executeStatement(
                    'UPDATE orders SET installation_head = :new WHERE installation_head = :old',
                    ['new' => $username, 'old' => $oldName]
                );
                $this->database->executeStatement(
                    'UPDATE service_bookings SET assigned_technician = :new WHERE assigned_technician = :old',
                    ['new' => $username, 'old' => $oldName]
                );
            }

            return [
                'id' => $id,
                'message' => "Details for \"$username\" have been updated successfully.",
            ];
        });
    }

    public function delete(int $id, int $currentAdminId): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid staff record ID.');
        }
        if ($id === $currentAdminId) {
            throw new \InvalidArgumentException('You cannot delete your own active administrator account.');
        }

        $existing = $this->database->fetchAssociative('SELECT * FROM admin_users WHERE id = :id', ['id' => $id]);
        if (!$existing) {
            throw new \RuntimeException('Staff or engineer record was not found.');
        }

        return $this->database->transactional(function () use ($id, $existing): array {
            $name = (string) $existing['username'];
            if ($name !== '') {
                $this->database->executeStatement(
                    'UPDATE quote_requests SET installation_head = NULL WHERE installation_head = :name',
                    ['name' => $name]
                );
                $this->database->executeStatement(
                    'UPDATE orders SET installation_head = NULL WHERE installation_head = :name',
                    ['name' => $name]
                );
                $this->database->executeStatement(
                    'UPDATE service_bookings SET assigned_technician = NULL WHERE assigned_technician = :name',
                    ['name' => $name]
                );
            }

            $this->database->delete('admin_users', ['id' => $id]);

            return [
                'id' => $id,
                'message' => "Personnel \"$name\" has been deleted.",
            ];
        });
    }

    public function profile(int $id, string $fallbackUsername = '', string $fallbackEmail = '', string $fallbackRole = 'staff'): array
    {
        $this->ensureSeeded();
        $row = null;
        if ($id > 0) {
            $row = $this->database->fetchAssociative(
                "SELECT u.id, u.username, u.email, u.role, u.created_at, u.updated_at,
                        (SELECT COUNT(*) FROM quote_requests q WHERE q.installation_head = u.username AND q.status NOT IN ('cancelled', 'rejected', 'expired')) AS quote_assignments,
                        (SELECT COUNT(*) FROM service_bookings b WHERE b.assigned_technician = u.username AND b.status != 'cancelled') AS booking_assignments
                 FROM admin_users u
                 WHERE u.id = :id
                 LIMIT 1",
                ['id' => $id]
            );
        }
        if ((!$row || !is_array($row)) && $fallbackEmail !== '') {
            $row = $this->database->fetchAssociative(
                "SELECT u.id, u.username, u.email, u.role, u.created_at, u.updated_at,
                        (SELECT COUNT(*) FROM quote_requests q WHERE q.installation_head = u.username AND q.status NOT IN ('cancelled', 'rejected', 'expired')) AS quote_assignments,
                        (SELECT COUNT(*) FROM service_bookings b WHERE b.assigned_technician = u.username AND b.status != 'cancelled') AS booking_assignments
                 FROM admin_users u
                 WHERE LOWER(u.email) = LOWER(:email)
                 LIMIT 1",
                ['email' => $fallbackEmail]
            );
        }

        if (is_array($row)) {
            $createdCount = (int) $this->database->fetchOne(
                "SELECT COUNT(DISTINCT product_id) FROM inventory_transactions WHERE created_by = :id AND (reference_id = 'CATALOG-EDIT' OR notes LIKE 'Opening stock%')",
                ['id' => (int) $row['id']]
            );
            $row['created_records'] = $createdCount;
            return $row;
        }

        return [
            'id' => $id,
            'username' => $fallbackUsername !== '' ? $fallbackUsername : 'Admin',
            'email' => $fallbackEmail,
            'role' => $fallbackRole,
            'created_at' => null,
            'updated_at' => null,
            'quote_assignments' => 0,
            'booking_assignments' => 0,
            'created_records' => 0,
        ];
    }

    public function changeOwnPassword(int $id, string $currentPassword, string $newPassword, string $confirmPassword): string
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid account session.');
        }
        if ($currentPassword === '') {
            throw new \InvalidArgumentException('Current password is required.');
        }
        if ($newPassword === '') {
            throw new \InvalidArgumentException('New password is required.');
        }
        if (mb_strlen($newPassword) < 6) {
            throw new \InvalidArgumentException('New password must be at least 6 characters long.');
        }
        if ($confirmPassword === '' || $newPassword !== $confirmPassword) {
            throw new \InvalidArgumentException('New password and confirmation password do not match.');
        }

        $account = $this->database->fetchAssociative('SELECT id, username, email, role, password_hash FROM admin_users WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$account) {
            throw new \RuntimeException('Account record was not found.');
        }
        if (strtolower((string) ($account['role'] ?? '')) !== 'staff') {
            throw new \RuntimeException('Only staff accounts are permitted to change their own password here.');
        }
        if (!password_verify($currentPassword, (string) $account['password_hash'])) {
            throw new \InvalidArgumentException('Current password is incorrect.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->database->update('admin_users', ['password_hash' => $newHash], ['id' => (int) $account['id']]);

        if (!empty($account['email'])) {
            $this->database->executeStatement(
                'UPDATE users SET password_hash = :hash WHERE LOWER(email) = LOWER(:email)',
                ['hash' => $newHash, 'email' => (string) $account['email']]
            );
        }

        return 'Your password has been updated successfully.';
    }
}

