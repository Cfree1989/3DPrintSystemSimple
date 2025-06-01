<?php
// 3D Print Lab Management System - Utilities
require_once 'config.php';

// Generate secure token
function generateToken($length = TOKEN_LENGTH) {
    return bin2hex(random_bytes($length / 2));
}

// Validate file upload
function validateFile($file) {
    $errors = [];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed';
        return $errors;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File too large (max 50MB)';
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
    }
    
    return $errors;
}

// Calculate print cost
function calculateCost($method, $weight, $time_hours = 1) {
    $rate = ($method === 'resin') ? RESIN_RATE : FILAMENT_RATE;
    $cost = $weight * $rate + ($time_hours * 2); // $2/hour machine time
    return max($cost, MINIMUM_CHARGE);
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Generate job filename
function generateJobFilename($jobId, $originalName) {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    return sprintf("job_%d_%s.%s", $jobId, date('Ymd'), $ext);
}

// Check staff authentication
function isStaffAuthenticated() {
    return isset($_SESSION['staff_auth']) && $_SESSION['staff_auth'] === true;
}

// Staff login
function authenticateStaff($password) {
    if ($password === STAFF_PASSWORD) {
        $_SESSION['staff_auth'] = true;
        return true;
    }
    return false;
}

// Format currency
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Create upload directory if it doesn't exist
function ensureUploadDir() {
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
}

?> 