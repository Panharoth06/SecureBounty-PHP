<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * AttachmentRepository
 *
 * Handles all database operations for the `attachments` table.
 * Extends BaseRepository to leverage parameterized query helpers.
 *
 * @see Requirement 7.3 — Validate file type and size, store Attachment linked to Report
 * @see Requirement 7.4 — Restrict allowed attachment file types
 * @see Requirement 7.5 — Restrict each Attachment to maximum 10 MB
 */
class AttachmentRepository extends BaseRepository
{
    /**
     * Insert a new attachment record and return the new ID.
     *
     * @param int    $reportId Report ID (FK → reports.id).
     * @param string $fileName Original filename.
     * @param string $filePath Server storage path.
     * @param string $fileType File extension (png, jpg, gif, pdf, txt, zip).
     * @param int    $fileSize File size in bytes.
     * @return int The ID of the newly created attachment.
     * @throws RuntimeException on insertion failure.
     */
    public function create(int $reportId, string $fileName, string $filePath, string $fileType, int $fileSize): int
    {
        $sql = 'INSERT INTO attachments (report_id, file_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, NOW())';

        $this->execute($sql, 'isssi', [$reportId, $fileName, $filePath, $fileType, $fileSize]);

        return $this->lastInsertId();
    }

    /**
     * Find all attachments for a given report.
     *
     * @param int $reportId Report ID.
     * @return array Array of associative arrays (attachment rows).
     */
    public function findByReportId(int $reportId): array
    {
        return $this->fetchAll(
            'SELECT * FROM attachments WHERE report_id = ? ORDER BY uploaded_at ASC',
            'i',
            [$reportId]
        );
    }

    /**
     * Find a single attachment by its ID.
     *
     * @param int $id Attachment ID.
     * @return array|null Associative array of the attachment row, or null if not found.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM attachments WHERE id = ?',
            'i',
            [$id]
        );
    }
}
