# 🎨 Customization Guide
## How Much Can You Change in Your 3D Print Lab System?

**Short Answer**: A LOT! This system is designed to be highly customizable.

---

## 🎯 **Easy Changes (5 minutes)**

### **Basic Branding & Settings** (`config.php`)
```php
// Change lab name and email
define('LAB_NAME', 'Your Makerspace Name');
define('LAB_EMAIL', 'contact@yourlab.com');

// Adjust pricing
define('FILAMENT_RATE', 0.10);  // Double the rate
define('RESIN_RATE', 0.15);     // Custom rate
define('MINIMUM_CHARGE', 5.00); // Higher minimum

// Add more file types
define('ALLOWED_EXTENSIONS', ['stl', 'obj', '3mf', 'ply', 'x3d']);

// Increase file size limit
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB

// Change staff password
define('STAFF_PASSWORD', 'your-secure-password-2024');
```

### **Add More Print Methods & Colors** (`config.php`)
```php
define('PRINT_METHODS', [
    'filament' => [
        'name' => 'Filament (FDM)',
        'colors' => ['Black', 'White', 'Red', 'Blue', 'Green', 'Yellow', 'Orange', 'Purple']
    ],
    'resin' => [
        'name' => 'Resin (SLA)', 
        'colors' => ['Clear', 'Black', 'White', 'Gray', 'Translucent']
    ],
    'metal' => [
        'name' => 'Metal Printing',
        'colors' => ['Steel', 'Aluminum', 'Titanium']
    ],
    'ceramic' => [
        'name' => 'Ceramic Printing',
        'colors' => ['White', 'Natural', 'Colored']
    ]
]);
```

### **Add More Academic Disciplines** (`config.php`)
```php
define('DISCIPLINES', [
    'Engineering', 'Computer Science', 'Art & Design', 'Architecture',
    'Biology', 'Chemistry', 'Physics', 'Mathematics', 'Medicine',
    'Business', 'Education', 'Psychology', 'Music', 'Theater',
    'Geology', 'Archaeology', 'Photography', 'Other'
]);
```

---

## 🎨 **Medium Changes (30 minutes)**

### **Visual Customization** (`index.php` - CSS section)
```css
/* Change color scheme */
.header { background: #e74c3c; } /* Red header */
.btn { background: #27ae60; }    /* Green buttons */

/* Add your logo */
.header::before {
    content: url('logo.png');
    display: block;
    margin-bottom: 10px;
}

/* Custom fonts */
body { font-family: 'Arial', 'Helvetica', sans-serif; }

/* Dark theme */
body { background: #2c3e50; color: white; }
.card { background: #34495e; }
```

### **Add More Form Fields** (`index.php` - renderHomePage function)
```php
// Add student ID field
<div class="form-group">
    <label for="student_id">Student ID *</label>
    <input type="text" id="student_id" name="student_id" required>
</div>

// Add phone number
<div class="form-group">
    <label for="phone">Phone Number</label>
    <input type="tel" id="phone" name="phone">
</div>

// Add deadline field
<div class="form-group">
    <label for="deadline">Needed By Date</label>
    <input type="date" id="deadline" name="deadline">
</div>
```

### **Modify Pricing Logic** (`utils.php`)
```php
function calculateCost($method, $weight, $time_hours = 1) {
    $rate = match($method) {
        'resin' => RESIN_RATE,
        'metal' => 0.50,     // $0.50/gram for metal
        'ceramic' => 0.25,   // $0.25/gram for ceramic  
        default => FILAMENT_RATE
    };
    
    // Add complexity factor
    $complexity_multiplier = 1.0;
    if ($weight > 100) $complexity_multiplier = 1.2; // 20% more for large prints
    
    $cost = ($weight * $rate * $complexity_multiplier) + ($time_hours * 3); // $3/hour
    return max($cost, MINIMUM_CHARGE);
}
```

---

## 🔧 **Advanced Changes (1-2 hours)**

### **Add New Workflow Stages** (`config.php` + `database.php`)
```php
// config.php - Add new stages
define('STAGES', [
    'uploaded' => 'Uploaded',
    'pending' => 'Pending Review',
    'quote_sent' => 'Quote Sent', // NEW
    'approved' => 'Approved',
    'confirmed' => 'Confirmed',
    'materials_ordered' => 'Materials Ordered', // NEW
    'queued' => 'In Queue',
    'printing' => 'Printing',
    'post_processing' => 'Post Processing', // NEW
    'quality_check' => 'Quality Check', // NEW
    'completed' => 'Completed',
    'picked_up' => 'Picked Up'
]);
```

### **Add Multi-File Upload** (`index.php`)
```php
// Change file input to multiple
<input type="file" id="file" name="files[]" accept=".stl,.obj,.3mf" multiple required>

// Update JavaScript
fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
        let fileList = Array.from(this.files).map(f => f.name).join(', ');
        fileUpload.innerHTML = `<strong>Selected:</strong> ${fileList}`;
    }
});
```

### **Add User Accounts System** (New files needed)
- Create `users.php` for user management
- Add login/registration for students  
- Track job history per user
- Add user dashboards

### **Add Payment Integration** 
```php
// Add to config.php
define('PAYMENT_ENABLED', true);
define('STRIPE_PUBLIC_KEY', 'your_stripe_key');

// Modify approval workflow to include payment
// Add payment confirmation step
```

---

## 🚀 **Major Expansions (Half day+)**

### **Multi-Lab Support**
- Add lab selection to forms
- Separate pricing per lab
- Lab-specific staff accounts
- Cross-lab job transfers

### **Advanced File Processing**
- 3D file preview/thumbnail generation
- Automatic cost estimation from file analysis
- File repair suggestions
- Print time estimation

### **Inventory Management**
- Material tracking
- Low stock alerts
- Automatic reorder points
- Cost tracking per material

### **Advanced Reporting**
- Usage statistics dashboard
- Revenue reporting
- Popular print analysis
- Student usage patterns

### **API Integration**
- Slicer software integration
- Printer status monitoring
- External payment systems
- Student information systems

---

## 📊 **What's Hardest vs Easiest to Change**

### **✅ EASIEST (Minutes)**
- Colors, text, branding
- Pricing rates
- Form fields
- Email templates
- Workflow stage names

### **🟡 MEDIUM (Hours)** 
- Visual design/CSS
- New form fields with database
- Pricing logic
- New workflow stages
- File type restrictions

### **🔴 HARDEST (Days)**
- Complete workflow redesign
- User account system
- Payment integration
- Multi-tenant support
- Real-time printer monitoring

---

## 🎯 **Recommended Starting Points**

1. **Customize branding** (logo, colors, lab name)
2. **Adjust pricing** for your materials
3. **Add specific form fields** your lab needs
4. **Modify workflow stages** to match your process
5. **Customize email templates** with your branding

---

## 🛠️ **Safe Modification Tips**

1. **Always backup** before making changes
2. **Test in small steps** - change one thing at a time
3. **Use the test script** (`test_system.php`) after changes
4. **Keep configuration changes** in `config.php` when possible
5. **Document your changes** for future reference

---

## 💡 **The System Architecture Supports**

✅ **Easy customization** through configuration  
✅ **Modular design** - change one part without breaking others  
✅ **Clean separation** of data, logic, and presentation  
✅ **Standard technologies** - any web developer can modify  
✅ **Scalable foundation** - can grow with your needs  

**Bottom Line: This system is designed to be YOUR system. Customize away!** 🎨 