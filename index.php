<?php
// 3D Print Lab Management System - Main Application
require_once 'config.php';
require_once 'database.php';
require_once 'utils.php';
require_once 'email.php';

// Initialize
$db = new Database();
$email = new EmailService();
ensureUploadDir();

// Route handling
$action = $_GET['action'] ?? 'home';
$message = $_GET['message'] ?? '';

// Process form submissions
if ($_POST) {
    switch ($action) {
        case 'submit':
            handleSubmission();
            break;
        case 'staff_login':
            handleStaffLogin();
            break;
        case 'approve':
            handleApproval();
            break;
        case 'reject':
            handleRejection();
            break;
        case 'status_update':
            handleStatusUpdate();
            break;
        case 'confirm':
            handleConfirmation();
            break;
    }
}

// Handle file upload and job submission
function handleSubmission() {
    global $db, $email;
    
    $errors = [];
    
    // Validate form data
    $name = sanitize($_POST['student_name'] ?? '');
    $emailAddr = sanitize($_POST['student_email'] ?? '');
    $discipline = sanitize($_POST['discipline'] ?? '');
    $project = sanitize($_POST['class_project'] ?? '');
    $method = sanitize($_POST['print_method'] ?? '');
    $color = sanitize($_POST['color'] ?? '');
    
    if (empty($name) || empty($emailAddr) || empty($discipline) || empty($method) || empty($color)) {
        $errors[] = 'All required fields must be filled';
    }
    
    if (!isValidEmail($emailAddr)) {
        $errors[] = 'Valid email address required';
    }
    
    // Validate file upload
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload required';
    } else {
        $fileErrors = validateFile($_FILES['file']);
        $errors = array_merge($errors, $fileErrors);
    }
    
    if (empty($errors)) {
        // Create job record
        $token = generateToken();
        $jobData = [
            'student_name' => $name,
            'student_email' => $emailAddr,
            'discipline' => $discipline,
            'class_project' => $project,
            'print_method' => $method,
            'color' => $color,
            'filename' => '',
            'original_filename' => $_FILES['file']['name'],
            'file_size' => $_FILES['file']['size'],
            'confirmation_token' => $token
        ];
        
        $jobId = $db->createJob($jobData);
        
        // Move uploaded file
        $filename = generateJobFilename($jobId, $_FILES['file']['name']);
        $filepath = UPLOAD_DIR . $filename;
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
            // Update job with filename
            $db->updateStatus($jobId, 'pending');
            
            // Get job for email
            $job = $db->getJob($jobId);
            $email->sendSubmissionConfirmation($job);
            
            header("Location: ?action=success&job_id=$jobId");
            exit;
        } else {
            $errors[] = 'File upload failed';
        }
    }
    
    // Redirect back with errors
    $errorMsg = implode(', ', $errors);
    header("Location: ?action=home&message=" . urlencode($errorMsg));
    exit;
}

// Handle staff login
function handleStaffLogin() {
    $password = $_POST['password'] ?? '';
    if (authenticateStaff($password)) {
        header("Location: ?action=dashboard");
        exit;
    } else {
        header("Location: ?action=staff&message=Invalid password");
        exit;
    }
}

// Handle job approval
function handleApproval() {
    if (!isStaffAuthenticated()) {
        header("Location: ?action=staff");
        exit;
    }
    
    global $db, $email;
    
    $jobId = (int)$_POST['job_id'];
    $weight = (float)$_POST['weight'];
    $timeHours = (float)$_POST['time_hours'];
    $cost = calculateCost($_POST['print_method'], $weight, $timeHours);
    
    $db->approveJob($jobId, $weight, $timeHours, $cost);
    
    // Send approval email
    $job = $db->getJob($jobId);
    $confirmUrl = "http://" . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . "?action=confirm&token=" . $job['confirmation_token'];
    $email->sendApprovalEmail($job, $confirmUrl);
    
    header("Location: ?action=dashboard&message=Job approved");
    exit;
}

// Handle job rejection
function handleRejection() {
    if (!isStaffAuthenticated()) {
        header("Location: ?action=staff");
        exit;
    }
    
    global $db, $email;
    
    $jobId = (int)$_POST['job_id'];
    $reason = sanitize($_POST['reason']);
    
    $db->rejectJob($jobId, $reason);
    
    // Send rejection email
    $job = $db->getJob($jobId);
    $email->sendRejectionEmail($job);
    
    header("Location: ?action=dashboard&message=Job rejected");
    exit;
}

// Handle status updates
function handleStatusUpdate() {
    if (!isStaffAuthenticated()) {
        header("Location: ?action=staff");
        exit;
    }
    
    global $db, $email;
    
    $jobId = (int)$_POST['job_id'];
    $newStatus = sanitize($_POST['status']);
    
    $db->updateStatus($jobId, $newStatus);
    
    // Send completion email if status is completed
    if ($newStatus === 'completed') {
        $job = $db->getJob($jobId);
        $email->sendCompletionEmail($job);
    }
    
    header("Location: ?action=dashboard&message=Status updated");
    exit;
}

