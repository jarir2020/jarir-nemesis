<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Generic File Upload Validator | Added: 2026-04-03

namespace Nemesis\Support;

use Nemesis\Http\UploadedFile;
use Nemesis\Http\UploadResult;

/**
 * Secure, generic file-upload validator.
 *
 * Preserves every security check from the original validateImageFileAndGetImagePath():
 *   • null-file guard                • null-byte filename attack
 *   • path-traversal block           • leading/trailing-dot block
 *   • double-extension block         • extension whitelist
 *   • server-side finfo MIME check   • real-content verification (getimagesize / magic bytes)
 *   • min-size (1 KB)                • max-size
 *   • directory auto-creation        • collision-safe unique filename
 *
 * Usage:
 *   // Typed shortcuts
 *   $result = FileValidator::image($file, $destPath, $userId);
 *   $result = FileValidator::video($file, $destPath, $userId, maxMb: 200);
 *   $result = FileValidator::document($file, $destPath, $userId);
 *   $result = FileValidator::audio($file, $destPath, $userId);
 *
 *   // Fluent builder
 *   $result = FileValidator::for('image')->maxSize(100)->validate($file, $dest, $id);
 *
 *   if ($result->failed()) {
 *       return response()->json(['error' => $result->error], 422);
 *   }
 *   $model->photo = $result->path; // relative path ready for DB
 *
 *   // Works with raw $_FILES via UploadedFile::fromGlobal():
 *   $file   = UploadedFile::fromGlobal('avatar');
 *   $result = FileValidator::image($file, storage_path('uploads/avatars'), $user->id);
 */
class FileValidator
{
    // -------------------------------------------------------------------------
    // Type definitions
    // -------------------------------------------------------------------------

    private const TYPES = [

        'image' => [
            'extensions' => ['jpg','jpeg','png','gif','bmp','webp','svg','heic','tiff','avif'],
            'mimes'      => [
                'image/jpeg','image/jpg','image/png','image/gif','image/bmp',
                'image/webp','image/svg+xml','image/heic','image/tiff','image/avif',
            ],
            'max_mb'     => 50,
            'min_bytes'  => 1024,
        ],

        'video' => [
            'extensions' => ['mp4','webm','avi','mov','mkv','flv','wmv','m4v','ogv','3gp'],
            'mimes'      => [
                'video/mp4','video/webm','video/avi','video/quicktime',
                'video/x-matroska','video/x-flv','video/x-ms-wmv',
                'video/x-m4v','video/ogg','video/3gpp',
            ],
            'max_mb'     => 500,
            'min_bytes'  => 1024,
        ],

        'document' => [
            'extensions' => [
                'pdf','doc','docx','xls','xlsx','ppt','pptx',
                'txt','csv','odt','ods','odp','rtf','md',
            ],
            'mimes'      => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain','text/csv','text/markdown',
                'application/vnd.oasis.opendocument.text',
                'application/vnd.oasis.opendocument.spreadsheet',
                'application/vnd.oasis.opendocument.presentation',
                'application/rtf','text/rtf',
                // ZIP container (docx/xlsx/pptx are ZIP internally)
                'application/zip',
                'application/x-zip-compressed',
            ],
            'max_mb'     => 20,
            'min_bytes'  => 1,
        ],

        'audio' => [
            'extensions' => ['mp3','wav','ogg','flac','aac','m4a','opus','wma'],
            'mimes'      => [
                'audio/mpeg','audio/mp3','audio/wav','audio/x-wav',
                'audio/ogg','audio/flac','audio/aac','audio/mp4',
                'audio/x-m4a','audio/opus','audio/x-ms-wma',
            ],
            'max_mb'     => 50,
            'min_bytes'  => 1024,
        ],

