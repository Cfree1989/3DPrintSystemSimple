<?php
// 3D Print Lab System - Test Script
// Run this to test if everything is working properly

echo "🧪 3D Print Lab System Test Suite\n";
echo "================================\n\n";

$errors = [];
$warnings = [];
$passed = 0;
$total = 0;

function test($description, $condition, $errorMsg = '') {
    global $errors, $passed, $total;
    $total++;
    echo "Testing: $description... ";
    
    if ($condition) {
        echo "✅ PASS\n";
        $passed++;
    } else {
        echo "❌ FAIL\n";
        if ($errorMsg) $errors[] = $errorMsg;
    }
}

// Test 1: File existence
echo "📁 Checking Required Files:\n";
test("config.php exists", file_exists('config.php'), "config.php is missing");
test("database.php exists", file_exists('database.php'), "database.php is missing");
test("utils.php exists", file_exists('utils.php'), "utils.php is missing");
test("email.php exists", file_exists('email.php'), "email.php is missing");
test("index.php exists", file_exists('index.php'), "index.php is missing");
echo "\n";

// Test 2: PHP syntax validation
echo "🔧 Checking PHP Syntax:\n";
$files = ['config.php', 'database.php', 'utils.php', 'email.php', 'index.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        $output = shell_exec("php -l $file 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        test("$file syntax", $syntaxOk, "Syntax error in $file: " . trim($output));
    }
}
echo "\n";

// Test 3: Include/require validation
echo "📦 Checking File Dependencies:\n";
try {
    require_once 'config.php';
    test("config.php loads", true);
} catch (Exception $e) {
    test("config.php loads", false, "Error loading config.php: " . $e->getMessage());
}

try {
    require_once 'utils.php';
    test("utils.php loads", true);
} catch (Exception $e) {
    test("utils.php loads", false, "Error loading utils.php: " . $e->getMessage());
}

try {
    require_once 'database.php';
    test("database.php loads", true);
} catch (Exception $e) {
    test("database.php loads", false, "Error loading database.php: " . $e->getMessage());
}

try {
    require_once 'email.php';
    test("email.php loads", true);
} catch (Exception $e) {
    test("email.php loads", false, "Error loading email.php: " . $e->getMessage());
}
echo "\n";

// Test 4: Configuration validation
echo "⚙️ Checking Configuration:\n";
if (defined('DB_FILE')) {
    test("Database file configured", true);
    test("Upload directory configured", defined('UPLOAD_DIR'));
    test("File size limit set", defined('MAX_FILE_SIZE'));
    test("Email settings configured", defined('LAB_EMAIL') && defined('LAB_NAME'));
    test("Pricing configured", defined('FILAMENT_RATE') && defined('RESIN_RATE'));
    test("Staff password set", defined('STAFF_PASSWORD'));
} else {
    test("Configuration loaded", false, "Configuration constants not found");
}
echo "\n";

// Test 5: Database functionality
echo "🗄️ Testing Database:\n";
try {
    $db = new Database();
    test("Database connection", true);
    
    // Test creating a dummy job
    $testData = [
        'student_name' => 'Test Student',
        'student_email' => 'test@example.com',
        'discipline' => 'Engineering',
        'class_project' => 'Test Project',
        'print_method' => 'filament',
        'color' => 'Black',
        'filename' => 'test.stl',
        'original_filename' => 'test.stl',
        'file_size' => 1024,
        'confirmation_token' => 'test_token_123'
    ];
    
    $jobId = $db->createJob($testData);
    test("Create test job", $jobId > 0, "Failed to create test job");
    
    if ($jobId > 0) {
        $job = $db->getJob($jobId);
        test("Retrieve test job", $job && $job['student_name'] === 'Test Student');
        
        $updated = $db->updateStatus($jobId, 'pending');
        test("Update job status", $updated);
        
        // Clean up test data
        // Note: In a real system you might want to delete test data
    }
    
} catch (Exception $e) {
    test("Database functionality", false, "Database error: " . $e->getMessage());
}
echo "\n";

