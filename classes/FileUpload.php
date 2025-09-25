<?php
// classes/FileUpload.php - File Upload Handler
class FileUpload {
    
    private $allowedExtensions;
    private $maxFileSize;
    private $uploadPath;
    
    public function __construct() {
        $this->allowedExtensions = ALLOWED_EXTENSIONS ?? ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
        $this->maxFileSize = MAX_UPLOAD_SIZE ?? 10485760; // 10MB
        $this->uploadPath = UPLOAD_PATH ?? dirname(__DIR__) . '/assets/uploads/';
    }
    
    /**
     * Handle file upload
     */
    public function handleUpload($file, $subDirectory = '') {
        try {
            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return $this->getUploadError($file['error']);
            }
            
            // Validate file size
            if ($file['size'] > $this->maxFileSize) {
                $maxSizeMB = round($this->maxFileSize / (1024 * 1024), 2);
                return ['success' => false, 'message' => "File size exceeds {$maxSizeMB}MB limit"];
            }
            
            // Validate file extension
            $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $this->allowedExtensions)) {
                $allowedTypes = implode(', ', $this->allowedExtensions);
                return ['success' => false, 'message' => "Invalid file type. Allowed: {$allowedTypes}"];
            }
            
            // Create subdirectory if it doesn't exist
            $targetDir = $this->uploadPath;
            if (!empty($subDirectory)) {
                $targetDir .= $subDirectory . '/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
            }
            
            // Generate unique filename
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            $filename = $this->generateUniqueFilename($originalName, $fileExtension, $targetDir);
            $targetPath = $targetDir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                return ['success' => false, 'message' => 'Failed to save uploaded file'];
            }
            
            // Log successful upload
            $this->logUpload($filename, $file['size'], $subDirectory);
            
            return [
                'success' => true,
                'filename' => $filename,
                'original_name' => $file['name'],
                'size' => $file['size'],
                'path' => $targetPath
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Upload error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate unique filename
     */
    private function generateUniqueFilename($originalName, $extension, $directory) {
        // Sanitize filename
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
        $cleanName = substr($cleanName, 0, 100); // Limit length
        
        // Add timestamp for uniqueness
        $timestamp = time();
        $filename = "{$cleanName}_{$timestamp}.{$extension}";
        
        // Ensure filename is unique
        $counter = 1;
        while (file_exists($directory . $filename)) {
            $filename = "{$cleanName}_{$timestamp}_{$counter}.{$extension}";
            $counter++;
        }
        
        return $filename;
    }
    
    /**
     * Get upload error message
     */
    private function getUploadError($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form upload limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
        ];
        
        $message = $errors[$errorCode] ?? 'Unknown upload error';
        return ['success' => false, 'message' => $message];
    }
    
    /**
     * Delete uploaded file
     */
    public function deleteFile($filename, $subDirectory = '') {
        $targetDir = $this->uploadPath;
        if (!empty($subDirectory)) {
            $targetDir .= $subDirectory . '/';
        }
        
        $filePath = $targetDir . $filename;
        
        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->logUpload($filename, 0, $subDirectory, 'deleted');
                return ['success' => true, 'message' => 'File deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete file'];
            }
        } else {
            return ['success' => false, 'message' => 'File not found'];
        }
    }
    
    /**
     * Get file info
     */
    public function getFileInfo($filename, $subDirectory = '') {
        $targetDir = $this->uploadPath;
        if (!empty($subDirectory)) {
            $targetDir .= $subDirectory . '/';
        }
        
        $filePath = $targetDir . $filename;
        
        if (file_exists($filePath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath),
                'path' => $filePath,
                'url' => $this->getFileUrl($filename, $subDirectory)
            ];
        } else {
            return ['success' => false, 'message' => 'File not found'];
        }
    }
    
    /**
     * Get file URL for web access
     */
    public function getFileUrl($filename, $subDirectory = '') {
        $baseUrl = getBaseUrl();
        $url = $baseUrl . '/assets/uploads/';
        
        if (!empty($subDirectory)) {
            $url .= $subDirectory . '/';
        }
        
        return $url . $filename;
    }
    
    /**
     * Validate image file
     */
    public function validateImage($file) {
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }
        
        $allowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
        if (!in_array($imageInfo[2], $allowedImageTypes)) {
            return ['success' => false, 'message' => 'Image must be JPG, PNG, or GIF'];
        }
        
        return ['success' => true, 'width' => $imageInfo[0], 'height' => $imageInfo[1]];
    }
    
    /**
     * Log upload activity
     */
    private function logUpload($filename, $fileSize, $subDirectory, $action = 'uploaded') {
        if (function_exists('logActivity')) {
            $description = "File {$action}: {$filename}";
            if (!empty($subDirectory)) {
                $description .= " in {$subDirectory}";
            }
            
            logActivity('system', null, 'file_' . $action, $description, null, [
                'filename' => $filename,
                'size' => $fileSize,
                'directory' => $subDirectory
            ]);
        }
        
        // Also log to upload.log file
        $logMessage = date('Y-m-d H:i:s') . " - File {$action}: {$filename} ({$fileSize} bytes)\n";
        error_log($logMessage, 3, dirname(__DIR__) . '/logs/upload.log');
    }
    
    /**
     * Clean old files (optional maintenance function)
     */
    public function cleanOldFiles($subDirectory = '', $daysOld = 30) {
        $targetDir = $this->uploadPath;
        if (!empty($subDirectory)) {
            $targetDir .= $subDirectory . '/';
        }
        
        if (!is_dir($targetDir)) {
            return ['success' => false, 'message' => 'Directory not found'];
        }
        
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $deletedFiles = 0;
        
        $files = scandir($targetDir);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && $file !== 'index.php') {
                $filePath = $targetDir . $file;
                if (is_file($filePath) && filemtime($filePath) < $cutoffTime) {
                    if (unlink($filePath)) {
                        $deletedFiles++;
                        $this->logUpload($file, 0, $subDirectory, 'auto-deleted');
                    }
                }
            }
        }
        
        return [
            'success' => true, 
            'message' => "Cleaned {$deletedFiles} old files",
            'deleted_count' => $deletedFiles
        ];
    }
}
?>