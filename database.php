<?php
// 3D Print Lab Management System - Database Operations
require_once 'config.php';

class Database {
    private $pdo;
    
    public function __construct() {
        $this->connect();
        $this->initSchema();
    }
    
    private function connect() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }
    
    private function initSchema() {
        $sql = "
        CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_name TEXT NOT NULL,
            student_email TEXT NOT NULL,
            discipline TEXT NOT NULL,
            class_project TEXT,
            print_method TEXT NOT NULL,
            color TEXT NOT NULL,
            filename TEXT NOT NULL,
            original_filename TEXT NOT NULL,
            file_size INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'uploaded',
            weight REAL,
            time_hours REAL,
            cost REAL,
            confirmation_token TEXT UNIQUE,
            rejection_reason TEXT,
            staff_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        
        CREATE TABLE IF NOT EXISTS audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            old_status TEXT,
            new_status TEXT,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES jobs (id)
        );
        ";
        
        $this->pdo->exec($sql);
    }
    
    // Create new job
    public function createJob($data) {
        $sql = "INSERT INTO jobs (student_name, student_email, discipline, class_project, 
                print_method, color, filename, original_filename, file_size, confirmation_token) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $data['student_name'], $data['student_email'], $data['discipline'],
            $data['class_project'], $data['print_method'], $data['color'],
            $data['filename'], $data['original_filename'], $data['file_size'],
            $data['confirmation_token']
        ]);
        
        $jobId = $this->pdo->lastInsertId();
        $this->logAction($jobId, 'created', null, 'uploaded');
        
        return $jobId;
    }
    
    // Get jobs by status
    public function getJobsByStatus($status = null) {
        if ($status) {
            $sql = "SELECT * FROM jobs WHERE status = ? ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$status]);
        } else {
            $sql = "SELECT * FROM jobs ORDER BY created_at DESC";
            $stmt = $this->pdo->query($sql);
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get job by ID
    public function getJob($id) {
        $sql = "SELECT * FROM jobs WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get job by token
    public function getJobByToken($token) {
        $sql = "SELECT * FROM jobs WHERE confirmation_token = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Update job status
    public function updateStatus($id, $newStatus, $details = null) {
        $job = $this->getJob($id);
        if (!$job) return false;
        
        $sql = "UPDATE jobs SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$newStatus, $id]);
        
        $this->logAction($id, 'status_changed', $job['status'], $newStatus, $details);
        return true;
    }
    
    // Approve job
    public function approveJob($id, $weight, $timeHours, $cost) {
        $sql = "UPDATE jobs SET status = 'approved', weight = ?, time_hours = ?, cost = ?, 
                updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$weight, $timeHours, $cost, $id]);
        
        $this->logAction($id, 'approved', 'pending', 'approved', 
                        "Cost: $cost, Weight: {$weight}g, Time: {$timeHours}h");
        return true;
    }
    
    // Reject job
    public function rejectJob($id, $reason) {
        $sql = "UPDATE jobs SET status = 'rejected', rejection_reason = ?, 
                updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$reason, $id]);
        
        $this->logAction($id, 'rejected', 'pending', 'rejected', $reason);
        return true;
    }
    
    // Log action
    private function logAction($jobId, $action, $oldStatus, $newStatus, $details = null) {
        $sql = "INSERT INTO audit_log (job_id, action, old_status, new_status, details) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jobId, $action, $oldStatus, $newStatus, $details]);
    }
    
    // Get audit log for job
    public function getAuditLog($jobId) {
        $sql = "SELECT * FROM audit_log WHERE job_id = ? ORDER BY timestamp DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$jobId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

?> 