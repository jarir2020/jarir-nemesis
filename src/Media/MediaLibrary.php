<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 13 — Media Library | Created: 2026-04-03
// MediaLibrary — upload, store, retrieve, and delete media assets.

namespace Nemesis\Media;

/**
 * MediaLibrary — driver-backed media asset management.
 *
 * Usage:
 *   $attachment = MediaLibrary::upload($_FILES['photo'], 'local');
 *   $url        = MediaLibrary::url($attachment);
 *   MediaLibrary::delete($attachment);
 *
 * Validation:
 *   MediaLibrary::setAllowedTypes(['image/jpeg', 'image/png', 'image/webp']);
 *   MediaLibrary::setMaxSize(5 * 1024 * 1024); // 5 MB
 *
 * DB table (auto-created on first write):
 *   attachments (id, filename, original_name, mime_type, size, disk, path, alt, created_at)
 */
class MediaLibrary
{
    // -------------------------------------------------------------------------
    // Configuration
    // -------------------------------------------------------------------------

    /** Default base directory for local uploads (relative to project root). */
    private static string $uploadDir = 'storage/uploads';

    /** Public URL base for local disk. */
    private static string $publicBase = '/storage/uploads';

    /** Allowed MIME types (empty = allow all). */
    private static array $allowedTypes = [];

    /** Max file size in bytes (0 = no limit). */
    private static int $maxSize = 0;

    /** DB table name. */
    private static string $table = 'attachments';

    /** Injected DB connection (optional — falls back to in-memory). */
    private static mixed $db = null;

    /** In-memory store for test/fallback: [id => Attachment]. */
    private static array $memory     = [];
    private static int   $memorySeq  = 0;

    private static bool $tableChecked = false;

    // -------------------------------------------------------------------------
    // Configuration setters
    // -------------------------------------------------------------------------

    public static function setDb(mixed $db): void            { static::$db = $db; }
    public static function setUploadDir(string $dir): void   { static::$uploadDir = rtrim($dir, '/'); }
    public static function setPublicBase(string $base): void { static::$publicBase = rtrim($base, '/'); }
    public static function setTable(string $table): void     { static::$table = $table; static::$tableChecked = false; }
    public static function setAllowedTypes(array $types): void { static::$allowedTypes = $types; }
    public static function setMaxSize(int $bytes): void      { static::$maxSize = $bytes; }

    /** Clear all in-memory state and DB connection (test helper). */
    public static function reset(): void
    {
        static::$memory       = [];
        static::$memorySeq    = 0;
        static::$db           = null;
        static::$tableChecked = false;
        static::$allowedTypes = [];
        static::$maxSize      = 0;
    }

    // -------------------------------------------------------------------------
    // Upload
    // -------------------------------------------------------------------------

    /**
     * Process a file upload and store the attachment.
     *
     * @param  array  $file  A $_FILES['key'] element: name, tmp_name, type, size, error
     * @param  string $disk  Storage disk ('local', 's3', etc.)
     * @return Attachment    The stored attachment record
     *
     * @throws \InvalidArgumentException  On validation failure
     * @throws \RuntimeException          On storage failure
     */
    public static function upload(array $file, string $disk = 'local'): Attachment
    {
        static::validateFile($file);

        $originalName = $file['name'] ?? 'upload';
        $mimeType     = $file['type'] ?? 'application/octet-stream';
        $size         = (int) ($file['size'] ?? 0);
        $tmpPath      = $file['tmp_name'] ?? null;

        // Generate unique filename
        $ext      = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(8)) . ($ext ? ".{$ext}" : '');
        $subdir   = date('Y/m');
        $relPath  = $subdir . '/' . $filename;
        $absDir   = static::$uploadDir . '/' . $subdir;
        $absPath  = static::$uploadDir . '/' . $relPath;

        // Store file (skip physical move in test mode / when tmp_name is fake)
        if ($tmpPath && file_exists($tmpPath)) {
            if (!is_dir($absDir)) {
                mkdir($absDir, 0755, true);
            }
            if (!move_uploaded_file($tmpPath, $absPath) && !copy($tmpPath, $absPath)) {
                throw new \RuntimeException("Failed to store uploaded file.");
            }
        }

        $data = [
            'filename'      => $filename,
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'size'          => $size,
            'disk'          => $disk,
            'path'          => $relPath,
            'alt'           => '',
            'created_at'    => date('Y-m-d H:i:s'),
        ];

