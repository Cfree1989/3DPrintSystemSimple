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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px 0; margin-bottom: 30px; }
        .header h1 { text-align: center; }
        .nav { text-align: center; margin: 20px 0; }
        .nav a { margin: 0 15px; color: #3498db; text-decoration: none; }
        .nav a:hover { text-decoration: underline; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group select, .form-group textarea { 
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; 
        }
        .btn { background: #3498db; color: white; padding: 12px 24px; border: none; 
               border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2980b9; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .alert { padding: 15px; margin: 20px 0; border-radius: 5px; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .tabs { display: flex; border-bottom: 1px solid #ddd; margin-bottom: 20px; }
        .tab { padding: 12px 24px; cursor: pointer; border-bottom: 2px solid transparent; }
        .tab.active { border-bottom-color: #3498db; color: #3498db; font-weight: bold; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; margin: 50px auto; padding: 20px; width: 90%; 
                        max-width: 600px; border-radius: 8px; }
        .close { float: right; font-size: 24px; cursor: pointer; }
        .job-list { margin: 20px 0; }
        .job-item { padding: 15px; border: 1px solid #ddd; margin: 10px 0; border-radius: 5px; }
        .job-item:hover { background: #f8f9fa; }
        .file-upload { border: 2px dashed #ddd; padding: 40px; text-align: center; 
                      border-radius: 8px; margin: 20px 0; cursor: pointer; }
        .file-upload:hover { border-color: #3498db; background: #f8f9fa; }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .grid { grid-template-columns: 1fr; }
            .tabs { flex-direction: column; }
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
        // Tab functionality
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelector(`[onclick="showTab('${tabName}')"]`).classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }

        // Modal functionality
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // File upload enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('file');
            const fileUpload = document.querySelector('.file-upload');
            
            if (fileInput && fileUpload) {
                fileUpload.addEventListener('click', () => fileInput.click());
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        fileUpload.innerHTML = `<strong>Selected:</strong> ${this.files[0].name}`;
                    }
                });
            }
        });

        // Auto-refresh dashboard
        if (window.location.search.includes('action=dashboard')) {
            setTimeout(() => window.location.reload(), 30000); // 30 seconds
        }
    </script>
</body>
</html>

<?php
// Page rendering functions
function renderHomePage() {
    global $db;
    ?>
    <div class="nav">
        <a href="?action=staff">Staff Login</a>
    </div>

    <div class="card">
        <h2>Submit 3D Print Request</h2>
        <form method="POST" action="?action=submit" enctype="multipart/form-data">
            <div class="grid">
                <div>
                    <div class="form-group">
                        <label for="student_name">Student Name *</label>
                        <input type="text" id="student_name" name="student_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="student_email">Email Address *</label>
                        <input type="email" id="student_email" name="student_email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="discipline">Academic Discipline *</label>
                        <select id="discipline" name="discipline" required>
                            <option value="">Select...</option>
                            <?php foreach (DISCIPLINES as $discipline): ?>
                                <option value="<?= $discipline ?>"><?= $discipline ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="class_project">Class/Project</label>
                        <input type="text" id="class_project" name="class_project">
                    </div>
                </div>
                
                <div>
                    <div class="form-group">
                        <label for="print_method">Print Method *</label>
                        <select id="print_method" name="print_method" required onchange="updateColors()">
                            <option value="">Select...</option>
                            <?php foreach (PRINT_METHODS as $key => $method): ?>
                                <option value="<?= $key ?>"><?= $method['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="color">Color *</label>
                        <select id="color" name="color" required>
                            <option value="">Select print method first</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>3D Model File *</label>
                        <div class="file-upload">
                            <p>Click to select file or drag and drop</p>
                            <p><small>Supported: .stl, .obj, .3mf (max 50MB)</small></p>
                        </div>
                        <input type="file" id="file" name="file" accept=".stl,.obj,.3mf" required style="display: none;">
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <h4>Important Information:</h4>
                <ul>
                    <li>Minimum charge: <?= formatCurrency(MINIMUM_CHARGE) ?></li>
                    <li>Pricing: Filament <?= formatCurrency(FILAMENT_RATE) ?>/g, Resin <?= formatCurrency(RESIN_RATE) ?>/g</li>
                    <li>Review takes 1-2 business days</li>
                    <li>You'll receive email notifications throughout the process</li>
                </ul>
            </div>
            
            <button type="submit" class="btn">Submit Print Request</button>
        </form>
    </div>

    <script>
        function updateColors() {
            const method = document.getElementById('print_method').value;
            const colorSelect = document.getElementById('color');
            const methods = <?= json_encode(PRINT_METHODS) ?>;
            
            colorSelect.innerHTML = '<option value="">Select color...</option>';
            
            if (method && methods[method]) {
                methods[method].colors.forEach(color => {
                    const option = document.createElement('option');
                    option.value = color;
                    option.textContent = color;
                    colorSelect.appendChild(option);
                });
            }
        }
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

        function updateJobStatus(jobId, status) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?action=status_update';
            
            const jobIdInput = document.createElement('input');
            jobIdInput.type = 'hidden';
            jobIdInput.name = 'job_id';
            jobIdInput.value = jobId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            
            form.appendChild(jobIdInput);
            form.appendChild(statusInput);
            document.body.appendChild(form);
            form.submit();
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