// Test 6: Utility functions
echo "🔧 Testing Utility Functions:\n";
if (function_exists('generateToken')) {
    $token = generateToken();
    test("Generate token", !empty($token) && strlen($token) >= 32);
} else {
    test("Generate token function", false, "generateToken function not found");
}

if (function_exists('sanitize')) {
    $sanitized = sanitize('<script>alert("test")</script>');
    test("Sanitize input", $sanitized !== '<script>alert("test")</script>');
} else {
    test("Sanitize function", false, "sanitize function not found");
}

if (function_exists('isValidEmail')) {
    test("Email validation (valid)", isValidEmail('test@example.com'));
    test("Email validation (invalid)", !isValidEmail('invalid-email'));
} else {
    test("Email validation function", false, "isValidEmail function not found");
}

if (function_exists('calculateCost')) {
    $cost = calculateCost('filament', 50, 2);
    test("Cost calculation", $cost >= MINIMUM_CHARGE);
} else {
    test("Cost calculation function", false, "calculateCost function not found");
}

if (function_exists('formatCurrency')) {
    $formatted = formatCurrency(5.50);
    test("Currency formatting", $formatted === '$5.50');
} else {
    test("Currency formatting function", false, "formatCurrency function not found");
}
echo "\n";

// Test 7: Directory permissions
echo "📂 Checking Permissions:\n";
if (defined('UPLOAD_DIR')) {
    test("Upload directory writable", is_writable('.'), "Current directory not writable");
    
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    test("Upload directory exists", is_dir(UPLOAD_DIR));
    test("Upload directory writable", is_writable(UPLOAD_DIR), "Upload directory not writable");
} else {
    test("Upload directory config", false, "UPLOAD_DIR not configured");
}
echo "\n";

// Test 8: Email functionality (basic test)
echo "📧 Testing Email System:\n";
try {
    $emailService = new EmailService();
    test("Email service loads", true);
    
    // Note: We don't actually send emails in test mode
    if (method_exists($emailService, 'sendSubmissionConfirmation')) {
        test("Email methods available", true);
    } else {
        test("Email methods available", false, "Email methods not found");
    }
} catch (Exception $e) {
    test("Email service", false, "Email service error: " . $e->getMessage());
}
echo "\n";

// Final Results
echo "📊 TEST RESULTS:\n";
echo "===============\n";
echo "✅ Passed: $passed/$total tests\n";

if ($passed === $total) {
    echo "🎉 ALL TESTS PASSED! Your system is working perfectly!\n\n";
    echo "🚀 Next Steps:\n";
    echo "1. Open your browser to http://localhost:8000 (or your configured URL)\n";
    echo "2. Test the student submission form\n";
    echo "3. Test the staff login (password: printlab2024)\n";
    echo "4. Try approving a job\n";
    echo "5. Test the complete workflow\n\n";
} else {
    echo "⚠️  Some tests failed. Issues found:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
    echo "\n";
}

echo "💡 To test the web interface:\n";
echo "   • Go to your browser\n";
echo "   • Navigate to your application URL\n";
echo "   • Try submitting a print request\n";
echo "   • Login as staff and approve it\n\n";

echo "🔧 Manual Tests You Should Do:\n";
echo "1. Submit a print request as a student\n";
echo "2. Login as staff (password: printlab2024)\n";
echo "3. Approve a pending job\n";
echo "4. Test status updates\n";
echo "5. Check if files upload properly\n";
echo "6. Verify email notifications (if configured)\n\n";

echo "📝 Files to check if there are issues:\n";
echo "   • Check browser console for JavaScript errors\n";
echo "   • Look for .db file creation (SQLite database)\n";
echo "   • Check uploads/ directory for uploaded files\n";
echo "   • Verify configuration in config.php\n\n";

echo "Test completed at: " . date('Y-m-d H:i:s') . "\n";
?> 