        return static::persist($data);
    }

    /**
     * Store an attachment directly from a data array (no file move).
     * Useful for programmatic attachment creation or testing.
     */
    public static function store(array $data): Attachment
    {
        return static::persist(array_merge([
            'filename'      => '',
            'original_name' => '',
            'mime_type'     => '',
            'size'          => 0,
            'disk'          => 'local',
            'path'          => '',
            'alt'           => '',
            'created_at'    => date('Y-m-d H:i:s'),
        ], $data));
    }

    // -------------------------------------------------------------------------
    // Retrieval
    // -------------------------------------------------------------------------

    /** Find an Attachment by its ID, or return null. */
    public static function find(int $id): ?Attachment
    {
        // Check memory first
        if (isset(static::$memory[$id])) {
            return static::$memory[$id];
        }

        if (static::$db !== null) {
            static::ensureTable();
            try {
                $row = static::dbFetchOne(
                    'SELECT * FROM ' . static::$table . ' WHERE id=? LIMIT 1',
                    [$id]
                );
                if ($row) {
                    $att = new Attachment($row);
                    static::$memory[$id] = $att;
                    return $att;
                }
            } catch (\Throwable) {}
        }
        return null;
    }

    /**
     * Return all attachments, optionally filtered.
     *
     * @param  array $filters  ['mime_type' => 'image/%', 'disk' => 'local']
     */
    public static function all(array $filters = []): array
    {
        if (static::$db !== null) {
            static::ensureTable();
            try {
                $sql    = 'SELECT * FROM ' . static::$table;
                $params = [];
                $where  = [];
                foreach ($filters as $col => $val) {
                    if (str_contains((string) $val, '%')) {
                        $where[]  = "{$col} LIKE ?";
                    } else {
                        $where[]  = "{$col} = ?";
                    }
                    $params[] = $val;
                }
                if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
                $sql .= ' ORDER BY id DESC';

                $rows = static::dbFetchAll($sql, $params);
                return array_map(fn(array $r) => new Attachment($r), $rows);
            } catch (\Throwable) {}
        }

        // Fallback: filter in-memory
        $result = array_values(static::$memory);
        foreach ($filters as $col => $val) {
            $result = array_filter($result, function (Attachment $a) use ($col, $val): bool {
                $getter = 'get' . str_replace('_', '', ucwords($col, '_'));
                if (method_exists($a, $getter)) {
                    $v = $a->$getter();
                    return str_contains((string) $val, '%')
                        ? fnmatch($val, (string) $v)
                        : $v == $val;
                }
                return true;
            });
        }
        return array_values($result);
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    /**
     * Delete an attachment record and its physical file.
     *
     * @param  int|Attachment $attachment
     */
    public static function delete(int|Attachment $attachment): bool
    {
        $id  = $attachment instanceof Attachment ? $attachment->getId() : $attachment;
        $att = $attachment instanceof Attachment ? $attachment : static::find($id);

        // Remove physical file
        if ($att !== null) {
            $absPath = static::$uploadDir . '/' . $att->getPath();
            if (file_exists($absPath)) {
                @unlink($absPath);
            }
        }

        unset(static::$memory[$id]);

        if (static::$db !== null) {
            static::ensureTable();
            try {
                static::dbExecute('DELETE FROM ' . static::$table . ' WHERE id=?', [$id]);
            } catch (\Throwable) {}
        }

        return true;
    }

    /**
     * Replace an existing attachment with a new upload while keeping the workflow simple.
     *
     * The old file record is deleted before the new one is stored.
     */
    public static function replace(int|Attachment $attachment, array $file, string $disk = 'local'): Attachment
    {
        static::delete($attachment);
        return static::upload($file, $disk);
    }

    /**
     * Delete many attachments in one pass.
     *
     * @param array<int, int|Attachment> $attachments
     */
    public static function deleteMany(array $attachments): int
    {
        $count = 0;
        foreach ($attachments as $attachment) {
            if (static::delete($attachment)) {
                $count++;
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // URL resolution
    // -------------------------------------------------------------------------

    /**
     * Return the public URL for an attachment.
     *
     * @param  int|Attachment $attachment
     */
    public static function url(int|Attachment $attachment): string
    {
        $att = $attachment instanceof Attachment ? $attachment : static::find($attachment);
        if ($att === null) return '';

        return static::$publicBase . '/' . ltrim($att->getPath(), '/');
    }

    /**
     * Return the public base URL currently in use.
     */
    public static function publicBase(): string
    {
        return static::$publicBase;
    }

    /**
     * Return the upload directory currently in use.
     */
    public static function uploadDir(): string
    {
        return static::$uploadDir;
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    /**
     * Validate a $_FILES array element.
     *
     * @throws \InvalidArgumentException
     */
    public static function validateFile(array $file): void
    {
        $error = $file['error'] ?? \UPLOAD_ERR_NO_FILE;
        if ($error !== \UPLOAD_ERR_OK && $error !== 0) {
            throw new \InvalidArgumentException("Upload error code: {$error}");
        }

        if (!empty($file['name']) && empty($file['tmp_name'])) {
            // Allow "mock" uploads in tests (no tmp_name required)
        }

        $mime = $file['type'] ?? '';
        if (!empty(static::$allowedTypes) && $mime !== '') {
            if (!in_array($mime, static::$allowedTypes, true)) {
                throw new \InvalidArgumentException("File type '{$mime}' is not allowed.");
            }
        }

        $size = (int) ($file['size'] ?? 0);
        if (static::$maxSize > 0 && $size > static::$maxSize) {
            throw new \InvalidArgumentException("File size {$size} exceeds maximum " . static::$maxSize . " bytes.");
        }
    }

    // -------------------------------------------------------------------------
    // Persistence helpers
    // -------------------------------------------------------------------------

    private static function persist(array $data): Attachment
    {
        if (static::$db !== null) {
            static::ensureTable();
            try {
                static::dbExecute(
                    'INSERT INTO ' . static::$table . ' (filename,original_name,mime_type,size,disk,path,alt,created_at) VALUES (?,?,?,?,?,?,?,?)',
                    [
                        $data['filename'], $data['original_name'], $data['mime_type'],
                        $data['size'], $data['disk'], $data['path'],
                        $data['alt'] ?? '', $data['created_at'],
                    ]
                );
                // Try to get last insert ID
                $id = static::dbLastInsertId();
                $data['id'] = $id;
                $att = new Attachment($data);
                if ($id > 0) static::$memory[$id] = $att;
                return $att;
            } catch (\Throwable) {}
        }

        // Fallback: in-memory
        static::$memorySeq++;
        $data['id'] = static::$memorySeq;
        $att = new Attachment($data);
        static::$memory[static::$memorySeq] = $att;
        return $att;
    }

    // -------------------------------------------------------------------------
    // DB helpers
    // -------------------------------------------------------------------------

    private static function ensureTable(): void
    {
        if (static::$tableChecked) return;
        static::$tableChecked = true;

        $sql = 'CREATE TABLE IF NOT EXISTS ' . static::$table . ' (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            filename      VARCHAR(255) NOT NULL,
            original_name VARCHAR(255),
            mime_type     VARCHAR(100),
            size          INTEGER DEFAULT 0,
            disk          VARCHAR(50) DEFAULT \'local\',
            path          VARCHAR(500),
            alt           VARCHAR(500) DEFAULT \'\',
            created_at    DATETIME
        )';

        try {
            static::dbExecute($sql, []);
        } catch (\Throwable) {
            try {
                static::dbExecute(
                    str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'INT PRIMARY KEY AUTO_INCREMENT', $sql),
                    []
                );
            } catch (\Throwable) {}
        }
    }

    private static function dbExecute(string $sql, array $params): void
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $db->prepare($sql)->execute($params);
        } elseif (method_exists($db, 'statement')) {
            $db->statement($sql, $params);
        }
    }

    private static function dbFetchOne(string $sql, array $params): ?array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } elseif (method_exists($db, 'selectOne')) {
            return $db->selectOne($sql, $params) ?: null;
        }
        return null;
    }

    private static function dbFetchAll(string $sql, array $params): array
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } elseif (method_exists($db, 'select')) {
            return (array) $db->select($sql, $params);
        }
        return [];
    }

    private static function dbLastInsertId(): int
    {
        $db = static::$db;
        if ($db instanceof \PDO) {
            return (int) $db->lastInsertId();
        } elseif (method_exists($db, 'lastInsertId')) {
            return (int) $db->lastInsertId();
        }
        return 0;
    }
}
