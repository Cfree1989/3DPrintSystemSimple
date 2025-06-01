# 3D Print Lab Management System

Ultra-minimal PHP-based 3D print request management system with complete workflow automation.

## 🚀 Quick Start

### Requirements
- **PHP 7.4+** with SQLite support
- Web server (Apache, Nginx, or PHP built-in server)
- Email configuration for notifications

### Installation

1. **Download/Copy Files**: Place all 5 PHP files in your web directory
   ```
   index.php       (Main application)
   config.php      (Configuration) 
   database.php    (Database layer)
   utils.php       (Utility functions)
   email.php       (Email system)
   ```

2. **Set Permissions**: Ensure web server can create upload directory
   ```bash
   chmod 755 .
   chmod 644 *.php
   ```

3. **Start the Application**:
   
   **Option A: PHP Built-in Server (for testing)**
   ```bash
   php -S localhost:8000
   ```
   
   **Option B: Apache/Nginx**
   - Place files in web root (e.g., `/var/www/html/`)
   - Access via `http://your-domain.com/`

4. **Access the System**: Open `http://localhost:8000` in your browser

## 🎯 How to Use

### For Students

1. **Go to the homepage** - You'll see the submission form
2. **Fill out the form**:
   - Student name and email
   - Academic discipline 
   - Optional class/project info
   - Print method (Filament or Resin)
   - Color choice
   - Upload your 3D file (.stl, .obj, .3mf)
3. **Submit** - You'll get a Job ID and confirmation
4. **Wait for approval email** (1-2 business days)
5. **Click confirmation link** in approval email to proceed
6. **Get pickup notification** when complete

### For Staff

1. **Go to Staff Login** (link on homepage)
2. **Login with password**: `printlab2024`
3. **Use the Dashboard**:
   - **Pending Tab**: Review new submissions, approve/reject
   - **Approved Tab**: View approved jobs waiting for student confirmation
   - **Confirmed Tab**: Jobs ready for printing
   - **In Progress Tab**: Currently printing jobs
   - **Completed Tab**: Ready for pickup

4. **Approval Process**:
   - Click "Approve" on pending jobs
   - Enter estimated weight and time
   - System calculates cost automatically
   - Student gets approval email with confirmation link

5. **Status Updates**: Use dropdown menus to move jobs through workflow

## ⚙️ Configuration

Edit `config.php` to customize:

```php
// Email settings
define('LAB_EMAIL', 'your-lab@university.edu');
define('LAB_NAME', 'Your Lab Name');

// Pricing (per gram)
define('FILAMENT_RATE', 0.05);  // $0.05/gram
define('RESIN_RATE', 0.08);     // $0.08/gram
define('MINIMUM_CHARGE', 2.00); // $2.00 minimum

// Staff password (CHANGE THIS!)
define('STAFF_PASSWORD', 'your-secure-password');
```

## 📧 Email Setup

The system uses PHP's `mail()` function. Configure your server's mail settings:

1. **Linux/Apache**: Install and configure `sendmail` or `postfix`
2. **Windows/IIS**: Configure SMTP in `php.ini`
3. **Testing**: Check `php.ini` mail configuration

## 🔄 Complete Workflow

1. **Student submits** → Gets Job ID → Email confirmation
2. **Staff reviews** → Approves with cost → Student gets approval email  
3. **Student confirms** → Job enters print queue
4. **Staff updates status** through workflow → Student gets completion email
5. **Student picks up** → Job complete

## 🗂️ File Structure

```
/project-root/
├── index.php      # Main application (732 lines)
├── config.php     # Configuration (56 lines)  
├── database.php   # Database layer (164 lines)
├── utils.php      # Utilities (68 lines)
├── email.php      # Email system (166 lines)
├── uploads/       # File storage (auto-created)
└── printlab.db    # SQLite database (auto-created)
```

## 🔒 Security Features

- ✅ Input validation and sanitization
- ✅ File upload restrictions (.stl, .obj, .3mf only)
- ✅ SQL injection protection (prepared statements)
- ✅ XSS protection (HTML escaping)
- ✅ Token-based confirmation system
- ✅ Session-based staff authentication

## 🛠️ Troubleshooting

### Common Issues

**"Database connection failed"**
- Check PHP SQLite extension: `php -m | grep sqlite`
- Ensure write permissions in application directory

**"File upload failed"**
- Check `upload_max_filesize` in `php.ini` (should be ≥50MB)
- Verify `uploads/` directory permissions

**"Email not sending"**
- Test PHP mail configuration: `php -r "mail('test@example.com','Test','Test');"`
- Check server mail logs

**Staff login not working**
- Verify password in `config.php`
- Check if sessions are enabled: `php -m | grep session`

### Testing the System

1. **Submit a test job** as a student
2. **Check database**: SQLite file `printlab.db` should be created
3. **Login as staff** and approve the test job
4. **Verify email functionality** (approval/confirmation emails)
5. **Test complete workflow** through all statuses

## 📊 System Stats

- **Total Code**: ~1,186 lines across 5 files
- **Database**: 2 tables (jobs, audit_log)
- **Features**: 8-stage workflow, email automation, file management
- **Dependencies**: None (pure PHP + SQLite)
- **Deployment**: Single directory copy

## 🎓 Default Settings

- **Staff Password**: `printlab2024` (⚠️ CHANGE THIS!)
- **File Size Limit**: 50MB
- **Supported Formats**: .stl, .obj, .3mf
- **Pricing**: $0.05/g filament, $0.08/g resin, $2.00 minimum
- **Machine Time**: $2.00/hour

## 📞 Support

The system includes comprehensive error handling and user-friendly messages. Check the audit log table for detailed activity tracking.

For issues, verify:
1. PHP version and extensions
2. File permissions  
3. Email configuration
4. Database write access 