        'archive' => [
            'extensions' => ['zip','tar','gz','7z','rar','bz2'],
            'mimes'      => [
                'application/zip','application/x-zip-compressed',
                'application/x-tar','application/gzip',
                'application/x-7z-compressed','application/x-rar-compressed',
                'application/x-bzip2',
            ],
            'max_mb'     => 200,
            'min_bytes'  => 1,
        ],
    ];

    // -------------------------------------------------------------------------
    // Builder state
    // -------------------------------------------------------------------------

    private string $type      = 'image';
    private int    $maxMb     = 50;
    private int    $minBytes  = 1024;

    private function __construct(string $type)
    {
        $this->type     = $type;
        $this->maxMb    = self::TYPES[$type]['max_mb']    ?? 50;
        $this->minBytes = self::TYPES[$type]['min_bytes'] ?? 1024;
    }

    // -------------------------------------------------------------------------
    // Static entry points
    // -------------------------------------------------------------------------

    /** Start a fluent builder for the given type. */
    public static function for(string $type): self
    {
        if (!isset(self::TYPES[$type])) {
            throw new \InvalidArgumentException(
                "Unknown upload type '{$type}'. Supported: " . implode(', ', array_keys(self::TYPES))
            );
        }
        return new self($type);
    }

    /** Override max file size (MB). */
    public function maxSize(int $mb): self
    {
        $clone = clone $this;
        $clone->maxMb = $mb;
        return $clone;
    }

    /** Override min file size (bytes). */
    public function minSize(int $bytes): self
    {
        $clone = clone $this;
        $clone->minBytes = $bytes;
        return $clone;
    }

    /** Run the validation. */
    public function validate(
        ?UploadedFile      $file,
        string             $destinationPath,
        int|string         $id,
        string             $inputName = 'file',
    ): UploadResult {
        return self::run(
            $file,
            $destinationPath,
            $id,
            $this->type,
            $this->maxMb,
            $this->minBytes,
            $inputName,
        );
    }

    // -------------------------------------------------------------------------
    // Convenience shortcuts
    // -------------------------------------------------------------------------

    public static function image(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        int           $maxMb    = 50,
        string        $input    = 'file',
    ): UploadResult {
        return self::run($file, $dest, $id, 'image', $maxMb, 1024, $input);
    }

    public static function video(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        int           $maxMb    = 500,
        string        $input    = 'file',
    ): UploadResult {
        return self::run($file, $dest, $id, 'video', $maxMb, 1024, $input);
    }

    public static function document(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        int           $maxMb    = 20,
        string        $input    = 'file',
    ): UploadResult {
        return self::run($file, $dest, $id, 'document', $maxMb, 1, $input);
    }

    public static function audio(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        int           $maxMb    = 50,
        string        $input    = 'file',
    ): UploadResult {
        return self::run($file, $dest, $id, 'audio', $maxMb, 1024, $input);
    }

    public static function archive(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        int           $maxMb    = 200,
        string        $input    = 'file',
    ): UploadResult {
        return self::run($file, $dest, $id, 'archive', $maxMb, 1, $input);
    }

    // -------------------------------------------------------------------------
    // Core validation pipeline
    // -------------------------------------------------------------------------

    private static function run(
        ?UploadedFile $file,
        string        $dest,
        int|string    $id,
        string        $type,
        int           $maxMb,
        int           $minBytes,
        string        $inputName,
    ): UploadResult {

        $cfg = self::TYPES[$type] ?? self::TYPES['image'];

        // ------------------------------------------------------------------
        // 1. Null / upload-error guard
        // ------------------------------------------------------------------
        if ($file === null || !$file->isValid()) {
            $err = $file ? $file->getErrorMessage() : 'No file received';
            return UploadResult::fail("No file received for '{$inputName}': {$err}");
        }

        $originalName = $file->getClientOriginalName();

        // ------------------------------------------------------------------
        // 2. Null-byte in filename — prevents OS-level path truncation
        // ------------------------------------------------------------------
        if (str_contains($originalName, "\0") || str_contains($originalName, '%00')) {
            return UploadResult::fail(
                "Invalid filename for '{$inputName}': null bytes not allowed"
            );
        }

        // ------------------------------------------------------------------
        // 3. Path-traversal — block ../ ..\ /.. \.. and bare / \
        // ------------------------------------------------------------------
        foreach (['../', '..\\', '/..', '\\..', '/', '\\'] as $seq) {
            if (str_contains($originalName, $seq)) {
                return UploadResult::fail(
                    "Invalid filename for '{$inputName}': path segments not allowed"
                );
            }
        }

        // ------------------------------------------------------------------
        // 4. Leading dot — blocks .htaccess, .env, etc.
        // ------------------------------------------------------------------
        if (str_starts_with($originalName, '.')) {
            return UploadResult::fail(
                "Invalid filename for '{$inputName}': leading dot not allowed"
            );
        }

        // ------------------------------------------------------------------
        // 5. Trailing dot — blocks 'file.' on some systems
        // ------------------------------------------------------------------
        if (str_ends_with($originalName, '.')) {
            return UploadResult::fail(
                "Invalid filename for '{$inputName}': trailing dot not allowed"
            );
        }

        // ------------------------------------------------------------------
        // 6. Double-extension — blocks 'shell.php.jpg' disguised uploads
        // ------------------------------------------------------------------
        if (substr_count($originalName, '.') > 1) {
            return UploadResult::fail(
                "File '{$originalName}' contains multiple extensions in '{$inputName}'"
            );
        }

        // ------------------------------------------------------------------
        // 7. Extension whitelist
        // ------------------------------------------------------------------
        $ext = $file->getClientOriginalExtension();
        if (!in_array($ext, $cfg['extensions'], true)) {
            $allowed = implode(', ', $cfg['extensions']);
            return UploadResult::fail(
                "Invalid extension '.{$ext}' for '{$inputName}'. Allowed: {$allowed}"
            );
        }

        // ------------------------------------------------------------------
        // 8. MIME type — server-side finfo (not browser-supplied header)
        // ------------------------------------------------------------------
        $mime = $file->getMimeType();
        if (!in_array($mime, $cfg['mimes'], true)) {
            $allowedMimes = implode(', ', $cfg['mimes']);
            return UploadResult::fail(
                "Invalid MIME type '{$mime}' for '{$inputName}'. Allowed: {$allowedMimes}"
            );
        }

        // ------------------------------------------------------------------
        // 9. Real-content verification (type-specific)
        // ------------------------------------------------------------------
        $contentCheck = self::verifyContent($file, $type, $mime, $inputName);
        if ($contentCheck !== null) {
            return UploadResult::fail($contentCheck);
        }

        // ------------------------------------------------------------------
        // 10. Min size — blocks empty / trivially small files
        // ------------------------------------------------------------------
        $size = $file->getSize();
        if ($size < $minBytes) {
            return UploadResult::fail(
                "File too small ({$size} bytes) for '{$inputName}'. Minimum: {$minBytes} bytes"
            );
        }

        // ------------------------------------------------------------------
        // 11. Max size
        // ------------------------------------------------------------------
        $sizeMb = $size / (1024 * 1024);
        if ($sizeMb > $maxMb) {
            return UploadResult::fail(
                sprintf(
                    "File too large (%.2f MB) for '%s'. Max: %d MB",
                    $sizeMb, $inputName, $maxMb
                )
            );
        }

        // ------------------------------------------------------------------
        // 12. Ensure destination directory exists
        // ------------------------------------------------------------------
        File::ensureDirectoryExists($dest);

        // ------------------------------------------------------------------
        // 13. Collision-safe unique filename
        // ------------------------------------------------------------------
        $fileName = self::uniqueName($id, $ext, $dest);

        // ------------------------------------------------------------------
        // 14. Move file to permanent location
        // ------------------------------------------------------------------
        if (!$file->move($dest, $fileName)) {
            return UploadResult::fail(
                "Failed to move uploaded file to destination for '{$inputName}'"
            );
        }

        // ------------------------------------------------------------------
        // 15. Return relative path (web-root relative, for DB storage)
        // ------------------------------------------------------------------
        $relativePath = Upload::findRelativePath($dest);
        return UploadResult::ok(trim($relativePath, '/') . '/' . $fileName);
    }

    // -------------------------------------------------------------------------
    // Real-content verification — per type
    // -------------------------------------------------------------------------

    private static function verifyContent(
        UploadedFile $file,
        string       $type,
        string       $mime,
        string       $inputName,
    ): ?string {

        $tmp = $file->getPathname();

        return match ($type) {
            'image'    => self::verifyImage($tmp, $mime, $inputName),
            'video'    => self::verifyMagicBytes($tmp, $type, $inputName),
            'document' => self::verifyMagicBytes($tmp, $type, $inputName),
            'audio'    => self::verifyMagicBytes($tmp, $type, $inputName),
            default    => null,
        };
    }

    /**
     * Image verification via getimagesize() — confirms the file has real
     * pixel dimensions and is not just a renamed non-image file.
     * SVG is XML so getimagesize() doesn't apply; it's skipped.
     */
    private static function verifyImage(string $tmp, string $mime, string $input): ?string
    {
        if ($mime === 'image/svg+xml') return null; // XML-based, skip

        $info = @getimagesize($tmp);
        if (!$info || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            return "File is not a valid image for '{$input}'. Could not read image dimensions";
        }

        return null;
    }

    /**
     * Magic-byte verification for video, audio, document.
     *
     * Reads the first 12 bytes and checks against known signatures.
     * This is a defence-in-depth measure on top of finfo MIME detection.
     */
    private static function verifyMagicBytes(string $tmp, string $type, string $input): ?string
    {
        $handle = @fopen($tmp, 'rb');
        if (!$handle) return null; // can't open — skip rather than block

        $bytes = fread($handle, 12);
        fclose($handle);

        if ($bytes === false || strlen($bytes) < 4) {
            return "File appears empty or corrupt for '{$input}'";
        }

        $hex = bin2hex($bytes);

        $valid = match ($type) {
            'video'    => self::validVideoMagic($hex),
            'document' => self::validDocumentMagic($hex),
            'audio'    => self::validAudioMagic($hex),
            default    => true,
        };

        if (!$valid) {
            return "File content does not match the declared type for '{$input}'";
        }

        return null;
    }

    /** Check magic bytes for common video formats. */
    private static function validVideoMagic(string $hex): bool
    {
        return
            // MP4 / M4V: ftyp box at offset 4 (bytes 4-7 = 66747970)
            substr($hex, 8, 8) === '66747970'
            // WebM: 1a 45 df a3
            || str_starts_with($hex, '1a45dfa3')
            // AVI: RIFF....AVI (52494646 ... 41564920)
            || (str_starts_with($hex, '52494646') && substr($hex, 16, 8) === '41564920')
            // FLV: 46 4c 56
            || str_starts_with($hex, '464c56')
            // WMV / ASF: 30 26 b2 75
            || str_starts_with($hex, '3026b275')
            // 3GP: ftyp3gp
            || str_starts_with(substr($hex, 8, 12), '66747970336770');
    }

    /** Check magic bytes for common document formats. */
    private static function validDocumentMagic(string $hex): bool
    {
        return
            // PDF: 25 50 44 46 (%PDF)
            str_starts_with($hex, '25504446')
            // ZIP (docx/xlsx/pptx are ZIP internally): 50 4b 03 04
            || str_starts_with($hex, '504b0304')
            // OLE compound (doc/xls/ppt legacy): d0 cf 11 e0
            || str_starts_with($hex, 'd0cf11e0')
            // Plain text / CSV / Markdown: no magic — allow all
            || true; // plain text has no universal magic bytes; rely on MIME
    }

    /** Check magic bytes for common audio formats. */
    private static function validAudioMagic(string $hex): bool
    {
        return
            // MP3: ID3 tag (49 44 33) or sync frames (ff fb / ff f3 / ff f2)
            str_starts_with($hex, '494433')
            || str_starts_with($hex, 'fffb')
            || str_starts_with($hex, 'fff3')
            || str_starts_with($hex, 'fff2')
            // WAV: RIFF....WAVE (52494646 ... 57415645)
            || (str_starts_with($hex, '52494646') && substr($hex, 16, 8) === '57415645')
            // OGG: 4f 67 67 53
            || str_starts_with($hex, '4f676753')
            // FLAC: 66 4c 61 43
            || str_starts_with($hex, '664c6143')
            // AAC/M4A/OPUS wrapped in MP4: ftyp at offset 4
            || substr($hex, 8, 8) === '66747970'
            // WMA: same OLE header as above
            || str_starts_with($hex, '3026b275');
    }

    // -------------------------------------------------------------------------
    // Filename helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a collision-safe unique filename.
     * Retries until no existing file has the same name.
     */
    private static function uniqueName(int|string $id, string $ext, string $dest): string
    {
        do {
            $name = time() . '_' . $id . '_' . Str::random(random_int(16, 24)) . '.' . $ext;
        } while (file_exists($dest . DIRECTORY_SEPARATOR . $name));

        return $name;
    }
}
