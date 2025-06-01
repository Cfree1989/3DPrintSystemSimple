<?php
// 3D Print Lab Management System - Main Application

// Start session before any output
session_start();

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

// Handle file download requests
if ($action === 'download_file') {
    handleFileDownload();
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

// Handle file download
function handleFileDownload() {
    if (!isStaffAuthenticated()) {
        header("Location: ?action=staff&message=Authentication required");
        exit;
    }
    
    global $db;
    
    $jobId = (int)($_GET['job_id'] ?? 0);
    if (!$jobId) {
        header("Location: ?action=dashboard&message=Invalid job ID");
        exit;
    }
    
    $job = $db->getJob($jobId);
    if (!$job) {
        header("Location: ?action=dashboard&message=Job not found");
        exit;
    }
    
    // Generate the filename as stored on disk
    $filename = generateJobFilename($jobId, $job['original_filename']);
    $filepath = UPLOAD_DIR . $filename;
    
    if (!file_exists($filepath)) {
        header("Location: ?action=dashboard&message=File not found on disk");
        exit;
    }
    
    // Serve the file
    $originalFilename = $job['original_filename'];
    $mimeType = getMimeType($filepath);
    
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: attachment; filename="' . basename($originalFilename) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Clear output buffer
    ob_clean();
    flush();
    
    // Read file and output
    readfile($filepath);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= LAB_NAME ?> - 3D Print Request System</title>
    <link rel="stylesheet" href="assets/styles.css">
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

    <script src="assets/scripts.js"></script>
    <script>
        // Pass PHP constants to JavaScript
        const PRINT_METHODS = <?= json_encode(PRINT_METHODS) ?>;
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
                        <p><strong>File:</strong> <?= htmlspecialchars($job['original_filename']) ?>
                            <a href="?action=download_file&job_id=<?= $job['id'] ?>" 
                               class="btn" 
                               style="font-size: 14px; padding: 8px 16px; margin-left: 12px; display: inline-flex; align-items: center; gap: 6px;"
                               title="Download <?= htmlspecialchars($job['original_filename']) ?>">
                                📁 Open File
                            </a>
                        </p>
                        <p><strong>Method:</strong> <?= htmlspecialchars($job['print_method']) ?> (<?= htmlspecialchars($job['color']) ?>)</p>
                        <p><strong>Email:</strong> <?= htmlspecialchars($job['student_email']) ?></p>
                        <p><strong>Submitted:</strong> <?= date('M j, Y g:i A', strtotime($job['created_at'])) ?></p>
                        
                        <?php if ($status === 'pending'): ?>
                            <div style="margin-top: 16px; display: flex; gap: 12px; flex-wrap: wrap;">
                                <button class="btn" onclick="showApprovalModal(<?= $job['id'] ?>, '<?= $job['print_method'] ?>')">Approve</button>
                                <button class="btn btn-danger" onclick="showRejectionModal(<?= $job['id'] ?>)">Reject</button>
                            </div>
                        <?php else: ?>
                            <?php if ($job['cost']): ?>
                                <p><strong>Cost:</strong> <?= formatCurrency($job['cost']) ?></p>
                            <?php endif; ?>
                            <div style="margin-top: 16px;">
                                <select onchange="updateJobStatus(<?= $job['id'] ?>, this.value)" style="margin-top: 0;">
                                    <?php foreach (STAGES as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $key === $status ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
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