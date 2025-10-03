# Deployment Guide - Mind Matters

## Quick Environment Switching

### 🏠 Local Development (Current)
Your system is currently configured for local development.

**Current Settings:**
- Database: `localhost/mind_matters_db`
- Site URL: `http://localhost/mind-matters`
- Debug: Enabled for localhost

### 🌐 Production Deployment

When ready to deploy to production, follow these steps:

#### Step 1: Replace Database Configuration Only
```bash
# Replace connect.php with production settings
cp connect_production.php connect.php

# Note: email_config.php no longer needs to be changed!
# It automatically detects localhost vs production
```

#### Step 2: Update JavaScript (if needed)
In `js/selfhelp-mobile.js`, update line 509:
```javascript
// Change this:
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {

// To this:
if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1' || window.location.hostname === 'mindmatters.free.nf') {
```

#### Step 3: Upload to Web Host
1. Upload all files to your hosting directory
2. Import database structure to your hosting MySQL
3. Test all functionality

### 🔄 Switch Back to Local
To revert to local development:

#### Step 1: Restore Local Configuration
```bash
# Database settings
# In connect.php, use:
$servername = "localhost";
$username = "root"; 
$password = "";
$dbname = "mind_matters_db";

# Email settings
# In email_config.php, use:
define('SITE_URL', 'http://localhost/mind-matters');
```

#### Step 2: Revert JavaScript
Remove production domain from debug condition in `js/selfhelp-mobile.js`.

## 📋 Configuration Files

### Local Development Files:
- `connect.php` - Local database settings
- `email_config.php` - Auto-detects environment (no changes needed!)

### Production Files:
- `connect_production.php` - Production database settings

## 🔧 Production Settings

### Database (InfinityFree):
- **Host**: sql110.infinityfree.com
- **Username**: if0_39860558
- **Password**: cwclewmYDD
- **Database**: if0_39860558_mindmatters

### Domain:
- **Production URL**: https://mindmatters.free.nf
- **Local URL**: http://localhost/mind-matters

### Email:
- **SMTP**: Gmail (1official.mindmatters@gmail.com)
- **App Password**: rxza cfjc vzkj wirf

## 🚀 What Gets Updated Automatically

When you change `SITE_URL` in `email_config.php`, these email links automatically update:

- ✅ Email verification links
- ✅ Appointment notifications  
- ✅ Session reminders
- ✅ Message notifications
- ✅ Co-therapist invitations
- ✅ Password reset emails
- ✅ All footer links in emails

## 🛡️ Security Notes

### For Production:
- Remove or secure test files (test_*.php, verify_email_setup.php)
- Set proper file permissions
- Monitor error logs
- Test all email functionality

### For Local Development:
- Keep production credentials secure
- Don't commit production config to git
- Test with real email addresses

## 📝 Quick Commands

### Deploy to Production:
```bash
cp connect_production.php connect.php
# Upload files to hosting
# Email config automatically detects production environment!
```

### Revert to Local:
```bash
# Already done - your system is on localhost!
```

Your Mind Matters system is ready for easy deployment switching! 🎯
