<?php
// 3D Print Lab Management System - Email Functions
require_once 'config.php';

class EmailService {
    
    // Send email (wrapper for PHP mail function)
    private function sendEmail($to, $subject, $message) {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . LAB_NAME . " <" . LAB_EMAIL . ">\r\n";
        $headers .= "Reply-To: " . LAB_EMAIL . "\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
    
    // Email template wrapper
    private function getTemplate($title, $content) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 15px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #3498db; 
                         color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>" . LAB_NAME . "</h2>
                    <h3>$title</h3>
                </div>
                <div class='content'>
                    $content
                </div>
                <div class='footer'>
                    <p>Questions? Contact us at " . LAB_EMAIL . "</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    // Submission confirmation email
    public function sendSubmissionConfirmation($job) {
        $subject = "3D Print Request Submitted - Job #" . $job['id'];
        
        $content = "
        <h3>Hi " . htmlspecialchars($job['student_name']) . ",</h3>
        <p>Your 3D print request has been successfully submitted!</p>
        
        <h4>Job Details:</h4>
        <ul>
            <li><strong>Job ID:</strong> #" . $job['id'] . "</li>
            <li><strong>File:</strong> " . htmlspecialchars($job['original_filename']) . "</li>
            <li><strong>Print Method:</strong> " . htmlspecialchars($job['print_method']) . "</li>
            <li><strong>Color:</strong> " . htmlspecialchars($job['color']) . "</li>
        </ul>
        
        <h4>What's Next?</h4>
        <p>Our staff will review your submission within 1-2 business days. You'll receive an email when:</p>
        <ul>
            <li>Your job is approved (with cost estimate)</li>
            <li>Your job needs modifications</li>
        </ul>
        
        <p><strong>Important:</strong> Keep this job ID for your records: <strong>#" . $job['id'] . "</strong></p>
        ";
        
        $message = $this->getTemplate("Print Request Submitted", $content);
        return $this->sendEmail($job['student_email'], $subject, $message);
    }
    
    // Job approval email
    public function sendApprovalEmail($job, $confirmUrl) {
        $subject = "3D Print Request Approved - Job #" . $job['id'];
        
        $content = "
        <h3>Great news, " . htmlspecialchars($job['student_name']) . "!</h3>
        <p>Your 3D print request has been approved.</p>
        
        <h4>Job Details:</h4>
        <ul>
            <li><strong>Job ID:</strong> #" . $job['id'] . "</li>
            <li><strong>File:</strong> " . htmlspecialchars($job['original_filename']) . "</li>
            <li><strong>Estimated Weight:</strong> " . $job['weight'] . "g</li>
            <li><strong>Estimated Time:</strong> " . $job['time_hours'] . " hours</li>
            <li><strong>Total Cost:</strong> $" . number_format($job['cost'], 2) . "</li>
        </ul>
        
        <h4>Action Required:</h4>
        <p>Please review and confirm your print job to proceed:</p>
        <a href='$confirmUrl' class='button'>Review & Confirm Job</a>
        
        <p><strong>Important:</strong> You must confirm within 7 days or your job will be cancelled.</p>
        ";
        
        $message = $this->getTemplate("Print Request Approved", $content);
        return $this->sendEmail($job['student_email'], $subject, $message);
    }
    
    // Job rejection email
    public function sendRejectionEmail($job) {
        $subject = "3D Print Request - Modifications Needed - Job #" . $job['id'];
        
        $content = "
        <h3>Hi " . htmlspecialchars($job['student_name']) . ",</h3>
        <p>We've reviewed your 3D print request and it needs some modifications before we can proceed.</p>
        
        <h4>Job Details:</h4>
        <ul>
            <li><strong>Job ID:</strong> #" . $job['id'] . "</li>
            <li><strong>File:</strong> " . htmlspecialchars($job['original_filename']) . "</li>
        </ul>
        
        <h4>Issues to Address:</h4>
        <div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>
            " . nl2br(htmlspecialchars($job['rejection_reason'])) . "
        </div>
        
        <h4>Next Steps:</h4>
        <p>Please make the necessary changes and submit a new request. If you have questions, contact us at " . LAB_EMAIL . "</p>
        ";
        
        $message = $this->getTemplate("Modifications Needed", $content);
        return $this->sendEmail($job['student_email'], $subject, $message);
    }
    
    // Job completion email
    public function sendCompletionEmail($job) {
        $subject = "3D Print Complete - Ready for Pickup - Job #" . $job['id'];
        
        $content = "
        <h3>Hi " . htmlspecialchars($job['student_name']) . ",</h3>
        <p>Your 3D print is complete and ready for pickup!</p>
        
        <h4>Job Details:</h4>
        <ul>
            <li><strong>Job ID:</strong> #" . $job['id'] . "</li>
            <li><strong>File:</strong> " . htmlspecialchars($job['original_filename']) . "</li>
            <li><strong>Amount Due:</strong> $" . number_format($job['cost'], 2) . "</li>
        </ul>
        
        <h4>Pickup Information:</h4>
        <p>Please come to the " . LAB_NAME . " during operating hours:</p>
        <ul>
            <li>Monday-Friday: 9:00 AM - 5:00 PM</li>
            <li>Bring your student ID</li>
            <li>Payment due at pickup (cash or card)</li>
        </ul>
        
        <p><strong>Note:</strong> Please pick up within 30 days or additional storage fees may apply.</p>
        ";
        
        $message = $this->getTemplate("Print Ready for Pickup", $content);
        return $this->sendEmail($job['student_email'], $subject, $message);
    }
}

?> 