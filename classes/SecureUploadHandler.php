<?php
// =========================================================================
// CIMS Secure Upload Handler
// Implements 5-Layer Defense-in-Depth for File & Image Uploads
// Conforms to Quality Standards Rule 5 (Security) & Rule 6 (OOP Architecture)
// =========================================================================

class SecureUploadHandler
{
    // Allowed MIME types and strictly mapped safe extensions
    private const IMAGE_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    private const RECEIPT_MIMES = [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf'
    ];

    // Base root for uploads and assets
    private static function getUploadsRootDir(): string
    {
        return dirname(__DIR__) . '/uploads';
    }

    private static function getAssetsRootDir(): string
    {
        return dirname(__DIR__) . '/assets';
    }

    /**
     * Layer 1, 2, 3: Validate, sanitize, and re-encode an asset image (e.g. Login Background).
     *
     * @param array $file $_FILES['input_name'] array
     * @param string $subDir Subdirectory within assets/ (e.g. 'img')
     * @param string $prefix File name prefix (e.g. 'custom_login_bg')
     * @param int $maxBytes Maximum allowed file size in bytes (default: 5MB)
     * @return string Relative path from app root (e.g. 'assets/img/custom_login_bg_...jpg')
     * @throws Exception
     */
    public static function validateAndSaveAssetImage(
        array $file,
        string $subDir = 'img',
        string $prefix = 'custom_login_bg',
        int $maxBytes = 5242880
    ): string {
        self::verifyUploadErrors($file, $maxBytes);
        $tmpPath = $file['tmp_name'];

        // Layer 1: Validate True Binary MIME
        $mime = self::detectTrueMime($tmpPath);
        if (!array_key_exists($mime, self::IMAGE_MIMES)) {
            throw new Exception("Security Violation: Disallowed file type. Only genuine JPG, PNG, and WebP images are permitted.");
        }

        // Layer 1: Structural Image Verification
        $imageSize = @getimagesize($tmpPath);
        if ($imageSize === false) {
            throw new Exception("Security Violation: File header does not match valid image dimensions.");
        }

        // Layer 1: Scan for hidden script tags
        self::scanForScriptSignatures(file_get_contents($tmpPath));

        $targetExt = self::IMAGE_MIMES[$mime];
        $filename = self::generateSecureFilename($prefix, $targetExt);

        $cleanSub = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir);
        $targetDir = self::getAssetsRootDir() . '/' . $cleanSub;
        if (!file_exists($targetDir)) {
            if (!mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
                throw new Exception("Failed to create asset storage directory.");
            }
        }

        $destination = $targetDir . '/' . $filename;

        // Layer 2: Image Re-Encoding (strips polyglots and comments)
        $rawBytes = file_get_contents($tmpPath);
        self::reencodeAndSaveImage($rawBytes, $mime, $destination);

