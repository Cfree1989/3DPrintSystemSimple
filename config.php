<?php
// 3D Print Lab Management System - Configuration
// Source of truth: solution_architecture.md

// Database configuration
define('DB_FILE', 'printlab.db');

// File upload settings
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['stl', 'obj', '3mf']);

// Email settings
define('LAB_EMAIL', 'printlab@university.edu');
define('LAB_NAME', '3D Print Lab');

// Pricing configuration
define('FILAMENT_RATE', 0.10); // per gram
define('RESIN_RATE', 0.20); // per gram
define('MINIMUM_CHARGE', 3.00);

// Workflow stages
define('STAGES', [
    'uploaded' => 'Uploaded',
    'pending' => 'Pending Review',
    'approved' => 'Approved',
    'confirmed' => 'Confirmed',
    'queued' => 'In Queue',
    'printing' => 'Printing',
    'completed' => 'Completed',
    'picked_up' => 'Picked Up'
]);

// Print methods and colors
define('PRINT_METHODS', [
    'filament' => [
        'name' => 'Filament (FDM)',
        'colors' => ['Black', 'White', 'Red', 'Blue', 'Green', 'Yellow']
    ],
    'resin' => [
        'name' => 'Resin (SLA)',
        'colors' => ['Clear', 'Black', 'White', 'Gray']
    ]
]);

// Academic disciplines
define('DISCIPLINES', [
    'Engineering', 'Computer Science', 'Art & Design', 'Architecture',
    'Biology', 'Chemistry', 'Physics', 'Mathematics', 'Other'
]);

// Security
define('STAFF_PASSWORD', 'printlab2024'); // Change in production
define('TOKEN_LENGTH', 32);

?> 