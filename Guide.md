# 🔰 Complete Beginner's Guide
## How to Run Your 3D Print Lab System (No Experience Required!)

### 📍 Where You Are Now
You have a **Python server** running, but this is a **PHP application**. Think of it like trying to play a DVD in a CD player - wrong format! You need PHP to make this work.

---

## 🎯 Step 1: Stop the Python Server
1. **Look at your terminal** (where it says "Serving HTTP on :: port 8000")
2. **Press `Ctrl + C`** to stop it
3. You should see the command prompt return

---

## 🎯 Step 2: Get PHP Running (Choose Your Method)

### Option A: Easy Way (XAMPP - Recommended for Beginners)

1. **Download XAMPP**: Go to https://www.apachefriends.org/
2. **Click "Download"** for Windows
3. **Install it** (just keep clicking "Next")
4. **Start XAMPP Control Panel**
5. **Click "Start" next to "Apache"** (this starts the web server)
6. **Copy your files**:
   - Open File Explorer
   - Go to `C:\xampp\htdocs\`
   - Create a new folder called `printlab`
   - Copy ALL your PHP files into this folder:
     - `index.php`
     - `config.php`
     - `database.php`
     - `utils.php`
     - `email.php`
7. **Open your browser** and go to: `http://localhost/printlab/`

### Option B: If You Already Have PHP Installed

1. **Stop Python server** (Ctrl+C)
2. **Type this command**:
   ```
   php -S localhost:8000
   ```
3. **Open your browser** and go to: `http://localhost:8000`

---

## 🎯 Step 3: Test If It's Working

1. **Open your web browser** (Chrome, Firefox, etc.)
2. **Go to the address** (from step 2 above)
3. **You should see**: A blue header saying "3D Print Lab" with a form below

### ✅ If you see the form - SUCCESS! Skip to Step 4
### ❌ If you see an error - Try Option A (XAMPP) above

---

## 🎯 Step 4: How to Actually Use the System

### 🎓 As a Student (Testing the System):

1. **Fill out the form** you see:
   - **Name**: Enter any name (like "Test Student")
   - **Email**: Use a real email (you'll get notifications here)
   - **Discipline**: Pick any from the dropdown
   - **Print Method**: Choose "Filament (FDM)"
   - **Color**: Choose any color
   - **File**: You need a 3D file (.stl, .obj, or .3mf)
     - If you don't have one, download a free .stl file from https://thingiverse.com

2. **Click "Submit Print Request"**
3. **You'll get a Job ID** - write this down!

### 👨‍💼 As Staff (Managing Requests):

1. **Click "Staff Login"** (link at top of page)
2. **Password**: `printlab2024`
3. **You'll see tabs**: Pending, Approved, Confirmed, etc.
4. **Click on "Pending"** - you'll see the job you just submitted
5. **Click "Approve"** button
6. **Enter some numbers**:
   - Weight: `50` (grams)
   - Time: `2` (hours)
7. **Click "Approve Job"**

### 📧 What Happens Next:
- The "student" gets an email with approval and cost
- They click a link to confirm
- Staff can move the job through different statuses
- Student gets notified when it's ready for pickup

---

## 🆘 Common Problems & Solutions

### "Page Not Found" or "Cannot Connect"
- **Check**: Is XAMPP Apache running? (Green "Running" text)
- **Check**: Did you copy files to the right folder?
- **Try**: Restart Apache in XAMPP

### "Database Error"  
- **This is normal first time!** The system creates the database automatically
- **Try**: Refresh the page

### "PHP Not Found" (Command Line)
- **Solution**: Use XAMPP method instead
- **Or**: Install PHP from https://windows.php.net/download/

### Form Doesn't Submit
- **Check**: Are you using the XAMPP address? (`http://localhost/printlab/`)
- **Try**: Clear your browser cache (Ctrl+F5)

---

## 🎉 Success Checklist

- [ ] Can see the blue "3D Print Lab" homepage
- [ ] Can fill out and submit the student form
- [ ] Can login as staff with password `printlab2024`
- [ ] Can see submitted job in "Pending" tab
- [ ] Can approve a job and enter weight/time

**Once you can do all these things, your system is working perfectly!**

---

## 🔧 Next Steps (When Ready)

1. **Change the staff password** in `config.php`
2. **Update email settings** in `config.php` with your real email
3. **Customize lab name and pricing** in `config.php`
4. **Test email functionality** (may need server mail setup)

---

## 💡 What You Built

You now have a **complete 3D print lab management system** that can:
- ✅ Accept student print requests online
- ✅ Let staff review and approve jobs
- ✅ Calculate costs automatically  
- ✅ Send email notifications
- ✅ Track jobs through the complete workflow
- ✅ Work on any computer with PHP

**This replaces paper forms and manual tracking entirely!** 