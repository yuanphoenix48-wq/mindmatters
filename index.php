<?php 
session_start();

// Check for persistent token for auto-login
if (!isset($_SESSION['user_id'])) {
    $token = $_COOKIE['persistent_token'] ?? '';
    if (!empty($token)) {
        include 'connect.php';
        require_once 'includes/TokenManager.php';
        
        $tokenManager = new TokenManager($conn);
        $userData = $tokenManager->validateToken($token);
        
        if ($userData) {
            // Auto-login successful
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['user_email'] = $userData['email'];
            $_SESSION['first_name'] = $userData['first_name'];
            $_SESSION['last_name'] = $userData['last_name'];
            $_SESSION['role'] = $userData['role'];
            $_SESSION['timeout'] = time() + (30 * 60);
            $_SESSION['last_activity'] = time() * 1000;
            
            // Redirect based on role
            $redirectUrl = $userData['role'] === 'admin' ? 'admin_dashboard.php' : 'dashboard.php';
            header("Location: $redirectUrl");
            exit();
        } else {
            // Invalid token, clear cookie
            setcookie('persistent_token', '', time() - 3600, '/');
        }
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Mind Matters</title>

  <!-- External Stylesheets -->
  <link rel="stylesheet" href="styles/global.css" />
  <link rel="stylesheet" href="styles/index.css" />
  <link rel="stylesheet" href="styles/notifications.css" />
  <link rel="stylesheet" href="styles/mobile.css" />

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Lora:wght@700&family=Source+Sans+Pro&display=swap" rel="stylesheet" />
</head>
<body class="indexBody">

  <!-- Header / Navbar -->
  <header class="container">
    <h1 class="indexTitle">Mind Matters</h1>
    <nav class="nav-links">
      <ul>
        <li><a href="#" class="signupbtn" role="button">Sign Up</a></li>
        <li><a href="#" class="loginbtn" role="button">Login</a></li>
      </ul>
    </nav>
  </header>

  <!-- Login Modal -->
  <div id="loginModal" class="modal" aria-hidden="true" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Login to Mind Matters</h2>
        <span class="close" aria-label="Close login form">&times;</span>
      </div>
      <div class="modal-body">
        <form id="loginForm" action="loginapi.php" method="POST">
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required />
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="password-container">
              <input type="password" id="password" name="password" required />
              <button type="button" id="toggleLoginPassword" aria-label="Show password">Show</button>
            </div>
          </div>

          <button type="submit">Login</button>
          <div id="login-status"></div>
          <div class="login-links">
            <p>
              <a href="resend_verification.php">
                Need to verify your email?
              </a>
            </p>
            <p>
              <a href="#" id="forgotPasswordLink" class="forgot-password-link">
                Forgot your password?
              </a>
            </p>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Sign Up Modal -->
  <div id="signupModal" class="modal" aria-hidden="true" style="display: none;">
    <div class="modal-content signup-modal">
      <div class="modal-header">
        <h2>Client Sign Up</h2>
        <span class="close" aria-label="Close sign-up form">&times;</span>
      </div>
      <div class="modal-body">
        <?php if (isset($_GET['signup_error'])): ?>
          <div class="alert alert-error">
            <?php echo htmlspecialchars($_GET['signup_error']); ?>
          </div>
        <?php endif; ?>
        <!-- Signup Form with POST Method -->
        <form id="signupForm" action="signupapi.php" method="POST">
          <div class="form-row">
            <div class="form-group">
              <label for="client-id">Client ID</label>
              <input type="text" id="client-id" name="client-id" pattern="[0-9]+" title="Please enter a valid client ID (numbers only)" value="<?php echo isset($_SESSION['signup_old']['client-id']) ? htmlspecialchars($_SESSION['signup_old']['client-id']) : ''; ?>" required />
            </div>
            
            <div class="form-group">
              <label for="first-name">First Name</label>
              <input type="text" id="first-name" name="first-name" value="<?php echo isset($_SESSION['signup_old']['first-name']) ? htmlspecialchars($_SESSION['signup_old']['first-name']) : ''; ?>" required />
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="last-name">Last Name</label>
              <input type="text" id="last-name" name="last-name" value="<?php echo isset($_SESSION['signup_old']['last-name']) ? htmlspecialchars($_SESSION['signup_old']['last-name']) : ''; ?>" required />
            </div>
            
            <div class="form-group">
              <label for="section">Section</label>
              <input type="text" id="section" name="section" value="<?php echo isset($_SESSION['signup_old']['section']) ? htmlspecialchars($_SESSION['signup_old']['section']) : ''; ?>" required />
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="gender">Gender</label>
              <select id="gender" name="gender" required>
                <option value="male" <?php echo (isset($_SESSION['signup_old']['gender']) && $_SESSION['signup_old']['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                <option value="female" <?php echo (isset($_SESSION['signup_old']['gender']) && $_SESSION['signup_old']['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
              </select>
            </div>
            
            <div class="form-group">
              <label for="signup-email">Email <small>(use your @student.fatima.edu.ph)</small></label>
              <input type="email" id="signup-email" name="signup-email" pattern="^[A-Za-z0-9._%+-]+@student\.fatima\.edu\.ph$" title="Please use your school email (e.g., name@student.fatima.edu.ph)" value="<?php echo isset($_SESSION['signup_old']['signup-email']) ? htmlspecialchars($_SESSION['signup_old']['signup-email']) : ''; ?>" required />
            </div>
          </div>
          
          <div class="form-group">
            <label for="signup-password">Password</label>
            <div class="password-container">
              <input type="password" id="signup-password" name="signup-password" value="<?php echo isset($_SESSION['signup_old']['signup-password']) ? htmlspecialchars($_SESSION['signup_old']['signup-password']) : ''; ?>" required />
              <button type="button" id="toggleSignupPassword" aria-label="Show password">Show</button>
            </div>
            <div class="form-help">
              Password must be 8+ characters and include uppercase, lowercase, number, and special character.
            </div>
            <div class="password-strength" id="passwordStrength">
              <div class="strength-bar">
                <div class="strength-fill" id="strengthFill"></div>
              </div>
              <div class="strength-text" id="strengthText">Enter password to see strength</div>
            </div>
          </div>
        
          <div class="form-group">
            <label for="confirm-password">Confirm Password</label>
            <div class="password-container">
              <input type="password" id="confirm-password" name="confirm-password" value="<?php echo isset($_SESSION['signup_old']['confirm-password']) ? htmlspecialchars($_SESSION['signup_old']['confirm-password']) : ''; ?>" required />
              <button type="button" id="toggleConfirmPassword" aria-label="Show password">Show</button>
            </div>
          </div>
        
          <button type="submit">Sign Up</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div id="forgotPasswordModal" class="modal" aria-hidden="true" style="display: none;">
    <div class="modal-content">
      <div class="modal-header">
        <h2>Reset Password</h2>
        <span class="close" aria-label="Close forgot password form">&times;</span>
      </div>
      <div class="modal-body">
        <!-- Step 1: Email Input -->
        <div id="emailStep" class="reset-step active">
          <p class="step-description">Enter your email address and we'll send you a verification code to reset your password.</p>
          <form id="forgotPasswordEmailForm">
            <div class="form-group">
              <label for="resetEmail">Email Address</label>
              <input type="email" id="resetEmail" name="resetEmail" required />
            </div>
            <button type="submit" id="sendCodeBtn">Send Verification Code</button>
            <div id="email-status"></div>
          </form>
        </div>

        <!-- Step 2: Code Verification -->
        <div id="codeStep" class="reset-step">
          <p class="step-description">Enter the 6-digit verification code sent to your email address.</p>
          <form id="forgotPasswordCodeForm">
            <div class="form-group">
              <label for="verificationCode">Verification Code</label>
              <input type="text" id="verificationCode" name="verificationCode" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required />
            </div>
            <div class="code-actions">
              <button type="submit" id="verifyCodeBtn">Verify Code</button>
              <button type="button" id="resendCodeBtn" class="resend-btn" disabled>
                Resend Code (<span id="resendTimer">60</span>s)
              </button>
            </div>
            <div id="code-status"></div>
          </form>
        </div>

        <!-- Step 3: New Password -->
        <div id="passwordStep" class="reset-step">
          <p class="step-description">Create a new strong password for your account.</p>
          <form id="forgotPasswordResetForm">
            <div class="form-group">
              <label for="newPassword">New Password</label>
              <div class="password-container">
                <input type="password" id="newPassword" name="newPassword" required />
                <button type="button" id="toggleNewPassword" aria-label="Show password">Show</button>
              </div>
              <div class="password-strength">
                <div class="strength-bar">
                  <div class="strength-fill" id="newPasswordStrengthFill"></div>
                </div>
                <div class="strength-text" id="newPasswordStrengthText">Enter password to see strength</div>
              </div>
            </div>
            
            <div class="form-group">
              <label for="confirmNewPassword">Confirm New Password</label>
              <div class="password-container">
                <input type="password" id="confirmNewPassword" name="confirmNewPassword" required />
                <button type="button" id="toggleConfirmNewPassword" aria-label="Show password">Show</button>
              </div>
            </div>
            
            <button type="submit" id="resetPasswordBtn">Reset Password</button>
            <div id="password-status"></div>
          </form>
        </div>

        <!-- Navigation -->
        <div class="reset-navigation">
          <button type="button" id="backToEmailBtn" class="back-btn" style="display: none;">← Back to Email</button>
          <button type="button" id="backToCodeBtn" class="back-btn" style="display: none;">← Back to Code</button>
        </div>
      </div>
    </div>
  </div>

  <div class="imageContainer1">
    <img src="images/image1.png" alt="graphics" class="image1">
    <div class="paragraphContainer">
      <p class="firstParagraph">Mind Matters is your go-to platform 
        for mental well-being. Whether you're looking for daily 
        inspiration, helpful tips, or a space to track your emotional 
        health, we offer a variety of tools to help you on your journey. 
        Our simple, easy-to-use interface makes it effortless to keep 
        a journal, set reminders, and access mindfulness resources. 
        We also provide teleconsultation services with licensed psychiatrists, 
        offering you the support you need in managing mental health concerns 
        from the comfort of your home. Join a supportive community focused 
        on personal growth, mental clarity, and positivity. At Mind Matters, 
        we believe that mental health is just as important as physical health. 
        Start your journey today and take a step towards a healthier, 
        more balanced life.
      </p>
    </div>
  </div>

  <div class="imageContainer1">
    <div class="paragraphContainer">
      <p class="firstParagraph">Telecounseling with a psychiatrist 
        provides a convenient and accessible way to receive professional 
        mental health support from the comfort of your home. 
        It allows individuals to connect with licensed psychiatrists 
        through secure video calls, phone consultations, or even chat sessions. 
        This service is ideal for those who face barriers to in-person 
        appointments, such as geographical location, time constraints, or mobility issues. 
        Telecounseling offers the same quality care as traditional in-office visits, 
        with the added benefit of privacy and flexibility. 
        Whether managing mental health conditions, seeking advice, or exploring treatment 
        options, telecounseling ensures that help is always within reach.
      </p>
    </div>
    <img src="images/image2.png" alt="graphics" class="image1">
  </div>

  <div class="imageContainer1">
    <img src="images/image3.png" alt="graphics" class="image1">
    <div class="paragraphContainer">
      <p class="firstParagraph">Checking on your health through an app guided by a psychiatrist 
        offers a modern and supportive approach to mental wellness. 
        With just a few taps, individuals can monitor their mental and 
        emotional well-being while receiving professional guidance 
        tailored to their needs. The app allows users to track their mood, 
        log symptoms, and follow personalized care plans created or overseen 
        by licensed psychiatrists. This convenient setup is perfect for those 
        who prefer continuous support outside of traditional appointments. 
        Combining technology with expert care, this service ensures that 
        mental health check-ins are consistent, discreet, and always 
        accessibleâ€”empowering users to take charge of their well-being 
        anytime, anywhere.
      </p>
    </div>
  </div>

  <footer class="site-footer">
    <div class="footer-content">
      <ul class="footer-links">
        <li><a href="about.php">About</a></li>
        <li><a href="contact.php">Contact Us</a></li>
        <li><a href="privacy.php">Privacy Policy</a></li>
      </ul>
      <hr />
      <p class="copyright">
        &copy; 2025 Mind Matters. All Rights Reserved.
      </p>
    </div>
  </footer>
  
  <!-- External JS -->
  <script src="https://cdn.jsdelivr.net/npm/jwt-decode@3.1.2/build/jwt-decode.min.js"></script>
  <script src="js/notifications.js" defer></script>
  <script src="js/mobile.js" defer></script>
  <script src="js/session_manager.js" defer></script>
  <script type="module" src="script.js" defer></script>
  <script>
    // Check for session expired message
    document.addEventListener('DOMContentLoaded', function() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('session_expired') === '1') {
        if (typeof showToast === 'function') {
          showToast('Your session has expired due to inactivity. Please log in again.', 'warning');
        } else {
          alert('Your session has expired due to inactivity. Please log in again.');
        }
        // Clean up URL
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    });

    // Modal management with proper cleanup
    document.addEventListener('DOMContentLoaded', function() {
      console.log('DOM loaded, initializing modals...');
      
      // Get elements
      const loginModal = document.getElementById('loginModal');
      const signupModal = document.getElementById('signupModal');
      const loginBtn = document.querySelector('.loginbtn');
      const signupBtn = document.querySelector('.signupbtn');
      
      console.log('Elements found:', {
        loginModal: !!loginModal,
        signupModal: !!signupModal,
        loginBtn: !!loginBtn,
        signupBtn: !!signupBtn
      });
      
      // Modal functions
      function showModal(modal) {
        if (modal) {
          console.log('Showing modal:', modal.id);
          modal.style.display = 'flex';
          modal.classList.add('show');
          modal.setAttribute('aria-hidden', 'false');
          document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
      }
      
      function hideModal(modal) {
        if (modal) {
          console.log('Hiding modal:', modal.id);
          modal.style.display = 'none';
          modal.classList.remove('show');
          modal.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = ''; // Restore scrolling
        }
      }
      
      // Remove any existing event listeners first
      if (loginBtn) {
        loginBtn.replaceWith(loginBtn.cloneNode(true));
      }
      if (signupBtn) {
        signupBtn.replaceWith(signupBtn.cloneNode(true));
      }
      
      // Get fresh references after cloning
      const freshLoginBtn = document.querySelector('.loginbtn');
      const freshSignupBtn = document.querySelector('.signupbtn');
      
      // Add click handlers
      if (freshLoginBtn && loginModal) {
        freshLoginBtn.addEventListener('click', function(e) {
          e.preventDefault();
          console.log('Login clicked, showing modal...');
          hideModal(signupModal); // Hide other modal first
          showModal(loginModal);
        });
      }
      
      if (freshSignupBtn && signupModal) {
        freshSignupBtn.addEventListener('click', function(e) {
          e.preventDefault();
          console.log('Signup clicked, showing modal...');
          hideModal(loginModal); // Hide other modal first
          showModal(signupModal);
        });
      }
      
      // Close handlers
      const closeButtons = document.querySelectorAll('.modal .close');
      closeButtons.forEach(btn => {
        btn.addEventListener('click', function() {
          const modal = this.closest('.modal');
          if (modal) {
            hideModal(modal);
          }
        });
      });
      
      // Click outside to close
      [loginModal, signupModal].forEach(modal => {
        if (modal) {
          modal.addEventListener('click', function(e) {
            if (e.target === modal) {
              hideModal(modal);
            }
          });
        }
      });
      
      // Clear signup error data when modal is closed
      if (signupModal) {
        const closeBtn = signupModal.querySelector('.close');
        if (closeBtn) {
          closeBtn.addEventListener('click', function() {
            // Clear the signup error from URL and session data
            if (window.location.search.includes('signup_error=')) {
              const url = new URL(window.location);
              url.searchParams.delete('signup_error');
              window.history.replaceState({}, '', url);
            }
            // Clear session data via AJAX
            fetch('clear_signup_session.php', { method: 'POST' }).catch(() => {});
          });
        }

        // Auto-open signup modal if there is an error or preserved data
        const hasSignupError = new URLSearchParams(window.location.search).has('signup_error');
        const hasPreservedData = <?php echo isset($_SESSION['signup_old']) ? 'true' : 'false'; ?>;
        if (hasSignupError || hasPreservedData) {
          showModal(signupModal);
        }
      }
      
      // Initialize password strength meter
      console.log('Initializing password strength meter...');
      initializePasswordStrengthInline();
    });
    
    // Password strength function (inline to avoid timing issues)
    function initializePasswordStrengthInline() {
      const passwordInput = document.getElementById('signup-password');
      const confirmPasswordInput = document.getElementById('confirm-password');
      const strengthIndicator = document.getElementById('passwordStrength');
      const strengthFill = document.getElementById('strengthFill');
      const strengthText = document.getElementById('strengthText');
      
      console.log('Password strength elements:', {
        passwordInput: !!passwordInput,
        confirmPasswordInput: !!confirmPasswordInput,
        strengthIndicator: !!strengthIndicator,
        strengthFill: !!strengthFill,
        strengthText: !!strengthText
      });
      
      if (!passwordInput || !strengthIndicator || !strengthFill || !strengthText) {
        console.log('Missing password strength elements');
        return;
      }
      
      // Password strength checker
      function checkPasswordStrength(password) {
        let score = 0;
        let feedback = [];
        
        if (password.length >= 8) score += 1;
        else feedback.push('At least 8 characters');
        
        if (/[A-Z]/.test(password)) score += 1;
        else feedback.push('Uppercase letter');
        
        if (/[a-z]/.test(password)) score += 1;
        else feedback.push('Lowercase letter');
        
        if (/[0-9]/.test(password)) score += 1;
        else feedback.push('Number');
        
        if (/[^A-Za-z0-9]/.test(password)) score += 1;
        else feedback.push('Special character');
        
        return { score, feedback };
      }
      
      // Update password strength indicator
      function updateStrengthIndicator(password) {
        console.log('Updating strength indicator for password:', password);
        
        if (password.length === 0) {
          strengthIndicator.classList.add('hidden');
          return;
        }
        
        strengthIndicator.classList.remove('hidden');
        const { score, feedback } = checkPasswordStrength(password);
        console.log('Password strength:', { score, feedback });
        
        strengthFill.className = 'strength-fill';
        if (score <= 1) {
          strengthFill.classList.add('weak');
          strengthText.textContent = 'Weak - ' + feedback.join(', ');
          strengthText.style.color = '#dc3545';
        } else if (score <= 2) {
          strengthFill.classList.add('fair');
          strengthText.textContent = 'Fair - ' + feedback.join(', ');
          strengthText.style.color = '#ffc107';
        } else if (score <= 3) {
          strengthFill.classList.add('good');
          strengthText.textContent = 'Good - ' + feedback.join(', ');
          strengthText.style.color = '#17a2b8';
        } else {
          strengthFill.classList.add('strong');
          strengthText.textContent = 'Strong password!';
          strengthText.style.color = '#28a745';
        }
      }
      
      // Real-time password strength checking
      passwordInput.addEventListener('input', function() {
        console.log('Password input event triggered');
        updateStrengthIndicator(this.value);
      });
      
      // Also add keyup event for better compatibility
      passwordInput.addEventListener('keyup', function() {
        console.log('Password keyup event triggered');
        updateStrengthIndicator(this.value);
      });
      
      // Real-time password matching
      if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
          if (this.value && this.value !== passwordInput.value) {
            this.setCustomValidity('Passwords do not match');
            this.style.borderColor = '#dc3545';
          } else {
            this.setCustomValidity('');
            this.style.borderColor = 'rgba(29, 93, 155, 0.1)';
          }
        });
      }
      
      console.log('Password strength meter initialized successfully');
    }
  </script>
  <script>
    // Password toggles are handled centrally in script.js to avoid duplicate bindings
    
    // Handle login form submission
    document.addEventListener('DOMContentLoaded', function() {
      const loginForm = document.getElementById('loginForm');
      const loginStatus = document.getElementById('login-status');
      
      if (loginForm && loginStatus) {
        loginForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const email = document.getElementById('email').value;
          const password = document.getElementById('password').value;
          const submitBtn = loginForm.querySelector('button[type="submit"]');
          
          // Clear previous status
          loginStatus.innerHTML = '';
          loginStatus.className = '';
          
          // Basic validation
          if (!email || !password) {
            showLoginStatus('Please fill in all fields', 'error');
            return;
          }
          
          // Show loading state
          const originalText = submitBtn.textContent;
          submitBtn.textContent = 'Logging in...';
          submitBtn.disabled = true;
          
          // Submit login request
          fetch('loginapi.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showLoginStatus('Login successful! Redirecting...', 'success');
              
              // Store persistent token in cookie for auto-login
              if (data.persistent_token) {
                const expiryDate = new Date();
                expiryDate.setTime(expiryDate.getTime() + (30 * 24 * 60 * 60 * 1000)); // 30 days
                document.cookie = `persistent_token=${data.persistent_token}; expires=${expiryDate.toUTCString()}; path=/; secure; samesite=strict`;
              }
              
              // Redirect based on role
              setTimeout(() => {
                if (data.role === 'admin') {
                  window.location.href = 'admin_dashboard.php';
                } else {
                  window.location.href = 'dashboard.php';
                }
              }, 1000);
            } else {
              showLoginStatus(data.error || 'Login failed', 'error');
            }
          })
          .catch(error => {
            console.error('Login error:', error);
            showLoginStatus('An error occurred. Please try again.', 'error');
          })
          .finally(() => {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
          });
        });
      }
      
      function showLoginStatus(message, type) {
        loginStatus.innerHTML = message;
        loginStatus.className = type;
        loginStatus.style.display = 'block';
        
        // Scroll to status message
        loginStatus.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // Forgot Password Functionality
    document.addEventListener('DOMContentLoaded', function() {
      const forgotPasswordLink = document.getElementById('forgotPasswordLink');
      const forgotPasswordModal = document.getElementById('forgotPasswordModal');
      const loginModal = document.getElementById('loginModal');
      
      // Modal management - match existing system
      const showModal = (modal) => {
        if (modal) {
          console.log('Showing modal:', modal.id);
          modal.style.display = 'flex';
          modal.classList.add('show');
          modal.setAttribute('aria-hidden', 'false');
          document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
      };
      
      const hideModal = (modal) => {
        if (modal) {
          console.log('Hiding modal:', modal.id);
          modal.style.display = 'none';
          modal.classList.remove('show');
          modal.setAttribute('aria-hidden', 'true');
          document.body.style.overflow = ''; // Restore scrolling
        }
      };
      
      // Show forgot password modal
      if (forgotPasswordLink && forgotPasswordModal && loginModal) {
        console.log('Forgot password elements found, adding event listener');
        forgotPasswordLink.addEventListener('click', function(e) {
          e.preventDefault();
          console.log('Forgot password link clicked');
          hideModal(loginModal);
          showModal(forgotPasswordModal);
          resetForgotPasswordModal();
        });
      } else {
        console.error('Forgot password elements missing:', {
          link: !!forgotPasswordLink,
          modal: !!forgotPasswordModal,
          loginModal: !!loginModal
        });
      }
      
      // Close modal functionality
      if (forgotPasswordModal) {
        forgotPasswordModal.addEventListener('click', function(e) {
          if (e.target === forgotPasswordModal || e.target.classList.contains('close')) {
            hideModal(forgotPasswordModal);
            showModal(loginModal);
          }
        });
      }
      
      // Reset modal to initial state
      function resetForgotPasswordModal() {
        showStep('emailStep');
        clearAllForms();
        clearAllStatuses();
      }
      
      function showStep(stepId) {
        const steps = ['emailStep', 'codeStep', 'passwordStep'];
        steps.forEach(step => {
          const element = document.getElementById(step);
          if (element) {
            element.classList.toggle('active', step === stepId);
          }
        });
        
        // Show/hide navigation buttons
        const backToEmailBtn = document.getElementById('backToEmailBtn');
        const backToCodeBtn = document.getElementById('backToCodeBtn');
        
        if (backToEmailBtn) backToEmailBtn.style.display = stepId === 'codeStep' ? 'inline-block' : 'none';
        if (backToCodeBtn) backToCodeBtn.style.display = stepId === 'passwordStep' ? 'inline-block' : 'none';
      }
      
      function clearAllForms() {
        document.getElementById('resetEmail').value = '';
        document.getElementById('verificationCode').value = '';
        document.getElementById('newPassword').value = '';
        document.getElementById('confirmNewPassword').value = '';
      }
      
      function clearAllStatuses() {
        const statuses = ['email-status', 'code-status', 'password-status'];
        statuses.forEach(id => {
          const element = document.getElementById(id);
          if (element) {
            element.innerHTML = '';
            element.className = '';
          }
        });
      }
      
      function showStatus(elementId, message, type) {
        const element = document.getElementById(elementId);
        if (element) {
          element.innerHTML = message;
          element.className = type === 'error' ? 'status-error' : 'status-success';
        }
      }
      
      // Step 1: Send verification code
      const emailForm = document.getElementById('forgotPasswordEmailForm');
      const sendCodeBtn = document.getElementById('sendCodeBtn');
      let currentEmail = '';
      
      if (emailForm) {
        emailForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const email = document.getElementById('resetEmail').value.trim();
          if (!email) {
            showStatus('email-status', 'Please enter your email address', 'error');
            return;
          }
          
          currentEmail = email;
          const originalText = sendCodeBtn.textContent;
          sendCodeBtn.textContent = 'Sending...';
          sendCodeBtn.disabled = true;
          
          fetch('forgot_password_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=send_code&email=${encodeURIComponent(email)}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showStatus('email-status', data.message, 'success');
              setTimeout(() => {
                showStep('codeStep');
                startResendTimer();
              }, 1500);
            } else {
              showStatus('email-status', data.message, 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showStatus('email-status', 'An error occurred. Please try again.', 'error');
          })
          .finally(() => {
            sendCodeBtn.textContent = originalText;
            sendCodeBtn.disabled = false;
          });
        });
      }
      
      // Step 2: Verify code
      const codeForm = document.getElementById('forgotPasswordCodeForm');
      const verifyCodeBtn = document.getElementById('verifyCodeBtn');
      const resendCodeBtn = document.getElementById('resendCodeBtn');
      const resendTimer = document.getElementById('resendTimer');
      let resendCountdown = 60;
      let resendInterval;
      
      function startResendTimer() {
        resendCountdown = 60;
        resendCodeBtn.disabled = true;
        resendInterval = setInterval(() => {
          resendCountdown--;
          resendTimer.textContent = resendCountdown;
          
          if (resendCountdown <= 0) {
            clearInterval(resendInterval);
            resendCodeBtn.disabled = false;
            resendCodeBtn.innerHTML = 'Resend Code';
          }
        }, 1000);
      }
      
      if (codeForm) {
        codeForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const code = document.getElementById('verificationCode').value.trim();
          if (!code || code.length !== 6) {
            showStatus('code-status', 'Please enter a valid 6-digit code', 'error');
            return;
          }
          
          const originalText = verifyCodeBtn.textContent;
          verifyCodeBtn.textContent = 'Verifying...';
          verifyCodeBtn.disabled = true;
          
          fetch('forgot_password_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=verify_code&email=${encodeURIComponent(currentEmail)}&code=${encodeURIComponent(code)}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showStatus('code-status', 'Code verified successfully!', 'success');
              setTimeout(() => {
                showStep('passwordStep');
              }, 1500);
            } else {
              showStatus('code-status', data.message, 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showStatus('code-status', 'An error occurred. Please try again.', 'error');
          })
          .finally(() => {
            verifyCodeBtn.textContent = originalText;
            verifyCodeBtn.disabled = false;
          });
        });
      }
      
      // Resend code functionality
      if (resendCodeBtn) {
        resendCodeBtn.addEventListener('click', function() {
          if (resendCodeBtn.disabled) return;
          
          const originalText = resendCodeBtn.innerHTML;
          resendCodeBtn.innerHTML = 'Sending...';
          resendCodeBtn.disabled = true;
          
          fetch('forgot_password_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=send_code&email=${encodeURIComponent(currentEmail)}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showStatus('code-status', 'New code sent to your email', 'success');
              startResendTimer();
            } else {
              showStatus('code-status', data.message, 'error');
              resendCodeBtn.innerHTML = originalText;
              resendCodeBtn.disabled = false;
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showStatus('code-status', 'Failed to resend code. Please try again.', 'error');
            resendCodeBtn.innerHTML = originalText;
            resendCodeBtn.disabled = false;
          });
        });
      }
      
      // Step 3: Reset password
      const passwordForm = document.getElementById('forgotPasswordResetForm');
      const resetPasswordBtn = document.getElementById('resetPasswordBtn');
      
      if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
          e.preventDefault();
          
          const newPassword = document.getElementById('newPassword').value;
          const confirmPassword = document.getElementById('confirmNewPassword').value;
          const code = document.getElementById('verificationCode').value;
          
          if (!newPassword || !confirmPassword) {
            showStatus('password-status', 'Please fill in all fields', 'error');
            return;
          }
          
          if (newPassword !== confirmPassword) {
            showStatus('password-status', 'Passwords do not match', 'error');
            return;
          }
          
          if (newPassword.length < 8) {
            showStatus('password-status', 'Password must be at least 8 characters long', 'error');
            return;
          }
          
          const originalText = resetPasswordBtn.textContent;
          resetPasswordBtn.textContent = 'Resetting...';
          resetPasswordBtn.disabled = true;
          
          fetch('forgot_password_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=reset_password&email=${encodeURIComponent(currentEmail)}&code=${encodeURIComponent(code)}&new_password=${encodeURIComponent(newPassword)}&confirm_password=${encodeURIComponent(confirmPassword)}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              showStatus('password-status', data.message, 'success');
              setTimeout(() => {
                hideModal(forgotPasswordModal);
                showModal(loginModal);
                showStatus('login-status', 'Password reset successfully! You can now login with your new password.', 'success');
              }, 2000);
            } else {
              showStatus('password-status', data.message, 'error');
            }
          })
          .catch(error => {
            console.error('Error:', error);
            showStatus('password-status', 'An error occurred. Please try again.', 'error');
          })
          .finally(() => {
            resetPasswordBtn.textContent = originalText;
            resetPasswordBtn.disabled = false;
          });
        });
      }
      
      // Navigation buttons
      const backToEmailBtn = document.getElementById('backToEmailBtn');
      const backToCodeBtn = document.getElementById('backToCodeBtn');
      
      if (backToEmailBtn) {
        backToEmailBtn.addEventListener('click', () => showStep('emailStep'));
      }
      
      if (backToCodeBtn) {
        backToCodeBtn.addEventListener('click', () => showStep('codeStep'));
      }
      
      // Password toggles for forgot password modal
      const toggleNewPassword = document.getElementById('toggleNewPassword');
      const toggleConfirmNewPassword = document.getElementById('toggleConfirmNewPassword');
      
      if (toggleNewPassword) {
        toggleNewPassword.addEventListener('click', function() {
          const input = document.getElementById('newPassword');
          const isPassword = input.type === 'password';
          input.type = isPassword ? 'text' : 'password';
          this.textContent = isPassword ? 'Hide' : 'Show';
        });
      }
      
      if (toggleConfirmNewPassword) {
        toggleConfirmNewPassword.addEventListener('click', function() {
          const input = document.getElementById('confirmNewPassword');
          const isPassword = input.type === 'password';
          input.type = isPassword ? 'text' : 'password';
          this.textContent = isPassword ? 'Hide' : 'Show';
        });
      }
      
      // Password strength for new password
      const newPasswordInput = document.getElementById('newPassword');
      const newPasswordStrengthFill = document.getElementById('newPasswordStrengthFill');
      const newPasswordStrengthText = document.getElementById('newPasswordStrengthText');
      
      if (newPasswordInput && newPasswordStrengthFill && newPasswordStrengthText) {
        newPasswordInput.addEventListener('input', function() {
          const password = this.value;
          const strength = calculatePasswordStrength(password);
          
          newPasswordStrengthFill.style.width = strength.percentage + '%';
          newPasswordStrengthFill.className = 'strength-fill ' + strength.class;
          newPasswordStrengthText.textContent = strength.text;
        });
      }
      
      function calculatePasswordStrength(password) {
        if (!password) {
          return { percentage: 0, class: '', text: 'Enter password to see strength' };
        }
        
        let score = 0;
        let feedback = [];
        
        // Length check
        if (password.length >= 8) score += 25;
        else feedback.push('at least 8 characters');
        
        // Lowercase check
        if (/[a-z]/.test(password)) score += 25;
        else feedback.push('lowercase letter');
        
        // Uppercase check
        if (/[A-Z]/.test(password)) score += 25;
        else feedback.push('uppercase letter');
        
        // Number check
        if (/\d/.test(password)) score += 25;
        else feedback.push('number');
        
        let strengthClass, strengthText;
        
        if (score < 50) {
          strengthClass = 'weak';
          strengthText = feedback.length > 0 ? `Weak - needs: ${feedback.join(', ')}` : 'Weak';
        } else if (score < 75) {
          strengthClass = 'medium';
          strengthText = feedback.length > 0 ? `Fair - needs: ${feedback.join(', ')}` : 'Fair';
        } else if (score < 100) {
          strengthClass = 'good';
          strengthText = feedback.length > 0 ? `Good - needs: ${feedback.join(', ')}` : 'Good';
        } else {
          strengthClass = 'strong';
          strengthText = 'Strong';
        }
        
        return { percentage: score, class: strengthClass, text: strengthText };
      }
    });
  </script>
</body>
</html>
