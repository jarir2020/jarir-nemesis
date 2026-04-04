<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 13 — Media Library | Created: 2026-04-03
// Attachment — value object representing a stored file/media asset.

namespace Nemesis\Media;

/**
 * Attachment — immutable value object for an uploaded media file.
 *
 * Usage:
 *   $att = new Attachment([
 *       'id'            => 1,
 *       'filename'      => 'photo-a3b9f.jpg',
 *       'original_name' => 'photo.jpg',
 *       'mime_type'     => 'image/jpeg',
 *       'size'          => 204800,
 *       'disk'          => 'local',
 *       'path'          => 'uploads/2026/04/photo-a3b9f.jpg',
 *       'alt'           => 'A nice photo',
 *       'created_at'    => '2026-04-03 12:00:00',
 *   ]);
 *
 *   $att->isImage();       // true
 *   $att->extension();     // 'jpg'
 *   $att->humanSize();     // '200 KB'
 */
class Attachment
{
    private int    $id;
    private string $filename;
    private string $originalName;
    private string $mimeType;
    private int    $size;          // bytes
    private string $disk;
    private string $path;
    private string $alt;
    private string $createdAt;

    /** MIME types that are considered images. */
    private const IMAGE_TYPES = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
        'image/webp', 'image/svg+xml', 'image/bmp', 'image/tiff',
    ];

    public function __construct(array $data)
    {
        $this->id           = (int)   ($data['id']            ?? 0);
        $this->filename     = (string)($data['filename']      ?? '');
        $this->originalName = (string)($data['original_name'] ?? $this->filename);
        $this->mimeType     = (string)($data['mime_type']     ?? '');
        $this->size         = (int)   ($data['size']          ?? 0);
        $this->disk         = (string)($data['disk']          ?? 'local');
        $this->path         = (string)($data['path']          ?? '');
        $this->alt          = (string)($data['alt']           ?? '');
        $this->createdAt    = (string)($data['created_at']    ?? '');
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int           { return $this->id; }
    public function getFilename(): string  { return $this->filename; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getMimeType(): string  { return $this->mimeType; }
    public function getSize(): int         { return $this->size; }
    public function getDisk(): string      { return $this->disk; }
    public function getPath(): string      { return $this->path; }
    public function getAlt(): string       { return $this->alt; }
    public function getCreatedAt(): string { return $this->createdAt; }

    // -------------------------------------------------------------------------
    // Computed properties
    // -------------------------------------------------------------------------

    /** True if the MIME type is one of the recognised image types. */
    public function isImage(): bool
    {
        return in_array(strtolower($this->mimeType), self::IMAGE_TYPES, true);
    }

    /** True if the file is a video. */
    public function isVideo(): bool
    {
        return str_starts_with($this->mimeType, 'video/');
    }

    /** True if the file is an audio file. */
    public function isAudio(): bool
    {
        return str_starts_with($this->mimeType, 'audio/');
    }

    /** True if the file is a PDF. */
    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    /** Return the lowercase file extension from the stored path. */
    public function extension(): string
    {
        $ext = strtolower(pathinfo($this->path ?: $this->filename, PATHINFO_EXTENSION));
        return $ext;
    }

    /**
     * Return a human-readable file size string.
     *
     * Examples: '512 B', '1.5 KB', '3.2 MB', '1.1 GB'
     */
    public function humanSize(): string
    {
        $bytes = $this->size;
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;
        $val   = (float) $bytes;

        while ($val >= 1024 && $i < count($units) - 1) {
            $val /= 1024;
            $i++;
        }

        return $i === 0
            ? (string) $bytes . ' B'
            : round($val, 1) . ' ' . $units[$i];
    }

    /**
     * Return a public URL for this attachment.
     * Delegates to MediaLibrary if available; falls back to path-based URL.
     */
    public function url(): string
    {
        if ($this->id > 0 && class_exists(MediaLibrary::class)) {
            try {
                return MediaLibrary::url($this);
            } catch (\Throwable) {}
        }
        return '/' . ltrim($this->path, '/');
    }

    /**
     * Return an HTML <img> tag for this attachment (if it is an image).
     *
     * @param  array $attrs  Extra HTML attributes: ['class' => 'img-fluid', ...]
     */
    public function imgTag(array $attrs = []): string
    {
        if (!$this->isImage()) return '';

        $attrStr = '';
        foreach ($attrs as $k => $v) {
            $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES) . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }

        $src = htmlspecialchars($this->url(), ENT_QUOTES);
        $alt = htmlspecialchars($this->alt ?: $this->originalName, ENT_QUOTES);
        return "<img src=\"{$src}\" alt=\"{$alt}\"{$attrStr}>";
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'filename'      => $this->filename,
            'original_name' => $this->originalName,
            'mime_type'     => $this->mimeType,
            'size'          => $this->size,
            'disk'          => $this->disk,
            'path'          => $this->path,
            'alt'           => $this->alt,
            'created_at'    => $this->createdAt,
        ];
    }
}
