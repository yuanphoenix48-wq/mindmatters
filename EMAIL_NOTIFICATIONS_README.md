# Email Notification System - Mind Matters

This document describes the comprehensive email notification system implemented for the Mind Matters platform.

## Overview

The email notification system provides automated email notifications for various events in the therapy platform:

- **Appointment Pending**: Notifies therapists when clients request appointments
- **Appointment Accepted**: Notifies clients when therapists accept their appointment requests
- **Therapist Session Acceptance**: Notifies clients when therapists accept their pending session requests
- **Session Reminders**: 24-hour and 10-minute reminders before sessions
- **Feedback Requests**: Automated requests for session feedback after completion
- **Message Notifications**: Notifies users when they receive new messages
- **Enhanced Email Verification**: Improved styling for account verification emails

## Features

### 🎨 Professional Email Templates
- Responsive HTML design that works on all devices
- Consistent branding with Mind Matters colors and styling
- Both HTML and plain text versions for maximum compatibility
- Modern gradient headers and clean typography

### 📧 Notification Types

#### 1. Appointment Pending Notification
- **Triggered**: When a client books a session
- **Recipients**: Assigned therapist
- **Content**: Client name, session date/time, direct link to appointments dashboard

#### 2. Appointment Accepted Notification
- **Triggered**: When a therapist accepts a pending appointment
- **Recipients**: Client who requested the appointment
- **Content**: Confirmation details, meeting link (if available), session details

#### 3. Therapist Session Acceptance Notification
- **Triggered**: When a therapist accepts a pending session request
- **Recipients**: Client who requested the session
- **Content**: Session confirmation, meeting link, therapist details

#### 4. Message Notifications
- **Triggered**: When a user sends a message to another user
- **Recipients**: Message receiver
- **Content**: Message preview, sender name, conversation link

#### 5. Session Reminders
- **24-Hour Reminder**: Sent 24 hours before session
- **10-Minute Reminder**: Sent 10 minutes before session
- **Recipients**: Both client and therapist
- **Content**: Session details, meeting link, preparation tips

#### 6. Feedback Request Notification
- **Triggered**: When a session is marked as completed
- **Recipients**: Client who attended the session
- **Content**: Session details, direct link to feedback form

#### 7. Enhanced Email Verification
- **Triggered**: During user registration
- **Recipients**: New users
- **Content**: Welcome message, verification link, next steps

## Installation

### 1. Database Setup

Run the SQL script to add reminder tracking columns:

```sql
-- Run this in phpMyAdmin or MySQL command line
mysql -u username -p database_name < add_reminder_columns.sql
```

### 2. Email Configuration

The system uses the existing `email_config.php` configuration. Ensure your SMTP settings are correct:

```php
// Gmail SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
define('SMTP_SECURE', 'tls');
```

### 3. Cron Job Setup

Set up automated reminders using cron jobs:

```bash
# Run the setup script
php setup_cron_jobs.php

# Add the suggested cron jobs to your crontab
crontab -e
```

**Cron Job Schedule:**
- **24-hour reminders**: Every hour at minute 0
- **10-minute reminders**: Every 5 minutes
- **Feedback requests**: Every hour at minute 0

## File Structure

```
includes/
├── EmailNotifications.php      # Main notification class
├── EmailVerification.php       # Enhanced verification emails
└── ...

send_session_reminders.php      # Cron job script
test_email_notifications.php    # Test script
setup_cron_jobs.php             # Cron setup helper
add_reminder_columns.sql        # Database migration
```

## Usage

### Manual Testing

Test all notification types:

```bash
php test_email_notifications.php
```

### Manual Reminder Sending

```bash
# Send 24-hour reminders
php send_session_reminders.php 24h

# Send 10-minute reminders
php send_session_reminders.php 10min

# Send feedback requests
php send_session_reminders.php feedback
```

### Programmatic Usage

