<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * UserRepository
 *
 * Handles all database operations for the `users` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 1.1 — Create user account with selected role
 * @see Requirement 1.2 — Detect duplicate email
 * @see Requirement 2.1 — Authenticate user by email
 * @see Requirement 10.1 — Paginated user listing for Admin
 * @see Requirement 10.2 — Deactivate user account
 * @see Requirement 10.3 — Reactivate user account
 * @see Requirement 10.4 — Change user role
 */
class UserRepository extends BaseRepository
{
    /**
     * Insert a new user record and return the auto-generated ID.
     *
     * @param int    $roleId       Role FK (references roles.id).
     * @param string $firstName    User's first name.
     * @param string $lastName     User's last name.
     * @param string $email        Unique login email.
     * @param string $passwordHash Bcrypt-hashed password.
     * @return int The ID of the newly created user.
     * @throws RuntimeException on insertion failure.
     */
    public function create(
        int $roleId,
        string $firstName,
        string $lastName,
        string $email,
        string $passwordHash
    ): int {
        $sql = 'INSERT INTO users (role_id, first_name, last_name, email, password_hash, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, \'active\', NOW(), NOW())';

        $types = 'issss';
        $params = [$roleId, $firstName, $lastName, $email, $passwordHash];

        $this->execute($sql, $types, $params);

        return $this->lastInsertId();
    }

    /**
     * Find a user by email address.
     *
     * @param string $email Email to search for.
     * @return array|null Associative array of the user row, or null if not found.
     */
    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM users WHERE email = ?',
            's',
            [$email]
        );
    }

    /**
     * Find a user by ID, including their role name via JOIN.
     *
     * @param int $id User ID to look up.
     * @return array|null Associative array with user fields + role_name, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT u.*, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?',
            'i',
            [$id]
        );
    }

    /**
     * Update a user's account status (active/inactive).
     *
     * @param int    $id     User ID to update.
     * @param string $status New status value ('active' or 'inactive').
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateStatus(int $id, string $status): int
    {
        return $this->execute(
            'UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?',
            'si',
            [$status, $id]
        );
    }

    /**
     * Update a user's role assignment.
     *
     * @param int $id     User ID to update.
     * @param int $roleId New role ID (references roles.id).
     * @return int Number of affected rows (0 or 1).
     * @throws RuntimeException on execution failure.
     */
    public function updateRole(int $id, int $roleId): int
    {
        return $this->execute(
            'UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?',
            'ii',
            [$roleId, $id]
        );
    }

    /**
     * Get all users with pagination, including role name.
     *
     * @param int $limit  Maximum number of records to return.
     * @param int $offset Number of records to skip.
     * @return array Array of associative arrays (user rows with role_name).
     */
    public function getAll(int $limit = 20, int $offset = 0): array
    {
        return $this->fetchAll(
            'SELECT u.*, r.name AS role_name
             FROM users u
             INNER JOIN roles r ON u.role_id = r.id
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?',
            'ii',
            [$limit, $offset]
        );
    }
}
