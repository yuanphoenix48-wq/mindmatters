<?php
session_start();
require_once 'connect.php';
require_once 'includes/EmailVerification.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $role = $_POST['role'];
    $gender = $_POST['gender'];

    // Role-specific fields
    $clientId = $role === 'client' ? trim($_POST['client_id']) : null;
    $section = $role === 'client' ? trim($_POST['section']) : null;
    $licenseId = $role === 'therapist' ? trim($_POST['license_id']) : null;
    $contactNumber = $role === 'therapist' ? trim($_POST['contact_number'] ?? '') : null;
    $specialization = $role === 'therapist' ? trim($_POST['specialization'] ?? '') : null;
    $yearsExperience = $role === 'therapist' ? intval($_POST['years_experience'] ?? 0) : null;
    $languagesSpoken = $role === 'therapist' ? trim($_POST['languages_spoken'] ?? '') : null;

    // Validate input
    $errors = [];

    if (empty($firstName)) {
        $errors[] = "First name is required";
    }

    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }

    // Password policy: length 8-72, require upper, lower, digit, special
    function is_strong_password($pwd) {
        if (strlen($pwd) < 8 || strlen($pwd) > 72) return false;
        $hasUpper = preg_match('/[A-Z]/', $pwd);
        $hasLower = preg_match('/[a-z]/', $pwd);
        $hasDigit = preg_match('/[0-9]/', $pwd);
        $hasSpecial = preg_match('/[^A-Za-z0-9]/', $pwd);
        return $hasUpper && $hasLower && $hasDigit && $hasSpecial;
    }

    if (empty($password) || !is_strong_password($password)) {
        $errors[] = "Password must be 8+ chars with uppercase, lowercase, number and special character";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    if (!in_array($role, ['client', 'therapist'])) {
        $errors[] = "Invalid role selected";
    }

    if (!in_array($gender, ['male', 'female'])) {
        $errors[] = "Invalid gender selected";
    }

    // Role-specific validation
    if ($role === 'client') {
        if (empty($clientId)) {
            $errors[] = "Client ID is required";
        }
        if (empty($section)) {
            $errors[] = "Section is required";
        }
    } else if ($role === 'therapist') {
        if (empty($licenseId)) { $errors[] = "License ID is required"; }
        if (empty($contactNumber)) { $errors[] = "Contact number is required"; }
        if (empty($specialization)) { $errors[] = "Field of specialization is required"; }
        if ($yearsExperience === null || $yearsExperience < 0) { $errors[] = "Years of experience must be 0 or more"; }
        if (empty($languagesSpoken)) { $errors[] = "Languages spoken is required"; }
    }

    // Check if email already exists
    $sql = "SELECT id FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email is already registered";
    }
    $stmt->close();

    // Check if client ID or license ID already exists
    if ($role === 'client') {
        $sql = "SELECT id FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $clientId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Client ID is already registered";
        }
        $stmt->close();
    } else if ($role === 'therapist') {
        $sql = "SELECT id FROM users WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $licenseId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "License ID is already registered";
        }
        $stmt->close();
    }

    // If no errors, proceed with user creation
    if (empty($errors)) {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Set default profile picture based on gender
        $profilePicture = $gender === 'female' 
            ? 'images/profile/default_images/female_gender.png'
            : 'images/profile/default_images/male_gender.png';

        // Set user_id based on role
        $userId = $role === 'client' ? $clientId : $licenseId;

        // Prepare email verification for clients only (therapists/admins are auto-verified)
        $verificationToken = null;
        $verificationExpires = null;
        $emailVerification = null;
        if ($role === 'client') {
            $emailVerification = new EmailVerification();
            if ($emailVerification->isConfigurationValid()) {
                $verificationToken = $emailVerification->generateVerificationToken();
                $verificationExpires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            } else {
                error_log("Email verification system not configured - client will be created as verified to avoid lockout");
            }
        }

        // Insert new user (with therapist fields + verification columns on users table)
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, user_id, gender, profile_picture, section, contact_number, license_number, specialization, years_of_experience, languages_spoken, verification_token, verification_expires, email_verified) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $section = $role === 'client' ? $section : null;
        $contactForInsert = $role === 'therapist' ? $contactNumber : null;
        $licenseForInsert = $role === 'therapist' ? $licenseId : null;
        $specForInsert = $role === 'therapist' ? $specialization : null;
        $yearsForInsert = $role === 'therapist' ? $yearsExperience : null;
        $langsForInsert = $role === 'therapist' ? $languagesSpoken : null;
        $emailVerifiedInitial = ($role === 'client' && $verificationToken) ? 0 : 1; // only clients need verification
        $stmt->bind_param(
            "ssssssssssssisssi",
            $firstName,
            $lastName,
            $email,
            $hashedPassword,
            $role,
            $userId,
            $gender,
            $profilePicture,
            $section,
            $contactForInsert,
            $licenseForInsert,
            $specialization,
            $yearsForInsert,
            $langsForInsert,
            $verificationToken,
            $verificationExpires,
            $emailVerifiedInitial
        );

        if ($stmt->execute()) {
            // Success path
            $stmt->close();

            // Send verification email for clients only
            if ($role === 'client' && $verificationToken && isset($emailVerification) && $emailVerification->isConfigurationValid()) {
                $emailSent = $emailVerification->sendVerificationEmail($email, $firstName, $verificationToken);
                if (!$emailSent) {
                    error_log("Failed to send verification email to client: " . $email);
                    $_SESSION['email_warning'] = "User created successfully, but verification email failed to send to: " . $email;
                } else {
                    $_SESSION['email_success'] = "User created and verification email sent to: " . $email;
                }
            }

            header('Location: admin_users.php?success=1');
            exit();
        } else {
            $errors[] = "Failed to create user: " . $stmt->error;
        }
        if (isset($stmt) && $stmt) { $stmt->close(); }
    }

    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        // Preserve submitted values so the form can be repopulated (including password fields)
        $_SESSION['add_user_old'] = $_POST;
        $errorString = implode(',', $errors);
        header('Location: admin_users.php?error=' . urlencode($errorString));
        exit();
    }
} else {
    // If not a POST request, redirect to user management
    header('Location: admin_users.php');
    exit();
}

$conn->close();
?> 