```php
require_once 'includes/EmailNotifications.php';

$emailNotifications = new EmailNotifications();

// Send appointment pending notification
$emailNotifications->sendAppointmentPendingNotification(
    $therapistEmail,
    $therapistName,
    $clientName,
    $sessionDate,
    $sessionTime,
    $sessionId
);

// Send appointment accepted notification
$emailNotifications->sendAppointmentAcceptedNotification(
    $clientEmail,
    $clientName,
    $therapistName,
    $sessionDate,
    $sessionTime,
    $meetLink
);

// Send session reminder
$emailNotifications->sendSessionReminderNotification(
    $email,
    $name,
    $sessionDate,
    $sessionTime,
    $therapistName,
    '24h', // or '10min'
    $meetLink
);

// Send feedback request
$emailNotifications->sendFeedbackRequestNotification(
    $email,
    $name,
    $sessionDate,
    $sessionTime,
    $therapistName,
    $feedbackLink
);

// Send therapist session acceptance notification
$emailNotifications->sendTherapistAcceptanceNotification(
    $clientEmail,
    $clientName,
    $therapistName,
    $sessionDate,
    $sessionTime,
    $meetLink
);

// Send message notification
$emailNotifications->sendMessageNotification(
    $receiverEmail,
    $receiverName,
    $senderName,
    $messagePreview,
    $conversationLink
);
```

## Integration Points

### 1. Appointment Booking (`book_session.php`)
- Sends pending notification to therapist when client books session

### 2. Appointment Status Updates (`update_appointment_status.php`)
- Sends acceptance notification to client when therapist accepts
- Sends feedback request when session is marked as completed

### 3. Session Acceptance (`accept_session.php`)
- Sends therapist acceptance notification to client when therapist accepts pending session

### 4. Message System (`send_message.php`)
- Sends message notification to receiver when message is sent

### 5. Session Reminders (`send_session_reminders.php`)
- Automated cron job for 24-hour and 10-minute reminders
- Automated feedback request sending

## Email Templates

All email templates feature:

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Professional Styling**: Clean, modern design with Mind Matters branding
- **Clear Call-to-Actions**: Prominent buttons for important actions
- **Accessibility**: High contrast, readable fonts, proper heading structure
- **Fallback Support**: Plain text versions for email clients that don't support HTML

### Template Features

- Gradient headers with Mind Matters branding
- Card-based content layout
- Color-coded urgency levels
- Professional footer with links
- Mobile-responsive design
- Cross-client compatibility

## Monitoring and Logging

### Error Logging
All email sending errors are logged to the PHP error log:

```php
error_log("Email sending error: " . $e->getMessage());
```

### Success Tracking
Database columns track notification status:
- `reminder_24h_sent`: 24-hour reminder sent
- `reminder_10min_sent`: 10-minute reminder sent
- `feedback_requested`: Feedback request sent

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check SMTP credentials in `email_config.php`
   - Verify firewall allows SMTP connections
   - Check PHP error logs

2. **Cron jobs not running**
   - Verify cron service is running
   - Check cron job syntax
   - Ensure PHP path is correct

3. **Template rendering issues**
   - Check for PHP syntax errors
   - Verify all required variables are passed
   - Test with different email clients

### Debug Mode

Enable detailed error logging by adding to your PHP configuration:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

## Security Considerations

- All email content is properly escaped to prevent XSS
- SMTP credentials are stored securely
- Email addresses are validated before sending
- Rate limiting prevents spam (via cron job frequency)

## Performance

- Database queries are optimized with proper indexes
- Email sending is asynchronous via cron jobs
- Templates are cached in memory during execution
- Minimal database load with efficient queries

## Future Enhancements

Potential improvements for future versions:

1. **Email Preferences**: Allow users to customize notification preferences
2. **SMS Notifications**: Add SMS backup for critical notifications
3. **Email Analytics**: Track open rates and click-through rates
4. **Template Customization**: Allow admins to customize email templates
5. **Queue System**: Implement email queue for high-volume sending
6. **Multi-language Support**: Support for multiple languages

## Support

For issues or questions:

1. Check the error logs first
2. Run the test script to verify functionality
3. Verify all configuration settings
4. Check cron job status
5. Test with a simple email first

---

**Note**: This system is designed for the Mind Matters platform. Ensure all email content complies with your organization's policies and applicable laws (CAN-SPAM, GDPR, etc.).