        return 'assets/' . trim($subDir, '/\\') . '/' . $filename;
    }

    /**
     * Layer 1, 2, 3: Validate and securely save an uploaded image file.
     *
     * @param array $file $_FILES['input_name'] array
     * @param string $subDir Subdirectory within uploads/ (e.g. 'proofs', 'receipts', 'signatures')
     * @param string $prefix File name prefix (e.g. 'proof', 'receipt')
     * @param int $maxBytes Maximum allowed file size in bytes (default: 10MB)
     * @return string Relative path from app root (e.g. 'uploads/proofs/proof_123_abc.jpg')
     * @throws Exception
     */
    public static function validateAndSaveImageUpload(
        array $file,
        string $subDir,
        string $prefix = 'img',
        int $maxBytes = 10485760
    ): string {
        self::verifyUploadErrors($file, $maxBytes);
        $tmpPath = $file['tmp_name'];

        // Layer 1: Validate True Binary MIME
        $mime = self::detectTrueMime($tmpPath);
        if (!array_key_exists($mime, self::IMAGE_MIMES)) {
            throw new Exception("Security Violation: Disallowed file type. Only genuine JPG, PNG, and WebP images are permitted.");
        }

        // Layer 1: Structural Image Verification
        $imageSize = @getimagesize($tmpPath);
        if ($imageSize === false) {
            throw new Exception("Security Violation: File header does not match valid image dimensions.");
        }

        // Layer 1: Scan for hidden script tags
        self::scanForScriptSignatures(file_get_contents($tmpPath));

        $targetExt = self::IMAGE_MIMES[$mime];
        $filename = self::generateSecureFilename($prefix, $targetExt);
        $targetDir = self::prepareDirectory($subDir);
        $destination = $targetDir . '/' . $filename;

        // Layer 2: Image Re-Encoding (strips polyglots and comments)
        $rawBytes = file_get_contents($tmpPath);
        self::reencodeAndSaveImage($rawBytes, $mime, $destination);

        return 'uploads/' . trim($subDir, '/\\') . '/' . $filename;
    }

    /**
     * Layer 1, 2, 3: Validate and securely save a Receipt file (Image or PDF).
     */
    public static function validateAndSaveReceiptUpload(
        array $file,
        string $subDir = 'receipts',
        string $prefix = 'receipt',
        int $maxBytes = 10485760
    ): string {
        self::verifyUploadErrors($file, $maxBytes);
        $tmpPath = $file['tmp_name'];

        // Layer 1: True Binary MIME
        $mime = self::detectTrueMime($tmpPath);
        if (!array_key_exists($mime, self::RECEIPT_MIMES)) {
            throw new Exception("Security Violation: Disallowed receipt type. Only JPG, PNG, WebP, or PDF documents are permitted.");
        }

        $rawBytes = file_get_contents($tmpPath);

        // Scan for dangerous executable signatures
        self::scanForScriptSignatures($rawBytes);

        $targetExt = self::RECEIPT_MIMES[$mime];
        $filename = self::generateSecureFilename($prefix, $targetExt);
        $targetDir = self::prepareDirectory($subDir);
        $destination = $targetDir . '/' . $filename;

        if ($mime === 'application/pdf') {
            // Verify PDF header magic bytes
            if (strncmp($rawBytes, '%PDF-', 5) !== 0) {
                throw new Exception("Security Violation: Corrupted or invalid PDF header.");
            }
            // Check for high-risk embedded PDF active script objects
            if (preg_match('/\/JavaScript|\/JS|\/Launch/i', $rawBytes)) {
                throw new Exception("Security Violation: Active script execution objects found inside PDF.");
            }
            if (!file_put_contents($destination, $rawBytes)) {
                throw new Exception("Failed to write sanitized document to disk.");
            }
        } else {
            // Re-encode image (Layer 2)
            $imageSize = @getimagesize($tmpPath);
            if ($imageSize === false) {
                throw new Exception("Security Violation: File is not a valid image.");
            }
            self::reencodeAndSaveImage($rawBytes, $mime, $destination);
        }

        return 'uploads/' . trim($subDir, '/\\') . '/' . $filename;
    }

    /**
     * Layer 1, 2, 3: Validate and securely save Base64 Image Data (Camera snapshot or Canvas signature).
     */
    public static function validateAndSaveBase64Image(
        string $base64Payload,
        string $subDir,
        string $prefix = 'base64',
        int $maxBytes = 10485760
    ): string {
        if (empty($base64Payload)) {
            throw new Exception("No image data received.");
        }

        // Extract mime prefix if present (e.g. data:image/png;base64,...)
        if (strpos($base64Payload, 'base64,') !== false) {
            $base64Data = explode('base64,', $base64Payload)[1];
        } else {
            $base64Data = $base64Payload;
        }

        $binaryData = base64_decode($base64Data, true);
        if ($binaryData === false || strlen($binaryData) === 0) {
            throw new Exception("Invalid Base64 image encoding.");
        }

        if (strlen($binaryData) > $maxBytes) {
            throw new Exception("Captured image exceeds the maximum permitted size limit.");
        }

        // Layer 1: Scan for script injection signatures
        self::scanForScriptSignatures($binaryData);

        // Layer 1: Detect True Binary MIME
        $mime = self::detectTrueMimeFromBuffer($binaryData);
        if (!array_key_exists($mime, self::IMAGE_MIMES)) {
            // Fallback: Check if GD can parse it
            if (function_exists('imagecreatefromstring')) {
                $img = @imagecreatefromstring($binaryData);
                if (!$img) {
                    throw new Exception("Security Violation: Invalid image bitstream.");
                }
                imagedestroy($img);
                $mime = 'image/png';
            } else {
                throw new Exception("Security Violation: Disallowed image encoding format.");
            }
        }

        $targetExt = self::IMAGE_MIMES[$mime] ?? 'png';
        $filename = self::generateSecureFilename($prefix, $targetExt);
        $targetDir = self::prepareDirectory($subDir);
        $destination = $targetDir . '/' . $filename;

        // Layer 2: Re-encode
        self::reencodeAndSaveImage($binaryData, $mime, $destination);

        return 'uploads/' . trim($subDir, '/\\') . '/' . $filename;
    }

    /**
     * Layer 2: Re-encode image from raw bytes using GD to permanently strip polyglots and comments.
     */
    private static function reencodeAndSaveImage(string $binaryData, string $mime, string $destination): void
    {
        if (function_exists('imagecreatefromstring')) {
            $img = @imagecreatefromstring($binaryData);
            if (!$img) {
                throw new Exception("Security Violation: Unable to decode image stream. File may be corrupted or disguised.");
            }

            // Preserve alpha channel for PNG and WebP
            imagealphablending($img, false);
            imagesavealpha($img, true);

            $saved = false;
            if ($mime === 'image/png') {
                $saved = imagepng($img, $destination, 8); // PNG compression 0-9
            } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
                $saved = imagewebp($img, $destination, 90);
            } else {
                // JPEG default
                $saved = imagejpeg($img, $destination, 90);
            }

            imagedestroy($img);

            if (!$saved) {
                throw new Exception("Server error: Failed to re-encode and store image.");
            }
        } else {
            // Fallback: If GD is unavailable, write directly after strict Layer 1 checks
            if (!file_put_contents($destination, $binaryData)) {
                throw new Exception("Failed to write verified image to storage.");
            }
        }
    }

    /**
     * Layer 1: True binary MIME detection via finfo
     */
    private static function detectTrueMime(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return strtolower(trim((string)$mime));
        }

        if (function_exists('mime_content_type')) {
            return strtolower(trim((string)mime_content_type($filePath)));
        }

        throw new Exception("Server environment error: MIME inspection module (fileinfo) is unavailable.");
    }

    /**
     * Layer 1: True binary MIME detection from raw in-memory buffer
     */
    private static function detectTrueMimeFromBuffer(string $buffer): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $buffer);
            finfo_close($finfo);
            return strtolower(trim((string)$mime));
        }

        return 'application/octet-stream';
    }

    /**
     * Layer 1: Scan for hidden PHP/CGI/JS execution tags inside files
     */
    private static function scanForScriptSignatures(string $buffer): void
    {
        $patterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<\?(?!xml)/i',
            '/<script/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i',
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/passthru\s*\(/i',
            '/shell_exec\s*\(/i',
            '/proc_open\s*\(/i',
            '/__halt_compiler/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $buffer)) {
                throw new Exception("Security Violation: Executable code or backdoor payload detected inside uploaded content.");
            }
        }
    }

    /**
     * Layer 3: Controlled Server-Generated Filenames (unpredictable token)
     */
    private static function generateSecureFilename(string $prefix, string $extension): string
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
        if (empty($safePrefix)) {
            $safePrefix = 'file';
        }
        $randomToken = bin2hex(random_bytes(10));
        return $safePrefix . '_' . time() . '_' . $randomToken . '.' . ltrim($extension, '.');
    }

    /**
     * Validate upload error statuses and file size bounds
     */
    private static function verifyUploadErrors(array $file, int $maxBytes): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception("Invalid file upload parameters.");
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new Exception("No file was uploaded.");
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception("Uploaded file exceeds the maximum allowed file size.");
            default:
                throw new Exception("Failed to upload file due to server error code: " . $file['error']);
        }

        if (!isset($file['size']) || $file['size'] > $maxBytes) {
            throw new Exception("Uploaded file exceeds the maximum size limit of " . round($maxBytes / (1024 * 1024), 1) . "MB.");
        }

        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception("Security Violation: Missing temporary file upload path.");
        }

        // In web server environments, strictly enforce is_uploaded_file to prevent local file inclusion
        if (php_sapi_name() !== 'cli' && !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Security Violation: File tmp_name is not an authentic uploaded file.");
        }
    }

    /**
     * Prepare and secure target subdirectory under uploads/
     */
    private static function prepareDirectory(string $subDir): string
    {
        $cleanSub = preg_replace('/[^a-zA-Z0-9_-]/', '', $subDir);
        $fullPath = self::getUploadsRootDir() . '/' . $cleanSub;

        if (!file_exists($fullPath)) {
            if (!mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
                throw new Exception("Failed to create upload storage directory.");
            }
        }

        return $fullPath;
    }
}
