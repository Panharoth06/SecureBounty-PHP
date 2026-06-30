<?php

/**
 * BaseRepository
 *
 * Provides a foundation for all repository classes with MySQLi connection
 * injection and parameterized query helpers to prevent SQL injection.
 *
 * @see Requirement 14.2 — Parameterized queries for all database operations
 */
class BaseRepository
{
    protected mysqli $conn;

    /**
     * @param mysqli $conn  Active MySQLi connection instance.
     */
    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Execute a parameterized query and return the result set.
     *
     * @param string $sql    SQL statement with `?` placeholders.
     * @param string $types  Type string for bind_param (e.g. "ssi").
     * @param array  $params Values to bind to the placeholders.
     * @return mysqli_result
     * @throws RuntimeException on query failure.
     */
    protected function query(string $sql, string $types = '', array $params = []): mysqli_result
    {
        $stmt = $this->prepareAndBind($sql, $types, $params);

        try {
            if (!$stmt->execute()) {
                throw new RuntimeException('Query execution failed: ' . $stmt->error);
            }

            $result = $stmt->get_result();

            if ($result === false) {
                throw new RuntimeException('Failed to retrieve result set: ' . $stmt->error);
            }

            return $result;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Execute a parameterized statement that does not return a result set
     * (INSERT, UPDATE, DELETE).
     *
     * @param string $sql    SQL statement with `?` placeholders.
     * @param string $types  Type string for bind_param.
     * @param array  $params Values to bind.
     * @return int           Number of affected rows.
     * @throws RuntimeException on execution failure.
     */
    protected function execute(string $sql, string $types = '', array $params = []): int
    {
        $stmt = $this->prepareAndBind($sql, $types, $params);

        try {
            if (!$stmt->execute()) {
                throw new RuntimeException('Statement execution failed: ' . $stmt->error);
            }

            return $stmt->affected_rows;
        } finally {
            $stmt->close();
        }
    }

    /**
     * Execute a parameterized query and return a single row as an associative array.
     *
     * @param string $sql    SQL statement with `?` placeholders.
     * @param string $types  Type string for bind_param.
     * @param array  $params Values to bind.
     * @return array|null    Associative array of the first row, or null if no rows.
     * @throws RuntimeException on query failure.
     */
    protected function fetchOne(string $sql, string $types = '', array $params = []): ?array
    {
        $result = $this->query($sql, $types, $params);
        $row = $result->fetch_assoc();
        $result->free();

        return $row ?: null;
    }

    /**
     * Execute a parameterized query and return all rows as an array of associative arrays.
     *
     * @param string $sql    SQL statement with `?` placeholders.
     * @param string $types  Type string for bind_param.
     * @param array  $params Values to bind.
     * @return array         Array of associative arrays.
     * @throws RuntimeException on query failure.
     */
    protected function fetchAll(string $sql, string $types = '', array $params = []): array
    {
        $result = $this->query($sql, $types, $params);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();

        return $rows;
    }

    /**
     * Get the last auto-increment ID inserted via this connection.
     *
     * @return int
     */
    protected function lastInsertId(): int
    {
        return (int) $this->conn->insert_id;
    }

    /**
     * Prepare a statement and bind parameters.
     *
     * @param string $sql    SQL with `?` placeholders.
     * @param string $types  Type string for bind_param.
     * @param array  $params Values to bind.
     * @return mysqli_stmt
     * @throws RuntimeException if prepare fails.
     */
    private function prepareAndBind(string $sql, string $types, array $params): mysqli_stmt
    {
        $stmt = $this->conn->prepare($sql);

        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->conn->error);
        }

        if ($types !== '' && count($params) > 0) {
            $stmt->bind_param($types, ...$params);
        }

        return $stmt;
    }
}
