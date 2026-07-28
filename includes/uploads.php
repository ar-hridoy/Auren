<?php
/**
 * includes/uploads.php
 *
 * Small helpers for validated file uploads (profile photos, ID documents) and
 * for rendering a user's avatar (uploaded photo, else coloured initials).
 */

const AVATAR_DIR = __DIR__ . '/../uploads/avatars';
const ID_DIR     = __DIR__ . '/../uploads/ids';

const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5 MB

const IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
const ID_TYPES = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];

/**
 * Validate and store an uploaded file. Returns the stored filename on success,
 * or throws RuntimeException with a user-safe message on failure.
 *
 * @param array  $file     one entry from $_FILES
 * @param string $destDir  absolute directory to store into
 * @param array  $allowed  map of allowed mime => extension
 * @param string $prefix   filename prefix (e.g. "avatar_12")
 */
function storeUpload(array $file, string $destDir, array $allowed, string $prefix): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The file failed to upload. Please try again.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('File is too large (max 5 MB).');
    }

    // Detect the real MIME type from file contents, not the client-supplied name.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Unsupported file type. Allowed: ' . implode(', ', array_values($allowed)) . '.');
    }

    if (!is_dir($destDir)) {
        @mkdir($destDir, 0775, true);
    }

    $ext = $allowed[$mime];
    $name = $prefix . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $dest = $destDir . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        // Fallback for CLI/dev server where move_uploaded_file can be strict.
        if (!@rename($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) {
            throw new RuntimeException('Could not save the file. Please try again.');
        }
    }
    return $name;
}

/** True if a real file was actually chosen for this $_FILES key. */
function hasUpload(string $key): bool
{
    return isset($_FILES[$key])
        && ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        && !empty($_FILES[$key]['name']);
}

/** Two-letter initials for an avatar fallback. */
function avatarInitials(string $name): string
{
    $p = preg_split('/\s+/', trim($name));
    $i = strtoupper(substr($p[0] ?? '', 0, 1));
    if (count($p) > 1) {
        $i .= strtoupper(substr($p[count($p) - 1], 0, 1));
    }
    return $i !== '' ? $i : '?';
}

/**
 * Render an avatar: the uploaded photo if present, otherwise a gradient circle
 * with initials. $size is in pixels.
 */
function renderAvatar(?string $photo, string $name, int $size = 40, string $extraStyle = ''): string
{
    $dim = "width:{$size}px;height:{$size}px;{$extraStyle}";
    if ($photo) {
        $src = '/auren/uploads/avatars/' . rawurlencode($photo);
        return '<img src="' . htmlspecialchars($src) . '" alt="' . htmlspecialchars($name) . '" '
             . 'class="auren-avatar-img" style="' . $dim . '">';
    }
    $fontSize = max(0.7, $size / 46);
    return '<span class="auren-avatar" style="' . $dim . "font-size:{$fontSize}rem;\">"
         . htmlspecialchars(avatarInitials($name)) . '</span>';
}