// Handle job confirmation
function handleConfirmation() {
    global $db;
    
    $token = sanitize($_POST['token']);
    $action_type = sanitize($_POST['action_type']);
    
    $job = $db->getJobByToken($token);
    if (!$job) {
        header("Location: ?action=home&message=Invalid confirmation link");
        exit;
    }
    
    if ($action_type === 'confirm') {
        $db->updateStatus($job['id'], 'confirmed');
        header("Location: ?action=confirmed&job_id=" . $job['id']);
    } else {
        $db->updateStatus($job['id'], 'cancelled');
        header("Location: ?action=cancelled&job_id=" . $job['id']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= LAB_NAME ?> - 3D Print Request System</title>
    <style>
        /* Apple Design System - CSS Reset & Base */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        /* SF Pro Typography - Apple's Design System */
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Segoe UI', system-ui, sans-serif;
            line-height: 1.47059; /* Apple's preferred line height */
            color: #1d1d1f; /* Apple's primary text color */
            background: #f5f5f7; /* Apple's light mode background */
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            font-size: 17px; /* Apple's base font size */
        }
        
        /* 8-Point Grid System - Apple's Spacing */
        .container { 
            max-width: 1120px; /* Apple's preferred max width */
            margin: 0 auto; 
            padding: 32px 24px; /* 32px = 4*8, 24px = 3*8 */
        }
        
        /* Apple Header - Glassmorphism Effect */
        .header { 
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 0.5px solid rgba(0, 0, 0, 0.1);
            padding: 24px 0; /* 3*8px */
            margin-bottom: 48px; /* 6*8px */
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header h1 { 
            text-align: center; 
            font-size: 32px; /* Apple Large Title */
            font-weight: 700;
            letter-spacing: -0.003em;
            color: #1d1d1f;
            margin-bottom: 8px; /* 1*8px */
        }
        
        .header p {
            text-align: center;
            font-size: 19px; /* Apple Title 3 */
            font-weight: 400;
            color: #86868b; /* Apple secondary text */
            letter-spacing: 0.012em;
        }
        
        /* Apple Navigation - Touch-Friendly */
        .nav { 
            display: flex;
            justify-content: center;
            gap: 24px; /* 3*8px */
            margin: 32px 0; /* 4*8px */
        }
        
        .nav a { 
            color: #007aff; /* Apple blue */
            text-decoration: none; 
            font-size: 17px;
            font-weight: 400;
            padding: 12px 16px; /* 44px touch target height */
            border-radius: 20px;
            transition: all 0.2s ease-in-out;
            min-height: 44px; /* Apple minimum touch target */
            display: flex;
            align-items: center;
        }
        
        .nav a:hover { 
            background: rgba(0, 122, 255, 0.1);
            transform: scale(1.02);
        }
        
        /* Apple Form Elements */
        .form-group { 
            margin-bottom: 24px; /* 3*8px */
        }
        
        .form-group label { 
            display: block; 
            margin-bottom: 8px; /* 1*8px */
            font-weight: 600;
            font-size: 17px;
            color: #1d1d1f;
            letter-spacing: -0.022em;
        }
        
        .form-group input, 
        .form-group select, 
        .form-group textarea { 
            width: 100%; 
            padding: 16px 16px; /* 2*8px for proper touch targets */
            border: 1px solid #d2d2d7; /* Apple border color */
            border-radius: 12px; /* Apple's corner radius */
            font-size: 17px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.2s ease-in-out;
            min-height: 44px; /* Touch target compliance */
        }
        
        .form-group input:focus, 
        .form-group select:focus, 
        .form-group textarea:focus {
            outline: none;
            border-color: #007aff;
            box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }
        
        /* Apple Buttons - iOS Style */
        .btn { 
            background: #007aff; /* Apple blue */
            color: white; 
            padding: 16px 32px; /* 2*8px, 4*8px */
            border: none; 
            border-radius: 24px; /* Pill shape */
            cursor: pointer; 
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 17px;
            font-weight: 600;
            letter-spacing: -0.022em;
            min-height: 44px; /* Touch target compliance */
            min-width: 44px;
            transition: all 0.2s cubic-bezier(0.25, 0.46, 0.45, 0.94); /* Apple's easing */
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .btn:hover { 
            background: #0051d0;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }
        
        .btn:active {
            transform: scale(0.98);
        }
        
        .btn-danger { 
            background: #ff3b30; /* Apple red */
        }
        
        .btn-danger:hover { 
            background: #d70015;
            box-shadow: 0 4px 12px rgba(255, 59, 48, 0.3);
        }
        
        .btn-success { 
            background: #30d158; /* Apple green */
        }
        
        .btn-success:hover { 
            background: #1fb142;
            box-shadow: 0 4px 12px rgba(48, 209, 88, 0.3);
        }
        
        /* Apple Alert System */
        .alert { 
            padding: 20px 24px; /* Proper 8-point spacing */
            margin: 24px 0; 
            border-radius: 16px; /* Apple's larger corner radius */
            font-size: 15px; /* Apple footnote size */
            line-height: 1.33337;
            border: none;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        
        .alert-error { 
            background: rgba(255, 59, 48, 0.1); 
            color: #d70015; 
            border: 1px solid rgba(255, 59, 48, 0.2);
        }
        
        .alert-success { 
            background: rgba(48, 209, 88, 0.1); 
            color: #1fb142; 
            border: 1px solid rgba(48, 209, 88, 0.2);
        }
        
        .alert-info { 
            background: rgba(0, 122, 255, 0.1); 
            color: #0051d0; 
            border: 1px solid rgba(0, 122, 255, 0.2);
        }
        
        /* Apple Card System - Depth & Materials */
        .card { 
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 32px; /* 4*8px */
            margin: 32px 0; 
            border-radius: 20px; /* Apple's card radius */
            box-shadow: 
                0 1px 3px rgba(0, 0, 0, 0.04),
                0 4px 12px rgba(0, 0, 0, 0.08); /* Apple's multi-layer shadow */
            border: 0.5px solid rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 
                0 4px 8px rgba(0, 0, 0, 0.06),
                0 12px 32px rgba(0, 0, 0, 0.12);
        }
        
        /* Apple Grid System */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); 
            gap: 32px; /* 4*8px */
            align-items: start;
        }
        
        /* Apple Tab System */
        .tabs { 
            display: flex; 
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 16px;
            padding: 4px;
            margin-bottom: 32px;
            border: 0.5px solid rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .tabs::-webkit-scrollbar {
            display: none;
        }
        
        .tab { 
            padding: 12px 20px; /* Proper touch targets */
            cursor: pointer; 
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            color: #86868b;
            transition: all 0.2s ease-in-out;
            white-space: nowrap;
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
        }
        
        .tab.active { 
            background: #007aff;
            color: white;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(0, 122, 255, 0.3);
        }
        
        .tab:not(.active):hover {
            background: rgba(0, 122, 255, 0.1);
            color: #007aff;
        }
        
        .tab-content { 
            display: none; 
        }
        
        .tab-content.active { 
            display: block; 
        }
        
        /* Apple Modal System */
        .modal { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1000;
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal-content { 
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            margin: 10vh auto; 
            padding: 32px; 
            width: 90%; 
            max-width: 480px; 
            border-radius: 20px;
            box-shadow: 
                0 4px 12px rgba(0, 0, 0, 0.15),
                0 12px 40px rgba(0, 0, 0, 0.2);
            border: 0.5px solid rgba(255, 255, 255, 0.9);
            animation: slideUp 0.3s ease-out;
        }
        
        .close { 
            float: right; 
            font-size: 24px; 
            cursor: pointer; 
            color: #86868b;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            transition: all 0.2s ease-in-out;
        }
        
        .close:hover {
            background: rgba(0, 0, 0, 0.1);
        }
        
        /* Apple Job Items */
        .job-item { 
            padding: 24px; /* 3*8px */
            background: rgba(255, 255, 255, 0.6);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 0.5px solid rgba(0, 0, 0, 0.1); 
            margin: 16px 0; /* 2*8px */
            border-radius: 16px;
            transition: all 0.2s ease-in-out;
        }
        
        .job-item:hover { 
            background: rgba(255, 255, 255, 0.8);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* Apple File Upload */
        .file-upload { 
            border: 2px dashed #d2d2d7; 
            padding: 48px 24px; /* 6*8px, 3*8px */
            text-align: center; 
            border-radius: 16px; 
            margin: 16px 0; 
            cursor: pointer;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.2s ease-in-out;
        }
        
        .file-upload:hover { 
            border-color: #007aff; 
            background: rgba(0, 122, 255, 0.05);
            transform: scale(1.01);
        }
        
        .file-upload p {
            font-size: 17px;
            color: #1d1d1f;
            margin-bottom: 8px;
        }
        
        .file-upload small {
            font-size: 15px;
            color: #86868b;
        }
        
        /* Typography Hierarchy - Apple Style */
        h1 { 
            font-size: 32px; 
            font-weight: 700; 
            letter-spacing: -0.003em; 
            line-height: 1.125;
            color: #1d1d1f;
        }
        
        h2 { 
            font-size: 28px; 
            font-weight: 700; 
            letter-spacing: 0.007em; 
            line-height: 1.14286;
            color: #1d1d1f;
        }
        
        h3 { 
            font-size: 22px; 
            font-weight: 600; 
            letter-spacing: 0.016em; 
            line-height: 1.27273;
            color: #1d1d1f;
        }
        
        h4 { 
            font-size: 19px; 
            font-weight: 600; 
            letter-spacing: 0.012em; 
            line-height: 1.21053;
            color: #1d1d1f;
        }
        
        p {
            font-size: 17px;
            line-height: 1.47059;
            color: #1d1d1f;
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body {
                background: #000000;
                color: #f5f5f7;
            }
            
            .header {
                background: rgba(0, 0, 0, 0.8);
                border-bottom-color: rgba(255, 255, 255, 0.1);
            }
            
            .header h1, h1, h2, h3, h4 {
                color: #f5f5f7;
            }
            
            .header p {
                color: #a1a1a6;
            }
            
            .card {
                background: rgba(28, 28, 30, 0.7);
                border-color: rgba(255, 255, 255, 0.1);
            }
            
            .form-group input, 
            .form-group select, 
            .form-group textarea {
                background: rgba(28, 28, 30, 0.8);
                border-color: #48484a;
                color: #f5f5f7;
            }
            
            .form-group label {
                color: #f5f5f7;
            }
            
            .job-item {
                background: rgba(28, 28, 30, 0.6);
                border-color: rgba(255, 255, 255, 0.1);
            }
            
            .tabs {
                background: rgba(28, 28, 30, 0.7);
            }
            
            .modal-content {
                background: rgba(28, 28, 30, 0.95);
            }
        }
        
        /* Reduced Motion Support - Accessibility */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Responsive Design - Mobile First */
        @media (max-width: 768px) {
            .container { 
                padding: 16px; /* 2*8px for mobile */
            }
            
            .grid { 
                grid-template-columns: 1fr; 
                gap: 24px; /* 3*8px */
            }
            
            .tabs { 
                flex-direction: column; 
                align-items: stretch;
            }
            
            .tab {
                text-align: center;
                padding: 16px;
            }
            
            .modal-content {
                margin: 5vh auto;
                width: 95%;
                padding: 24px;
            }
            
            .card {
                padding: 24px;
                margin: 24px 0;
                border-radius: 16px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1><?= LAB_NAME ?></h1>
            <p>3D Print Request Management System</p>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php
        switch ($action) {
            case 'home':
            default:
                renderHomePage();
                break;
            case 'staff':
                renderStaffLogin();
                break;
            case 'dashboard':
                if (isStaffAuthenticated()) {
                    renderDashboard();
                } else {
                    renderStaffLogin();
                }
                break;
            case 'confirm':
                renderConfirmation();
                break;
            case 'success':
                renderSuccess();
                break;
            case 'confirmed':
                renderConfirmed();
                break;
            case 'cancelled':
                renderCancelled();
                break;
        }
        ?>
    </div>

    <script>
        // Apple-Style Enhanced Interactions
        
        // Tab functionality with smooth transitions
        function showTab(tabName) {
            // Remove active states
            document.querySelectorAll('.tab').forEach(t => {
                t.classList.remove('active');
                t.style.transform = 'scale(1)';
            });
            document.querySelectorAll('.tab-content').forEach(c => {
                c.classList.remove('active');
                c.style.opacity = '0';
            });
            
            // Add active states with animation
            const activeTab = document.querySelector(`[onclick="showTab('${tabName}')"]`);
            const activeContent = document.getElementById(tabName);
            
            if (activeTab && activeContent) {
                activeTab.classList.add('active');
                
                // Apple-style spring animation
                activeTab.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    activeTab.style.transform = 'scale(1)';
                }, 100);
                
                // Fade in content
                setTimeout(() => {
                    activeContent.classList.add('active');
                    activeContent.style.opacity = '1';
                }, 50);
            }
        }

        // Enhanced Modal functionality with Apple-style animations
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                // Force reflow
                modal.offsetHeight;
                modal.style.animation = 'fadeIn 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                
                const modalContent = modal.querySelector('.modal-content');
                if (modalContent) {
                    modalContent.style.animation = 'slideUp 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
                }
                
                // Focus management for accessibility
                const firstFocusable = modal.querySelector('input, button, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (firstFocusable) {
                    setTimeout(() => firstFocusable.focus(), 100);
                }
            }
        }
        
        function hideModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                const modalContent = modal.querySelector('.modal-content');
                
                // Apple-style fade out
                if (modalContent) {
                    modalContent.style.animation = 'fadeOut 0.2s ease-in forwards';
                }
                modal.style.animation = 'fadeOut 0.2s ease-in forwards';
                
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 200);
            }
        }

        // Apple-style button interactions
        function addButtonEffects() {
            document.querySelectorAll('.btn').forEach(button => {
                button.addEventListener('mousedown', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                button.addEventListener('mouseup', function() {
                    this.style.transform = 'scale(1)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });
        }

        // Enhanced file upload with Apple-style feedback
        function enhanceFileUpload() {
            const fileInput = document.getElementById('file');
            const fileUpload = document.querySelector('.file-upload');
            
            if (fileInput && fileUpload) {
                // Click to upload
                fileUpload.addEventListener('click', () => fileInput.click());
                
                // Drag and drop enhancement
                fileUpload.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#007aff';
                    this.style.background = 'rgba(0, 122, 255, 0.1)';
                    this.style.transform = 'scale(1.02)';
                });
                
                fileUpload.addEventListener('dragleave', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#d2d2d7';
                    this.style.background = 'rgba(255, 255, 255, 0.5)';
                    this.style.transform = 'scale(1)';
                });
                
                fileUpload.addEventListener('drop', function(e) {
                    e.preventDefault();
                    this.style.borderColor = '#d2d2d7';
                    this.style.background = 'rgba(255, 255, 255, 0.5)';
                    this.style.transform = 'scale(1)';
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        updateFileUploadDisplay(files[0]);
                    }
                });
                
                // File selection feedback
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        updateFileUploadDisplay(this.files[0]);
                    }
                });
            }
        }
        
        function updateFileUploadDisplay(file) {
            const fileUpload = document.querySelector('.file-upload');
            if (fileUpload) {
                // Apple-style success animation
                fileUpload.style.borderColor = '#30d158';
                fileUpload.style.background = 'rgba(48, 209, 88, 0.1)';
                fileUpload.innerHTML = `
                    <p style="color: #30d158; font-weight: 600;">✓ File Selected</p>
                    <p style="color: #1d1d1f; margin: 8px 0;"><strong>${file.name}</strong></p>
                    <small style="color: #86868b;">${formatFileSize(file.size)} • ${file.type || 'Unknown type'}</small>
                `;
                
                // Subtle success animation
                fileUpload.style.transform = 'scale(1.02)';
                setTimeout(() => {
                    fileUpload.style.transform = 'scale(1)';
                }, 200);
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Apple-style form validation
        function enhanceFormValidation() {
            document.querySelectorAll('input, select, textarea').forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    // Clear validation styling on input
                    this.style.borderColor = '#d2d2d7';
                    this.style.boxShadow = 'none';
                });
            });
        }
        
        function validateField(field) {
            const isValid = field.checkValidity();
            
            if (!isValid) {
                field.style.borderColor = '#ff3b30';
                field.style.boxShadow = '0 0 0 4px rgba(255, 59, 48, 0.1)';
            } else {
                field.style.borderColor = '#30d158';
                field.style.boxShadow = '0 0 0 4px rgba(48, 209, 88, 0.1)';
                
                // Remove success styling after 2 seconds
                setTimeout(() => {
                    field.style.borderColor = '#d2d2d7';
                    field.style.boxShadow = 'none';
                }, 2000);
            }
        }

        // Apple-style loading states
        function showLoadingState(button) {
            const originalText = button.textContent;
            button.disabled = true;
            button.style.opacity = '0.6';
            button.innerHTML = `
                <span style="display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>
                Loading...
            `;
            
            return originalText;
        }
        
        function hideLoadingState(button, originalText) {
            button.disabled = false;
            button.style.opacity = '1';
            button.textContent = originalText;
        }

        // Keyboard accessibility enhancements
        function enhanceKeyboardNavigation() {
            // Tab focus styling
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    document.body.classList.add('keyboard-navigating');
                }
            });
            
            document.addEventListener('mousedown', function() {
                document.body.classList.remove('keyboard-navigating');
            });
            
            // Modal keyboard controls
            document.addEventListener('keydown', function(e) {
                const visibleModal = document.querySelector('.modal[style*="display: block"]');
                if (visibleModal && e.key === 'Escape') {
                    const modalId = visibleModal.id;
                    hideModal(modalId);
                }
            });
        }

        // Smooth scroll for anchor links
        function enhanceSmoothScrolling() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }

        // Auto-refresh with visual feedback
        function setupAutoRefresh() {
            if (window.location.search.includes('action=dashboard')) {
                let countdown = 30;
                const refreshIndicator = document.createElement('div');
                refreshIndicator.style.cssText = `
                    position: fixed;
                    bottom: 24px;
                    right: 24px;
                    background: rgba(0, 0, 0, 0.8);
                    color: white;
                    padding: 12px 16px;
                    border-radius: 20px;
                    font-size: 14px;
                    z-index: 1000;
                    backdrop-filter: blur(10px);
                    -webkit-backdrop-filter: blur(10px);
                `;
                
                document.body.appendChild(refreshIndicator);
                
                const updateCountdown = () => {
                    refreshIndicator.textContent = `Auto-refresh in ${countdown}s`;
                    countdown--;
                    
                    if (countdown < 0) {
                        refreshIndicator.textContent = 'Refreshing...';
                        setTimeout(() => window.location.reload(), 500);
                    }
                };
                
                updateCountdown();
                setInterval(updateCountdown, 1000);
            }
        }

        // Initialize Apple-style enhancements
        document.addEventListener('DOMContentLoaded', function() {
            addButtonEffects();
            enhanceFileUpload();
            enhanceFormValidation();
            enhanceKeyboardNavigation();
            enhanceSmoothScrolling();
            setupAutoRefresh();
            
            // Add Apple-style focus indicators
            const style = document.createElement('style');
            style.textContent = `
                .keyboard-navigating *:focus {
                    outline: 2px solid #007aff;
                    outline-offset: 2px;
                    border-radius: 4px;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                @keyframes fadeOut {
                    from { opacity: 1; transform: scale(1); }
                    to { opacity: 0; transform: scale(0.95); }
                }
            `;
            document.head.appendChild(style);
        });

        // Apple-style notification system
        function showNotification(message, type = 'info', duration = 3000) {
            const notification = document.createElement('div');
            const colors = {
                success: { bg: 'rgba(48, 209, 88, 0.9)', text: 'white' },
                error: { bg: 'rgba(255, 59, 48, 0.9)', text: 'white' },
                info: { bg: 'rgba(0, 122, 255, 0.9)', text: 'white' }
            };
            
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 24px;
                background: ${colors[type].bg};
                color: ${colors[type].text};
                padding: 16px 20px;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 500;
                z-index: 1001;
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                animation: slideInRight 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
                max-width: 300px;
            `;
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in forwards';
                setTimeout(() => notification.remove(), 300);
            }, duration);
        }
        
        // Add slide animations for notifications
        const notificationStyles = document.createElement('style');
        notificationStyles.textContent = `
            @keyframes slideInRight {
                from { 
                    opacity: 0;
                    transform: translateX(100px);
                }
                to { 
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            
            @keyframes slideOutRight {
                from { 
                    opacity: 1;
                    transform: translateX(0);
                }
                to { 
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
        `;
        document.head.appendChild(notificationStyles);

        // Enhanced job status functions
        function showApprovalModal(jobId, printMethod) {
            document.getElementById('approve_job_id').value = jobId;
            document.getElementById('approve_print_method').value = printMethod;
            showModal('approvalModal');
        }

        function showRejectionModal(jobId) {
            document.getElementById('reject_job_id').value = jobId;
            showModal('rejectionModal');
        }

        function updateJobStatus(jobId, newStatus) {
            if (confirm('Update job status to: ' + newStatus + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=status_update';
                form.style.display = 'none';
                
                const jobIdInput = document.createElement('input');
                jobIdInput.name = 'job_id';
                jobIdInput.value = jobId;
                
                const statusInput = document.createElement('input');
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                form.appendChild(jobIdInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>

<?php
// Page rendering functions
function renderHomePage() {
    global $db;
    ?>
    <!-- Apple-style Navigation -->
    <nav class="nav" role="navigation" aria-label="Main navigation">
        <a href="?action=staff" role="button" aria-label="Access staff dashboard">
            <span>Staff Login</span>
        </a>
    </nav>

    <!-- Main Content Card with Apple Design -->
    <main class="card" role="main">
        <header style="text-align: center; margin-bottom: 32px;">
            <h2 style="margin-bottom: 8px;">Submit 3D Print Request</h2>
            <p style="color: #86868b; font-size: 19px;">Complete the form below to request a 3D print. We'll review your submission and send updates via email.</p>
        </header>

        <form method="POST" action="?action=submit" enctype="multipart/form-data" novalidate>
            <div class="grid">
                <!-- Student Information Section -->
                <section aria-labelledby="student-info-heading">
                    <h3 id="student-info-heading" style="margin-bottom: 24px; font-size: 22px; color: #1d1d1f;">Student Information</h3>
                    
                    <div class="form-group">
                        <label for="student_name">
                            Student Name <span style="color: #ff3b30;">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="student_name" 
                            name="student_name" 
                            required 
                            autocomplete="name"
                            aria-describedby="student_name_help"
                            placeholder="Enter your full name"
                        >
                        <small id="student_name_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Enter your full name as it appears in university records
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_email">
                            Email Address <span style="color: #ff3b30;">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="student_email" 
                            name="student_email" 
                            required
                            autocomplete="email"
                            aria-describedby="student_email_help"
                            placeholder="your.email@university.edu"
                        >
                        <small id="student_email_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            We'll send updates and notifications to this email
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="discipline">
                            Academic Discipline <span style="color: #ff3b30;">*</span>
                        </label>
                        <select 
                            id="discipline" 
                            name="discipline" 
                            required
                            aria-describedby="discipline_help"
                        >
                            <option value="">Choose your field of study</option>
                            <?php foreach (DISCIPLINES as $discipline): ?>
                                <option value="<?= htmlspecialchars($discipline) ?>"><?= htmlspecialchars($discipline) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="discipline_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Select your primary academic department
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_project">Class/Project Name</label>
                        <input 
                            type="text" 
                            id="class_project" 
                            name="class_project"
                            autocomplete="off"
                            aria-describedby="class_project_help"
                            placeholder="e.g., Engineering Design 101 Final Project"
                        >
                        <small id="class_project_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Optional: Help us track usage by class or project
                        </small>
                    </div>
                </section>
                
                <!-- Print Specifications Section -->
                <section aria-labelledby="print-specs-heading">
                    <h3 id="print-specs-heading" style="margin-bottom: 24px; font-size: 22px; color: #1d1d1f;">Print Specifications</h3>
                    
                    <div class="form-group">
                        <label for="print_method">
                            Print Method <span style="color: #ff3b30;">*</span>
                        </label>
                        <select 
                            id="print_method" 
                            name="print_method" 
                            required 
                            onchange="updateColors()"
                            aria-describedby="print_method_help"
                        >
                            <option value="">Select printing technology</option>
                            <?php foreach (PRINT_METHODS as $key => $method): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($method['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="print_method_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Different methods have different material options and pricing
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">
                            Material Color <span style="color: #ff3b30;">*</span>
                        </label>
                        <select 
                            id="color" 
                            name="color" 
                            required
                            aria-describedby="color_help"
                            disabled
                        >
                            <option value="">Select print method first</option>
                        </select>
                        <small id="color_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Available colors depend on your selected print method
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="file">
                            3D Model File <span style="color: #ff3b30;">*</span>
                        </label>
                        <div class="file-upload" role="button" tabindex="0" aria-describedby="file_help" 
                             onkeydown="if(event.key==='Enter'||event.key===' '){document.getElementById('file').click();}">
                            <p style="font-size: 17px; color: #1d1d1f; margin-bottom: 8px;">
                                📁 Click to select file or drag and drop
                            </p>
                            <p style="font-size: 15px; color: #86868b;">
                                Supported formats: STL, OBJ, 3MF • Maximum size: 50MB
                            </p>
                        </div>
                        <input 
                            type="file" 
                            id="file" 
                            name="file" 
                            accept=".stl,.obj,.3mf" 
                            required 
                            style="display: none;"
                            aria-describedby="file_help"
                        >
                        <small id="file_help" style="color: #86868b; font-size: 15px; margin-top: 4px; display: block;">
                            Upload your 3D model file. Ensure the model is properly scaled and oriented.
                        </small>
                    </div>
                </section>
            </div>
            
            <!-- Information Panel -->
            <aside class="alert alert-info" role="complementary" aria-labelledby="info-heading">
                <h4 id="info-heading" style="margin-bottom: 16px; font-size: 19px; font-weight: 600; color: #0051d0;">
                    📋 Important Information
                </h4>
                <div style="display: grid; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="background: rgba(0, 122, 255, 0.1); color: #007aff; padding: 4px 8px; border-radius: 8px; font-size: 14px; font-weight: 500;">PRICING</span>
                        <span>Minimum charge: <strong><?= formatCurrency(MINIMUM_CHARGE) ?></strong> • Filament: <strong><?= formatCurrency(FILAMENT_RATE) ?>/g</strong> • Resin: <strong><?= formatCurrency(RESIN_RATE) ?>/g</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="background: rgba(0, 122, 255, 0.1); color: #007aff; padding: 4px 8px; border-radius: 8px; font-size: 14px; font-weight: 500;">TIMELINE</span>
                        <span>Review takes <strong>1-2 business days</strong> • Full process typically <strong>3-7 days</strong></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="background: rgba(0, 122, 255, 0.1); color: #007aff; padding: 4px 8px; border-radius: 8px; font-size: 14px; font-weight: 500;">UPDATES</span>
                        <span>Email notifications at each step • Cost approval required before printing</span>
                    </div>
                </div>
            </aside>
            
            <!-- Submit Button -->
            <div style="text-align: center; margin-top: 32px;">
                <button type="submit" class="btn" style="font-size: 17px; padding: 16px 32px;" aria-describedby="submit_help">
                    Submit Print Request
                </button>
                <p id="submit_help" style="color: #86868b; font-size: 15px; margin-top: 8px;">
                    You'll receive a confirmation email once submitted
                </p>
            </div>
        </form>
    </main>

    <script>
        // Enhanced color update function with Apple-style transitions
        function updateColors() {
            const methodSelect = document.getElementById('print_method');
            const colorSelect = document.getElementById('color');
            const method = methodSelect.value;
            const methods = <?= json_encode(PRINT_METHODS) ?>;
            
            // Clear current options with fade effect
            colorSelect.style.opacity = '0.5';
            colorSelect.disabled = true;
            
            setTimeout(() => {
                colorSelect.innerHTML = '<option value="">Choose a color</option>';
                
                if (method && methods[method]) {
                    // Apple-style option building
                    methods[method].colors.forEach(color => {
                        const option = document.createElement('option');
                        option.value = color;
                        option.textContent = color;
                        colorSelect.appendChild(option);
                    });
                    
                    colorSelect.disabled = false;
                    colorSelect.style.opacity = '1';
                    
                    // Update help text dynamically
                    const helpText = document.getElementById('color_help');
                    if (helpText) {
                        helpText.textContent = `${methods[method].colors.length} colors available for ${methods[method].name}`;
                    }
                } else {
                    colorSelect.innerHTML = '<option value="">Select print method first</option>';
                    colorSelect.style.opacity = '0.5';
                }
            }, 150);
        }
        
        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitButton = form.querySelector('button[type="submit"]');
            
            form.addEventListener('submit', function(e) {
                // Apple-style loading state
                const originalText = showLoadingState(submitButton);
                
                // Client-side validation feedback
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.style.borderColor = '#ff3b30';
                        field.style.boxShadow = '0 0 0 4px rgba(255, 59, 48, 0.1)';
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    hideLoadingState(submitButton, originalText);
                    showNotification('Please fill in all required fields', 'error');
                    
                    // Focus first invalid field
                    const firstInvalid = form.querySelector('[required]:invalid, [style*="border-color: rgb(255, 59, 48)"]');
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            });
            
            // Real-time validation feedback
            const emailField = document.getElementById('student_email');
            if (emailField) {
                emailField.addEventListener('input', function() {
                    const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.value);
                    const helpText = document.getElementById('student_email_help');
                    
                    if (this.value && !isValid) {
                        this.style.borderColor = '#ff3b30';
                        helpText.textContent = 'Please enter a valid email address';
                        helpText.style.color = '#ff3b30';
                    } else {
                        this.style.borderColor = '#d2d2d7';
                        helpText.textContent = "We'll send updates and notifications to this email";
                        helpText.style.color = '#86868b';
                    }
                });
            }
        });
    </script>
    <?php
}

function renderStaffLogin() {
    ?>
    <div class="nav">
        <a href="?action=home">Back to Home</a>
    </div>

    <div class="card" style="max-width: 400px; margin: 50px auto;">
        <h2>Staff Login</h2>
        <form method="POST" action="?action=staff_login">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
    <?php
}

function renderDashboard() {
    global $db;
    $jobs = $db->getJobsByStatus();
    $jobsByStatus = [];
    
    foreach ($jobs as $job) {
        $jobsByStatus[$job['status']][] = $job;
    }
    ?>
    <div class="nav">
        <a href="?action=home">Public View</a>
        <a href="?action=staff&logout=1">Logout</a>
    </div>

    <h2>Staff Dashboard</h2>
    
    <div class="tabs">
        <div class="tab active" onclick="showTab('pending')">Pending (<?= count($jobsByStatus['pending'] ?? []) ?>)</div>
        <div class="tab" onclick="showTab('approved')">Approved (<?= count($jobsByStatus['approved'] ?? []) ?>)</div>
        <div class="tab" onclick="showTab('confirmed')">Confirmed (<?= count($jobsByStatus['confirmed'] ?? []) ?>)</div>
        <div class="tab" onclick="showTab('printing')">In Progress (<?= count($jobsByStatus['printing'] ?? []) ?>)</div>
        <div class="tab" onclick="showTab('completed')">Completed (<?= count($jobsByStatus['completed'] ?? []) ?>)</div>
    </div>

    <?php foreach (['pending', 'approved', 'confirmed', 'printing', 'completed'] as $status): ?>
        <div id="<?= $status ?>" class="tab-content <?= $status === 'pending' ? 'active' : '' ?>">
            <?php if (isset($jobsByStatus[$status])): ?>
                <?php foreach ($jobsByStatus[$status] as $job): ?>
                    <div class="job-item">
                        <h4>Job #<?= $job['id'] ?> - <?= htmlspecialchars($job['student_name']) ?></h4>
                        <p><strong>File:</strong> <?= htmlspecialchars($job['original_filename']) ?></p>
                        <p><strong>Method:</strong> <?= htmlspecialchars($job['print_method']) ?> (<?= htmlspecialchars($job['color']) ?>)</p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($job['student_email']) ?></p>
                        <p><strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($job['created_at'])) ?></p>
                        
                        <?php if ($status === 'pending'): ?>
                            <button class="btn" onclick="showApprovalModal(<?= $job['id'] ?>, '<?= $job['print_method'] ?>')">Approve</button>
                            <button class="btn btn-danger" onclick="showRejectionModal(<?= $job['id'] ?>)">Reject</button>
                        <?php else: ?>
                            <?php if ($job['cost']): ?>
                                <p><strong>Cost:</strong> <?= formatCurrency($job['cost']) ?></p>
                            <?php endif; ?>
                            <select onchange="updateJobStatus(<?= $job['id'] ?>, this.value)">
                                <?php foreach (STAGES as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $key === $status ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No jobs in this status.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('approvalModal')">&times;</span>
            <h3>Approve Job</h3>
            <form method="POST" action="?action=approve">
                <input type="hidden" id="approve_job_id" name="job_id">
                <input type="hidden" id="approve_print_method" name="print_method">
                
                <div class="form-group">
                    <label>Estimated Weight (grams)</label>
                    <input type="number" name="weight" step="0.1" required>
                </div>
                
                <div class="form-group">
                    <label>Estimated Time (hours)</label>
                    <input type="number" name="time_hours" step="0.5" value="2" required>
                </div>
                
                <button type="submit" class="btn btn-success">Approve Job</button>
                <button type="button" class="btn" onclick="hideModal('approvalModal')">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="hideModal('rejectionModal')">&times;</span>
            <h3>Reject Job</h3>
            <form method="POST" action="?action=reject">
                <input type="hidden" id="reject_job_id" name="job_id">
                
                <div class="form-group">
                    <label>Reason for Rejection</label>
                    <textarea name="reason" rows="4" required placeholder="Explain what needs to be fixed..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-danger">Reject Job</button>
                <button type="button" class="btn" onclick="hideModal('rejectionModal')">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function showApprovalModal(jobId, printMethod) {
            document.getElementById('approve_job_id').value = jobId;
            document.getElementById('approve_print_method').value = printMethod;
            showModal('approvalModal');
        }

        function showRejectionModal(jobId) {
            document.getElementById('reject_job_id').value = jobId;
            showModal('rejectionModal');
        }

        function updateJobStatus(jobId, newStatus) {
            if (confirm('Update job status to: ' + newStatus + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=status_update';
                form.style.display = 'none';
                
                const jobIdInput = document.createElement('input');
                jobIdInput.name = 'job_id';
                jobIdInput.value = jobId;
                
                const statusInput = document.createElement('input');
                statusInput.name = 'status';
                statusInput.value = newStatus;
                
                form.appendChild(jobIdInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    <?php
}

function renderConfirmation() {
    global $db;
    $token = $_GET['token'] ?? '';
    $job = $db->getJobByToken($token);
    
    if (!$job) {
        echo '<div class="alert alert-error">Invalid confirmation link.</div>';
        return;
    }
    ?>
    <h2>Confirm Your Print Job</h2>
    
    <div class="card">
        <h3>Job #<?= $job['id'] ?> Details</h3>
        
        <div class="grid">
            <div>
                <p><strong>Student:</strong> <?= htmlspecialchars($job['student_name']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($job['student_email']) ?></p>
                <p><strong>File:</strong> <?= htmlspecialchars($job['original_filename']) ?></p>
                <p><strong>Print Method:</strong> <?= htmlspecialchars($job['print_method']) ?></p>
                <p><strong>Color:</strong> <?= htmlspecialchars($job['color']) ?></p>
            </div>
            <div>
                <p><strong>Estimated Weight:</strong> <?= $job['weight'] ?>g</p>
                <p><strong>Estimated Time:</strong> <?= $job['time_hours'] ?> hours</p>
                <p><strong>Total Cost:</strong> <?= formatCurrency($job['cost']) ?></p>
                <p><strong>Status:</strong> <?= STAGES[$job['status']] ?></p>
            </div>
        </div>
        
        <?php if ($job['status'] === 'approved'): ?>
            <div class="alert alert-info">
                <h4>Action Required</h4>
                <p>Please confirm that you want to proceed with this print job. Once confirmed, your job will enter the print queue.</p>
            </div>
            
            <form method="POST" action="?action=confirm">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <button type="submit" name="action_type" value="confirm" class="btn btn-success">Confirm Job</button>
                <button type="submit" name="action_type" value="cancel" class="btn btn-danger">Cancel Job</button>
            </form>
        <?php else: ?>
            <div class="alert alert-info">
                This job has already been processed. Current status: <?= STAGES[$job['status']] ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function renderSuccess() {
    $jobId = $_GET['job_id'] ?? '';
    ?>
    <div class="card">
        <h2>✅ Request Submitted Successfully!</h2>
        
        <div class="alert alert-success">
            <h4>Your print request has been received</h4>
            <p><strong>Job ID:</strong> #<?= htmlspecialchars($jobId) ?></p>
        </div>
        
        <h3>What happens next?</h3>
        <ol>
            <li><strong>Review (1-2 business days):</strong> Our staff will review your file and specifications</li>
            <li><strong>Approval:</strong> You'll receive an email with cost estimate and confirmation link</li>
            <li><strong>Confirmation:</strong> You must confirm the job within 7 days</li>
            <li><strong>Printing:</strong> Your job enters the print queue</li>
            <li><strong>Pickup:</strong> You'll be notified when ready for pickup</li>
        </ol>
        
        <p><strong>Important:</strong> Save your Job ID (#<?= htmlspecialchars($jobId) ?>) for reference.</p>
        
        <a href="?action=home" class="btn">Submit Another Request</a>
    </div>
    <?php
}

function renderConfirmed() {
    $jobId = $_GET['job_id'] ?? '';
    ?>
    <div class="card">
        <h2>✅ Job Confirmed!</h2>
        
        <div class="alert alert-success">
            <p>Job #<?= htmlspecialchars($jobId) ?> has been confirmed and added to the print queue.</p>
        </div>
        
        <p>You'll receive email updates as your job progresses through printing and completion.</p>
        
        <a href="?action=home" class="btn">Submit Another Request</a>
    </div>
    <?php
}

function renderCancelled() {
    $jobId = $_GET['job_id'] ?? '';
    ?>
    <div class="card">
        <h2>Job Cancelled</h2>
        
        <div class="alert alert-info">
            <p>Job #<?= htmlspecialchars($jobId) ?> has been cancelled.</p>
        </div>
        
        <p>If you'd like to submit a new request with modifications, you can do so below.</p>
        
        <a href="?action=home" class="btn">Submit New Request</a>
    </div>
    <?php
}
?> 