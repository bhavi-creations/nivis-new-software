<?php
require_once __DIR__ . '/errors.php';

function store_product_image(): ?string
{
    $file = $_FILES['image'] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(['error' => 'Image upload failed. Choose a photo within the server upload limit (maximum 50 MB).'], 422);
    }
    if ($file['size'] > 50 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) {
        respond(['error' => 'Choose an image smaller than 50 MB.'], 422);
    }
    if (!class_exists('finfo')) throw new NivisConfigurationException('Enable the PHP fileinfo extension to upload product images.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = [
        'image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp',
        'image/gif'=>'gif', 'image/bmp'=>'bmp', 'image/x-ms-bmp'=>'bmp',
        'image/tiff'=>'tiff', 'image/svg+xml'=>'svg', 'image/avif'=>'avif',
        'image/heic'=>'heic', 'image/heif'=>'heif', 'image/heic-sequence'=>'heic',
        'image/heif-sequence'=>'heif', 'image/x-icon'=>'ico', 'image/vnd.microsoft.icon'=>'ico',
        'image/vnd.adobe.photoshop'=>'psd', 'image/jp2'=>'jp2', 'image/jxl'=>'jxl'
    ];
    if (!is_string($mime) || strpos($mime, 'image/') !== 0) {
        respond(['error' => 'Choose a valid image file (maximum 50 MB).'], 422);
    }
    // Unknown image formats use a non-executable extension; never trust the supplied filename.
    $extension = $extensions[$mime] ?? 'img';
    $directory = __DIR__ . '/../uploads/photos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new NivisConfigurationException('Cannot create uploads/photos. Give the PHP server write permission to the uploads folder.');
    }
    $path = 'uploads/photos/' . bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $path)) {
        throw new NivisConfigurationException('Cannot save product photo. Check write permissions and free space for uploads/photos.');
    }
    return $path;
}

function remove_product_image(?string $path): void
{
    if ($path && preg_match('~^uploads/photos/[a-f0-9]{32}\.(jpg|png|webp|gif|bmp|tiff|svg|avif|heic|heif|ico|psd|jp2|jxl|img)$~D', $path)) {
        $file = __DIR__ . '/../' . $path;
        if (is_file($file)) @unlink($file);
    }
}
