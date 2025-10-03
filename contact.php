<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Contact Us - Mind Matters</title>
  <link rel="stylesheet" href="styles/contact.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>
<body>
  <div class="container">
    <div class="hero-section">
      <h1>Contact Us</h1>
      <p class="intro-text">We'd love to hear from you! Send us a message and we'll get back to you as soon as possible.</p>
    </div>
    
    <div class="contact-form-container">
    
      <form id="contactForm" method="post">
        <div class="form-group">
          <label for="name">Full Name</label>
          <input type="text" id="name" name="name" required />
          <div class="error-message" id="name-error"></div>
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" required />
          <div class="error-message" id="email-error"></div>
        </div>

        <div class="form-group">
          <label for="message">Message</label>
          <textarea id="message" name="message" rows="5" required></textarea>
          <div class="error-message" id="message-error"></div>
        </div>

        <div id="form-messages"></div>

        <button type="submit" id="submit-btn">
          <span class="btn-text">Send Message</span>
          <span class="btn-spinner" style="display: none;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12a9 9 0 11-6.219-8.56"/>
            </svg>
          </span>
        </button>
      </form>
    </div>

    <div class="back-link">
      <a href="index.php">← Return to Home</a>
    </div>
  </div>

  <script>
  // Contact Form JavaScript
  document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contactForm');
    const submitBtn = document.getElementById('submit-btn');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnSpinner = submitBtn.querySelector('.btn-spinner');
    const formMessages = document.getElementById('form-messages');

    // Form submission handler
    form.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      // Clear previous messages
      clearMessages();
      clearErrors();
      
      // Show loading state
      setLoadingState(true);
      
      // Get form data
      const formData = new FormData(form);
      
      try {
        const response = await fetch('process_contact.php', {
          method: 'POST',
          body: formData
        });
        
        // Check if response is ok
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        // Get response text first to check for errors
        const responseText = await response.text();
        
        // Try to parse as JSON
        let result;
        try {
          result = JSON.parse(responseText);
        } catch (jsonError) {
          console.error('JSON parse error:', jsonError);
          console.error('Response text:', responseText);
          throw new Error('Invalid response from server. Please check if the database table exists.');
        }
        
        if (result.success) {
          showSuccess(result.message);
          form.reset();
        } else {
          showError(result.message);
          if (result.errors) {
            showFieldErrors(result.errors);
          }
        }
      } catch (error) {
        showError('Sorry, there was an error processing your request. Please try again later.');
        console.error('Form submission error:', error);
      } finally {
        setLoadingState(false);
      }
    });

    // Real-time validation
    const inputs = form.querySelectorAll('input, textarea');
    inputs.forEach(input => {
      input.addEventListener('blur', function() {
        validateField(this);
      });
      
      input.addEventListener('input', function() {
        clearFieldError(this);
      });
    });

    function setLoadingState(loading) {
      if (loading) {
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnSpinner.style.display = 'inline-block';
        btnSpinner.style.animation = 'spin 1s linear infinite';
      } else {
        submitBtn.disabled = false;
        btnText.style.display = 'inline';
        btnSpinner.style.display = 'none';
        btnSpinner.style.animation = 'none';
      }
    }

    function showSuccess(message) {
      formMessages.innerHTML = `
        <div class="alert alert-success">
          <strong>Success!</strong> ${message}
        </div>
      `;
      formMessages.scrollIntoView({ behavior: 'smooth' });
    }

    function showError(message) {
      formMessages.innerHTML = `
        <div class="alert alert-error">
          <strong>Error!</strong> ${message}
        </div>
      `;
      formMessages.scrollIntoView({ behavior: 'smooth' });
    }

    function clearMessages() {
      formMessages.innerHTML = '';
    }

    function clearErrors() {
      const errorMessages = form.querySelectorAll('.error-message');
      errorMessages.forEach(error => error.textContent = '');
    }

    function clearFieldError(field) {
      const errorElement = document.getElementById(field.name + '-error');
      if (errorElement) {
        errorElement.textContent = '';
        field.classList.remove('error');
      }
    }

    function showFieldErrors(errors) {
      Object.keys(errors).forEach(fieldName => {
        const field = document.getElementById(fieldName);
        const errorElement = document.getElementById(fieldName + '-error');
        
        if (field && errorElement) {
          field.classList.add('error');
          errorElement.textContent = errors[fieldName];
        }
      });
    }

    function validateField(field) {
      const value = field.value.trim();
      const fieldName = field.name;
      const errorElement = document.getElementById(fieldName + '-error');
      
      let error = '';
      
      if (field.required && !value) {
        error = `${fieldName.charAt(0).toUpperCase() + fieldName.slice(1)} is required`;
      } else if (fieldName === 'email' && value && !isValidEmail(value)) {
        error = 'Please enter a valid email address';
      }
      
      if (error) {
        field.classList.add('error');
        errorElement.textContent = error;
      } else {
        field.classList.remove('error');
        errorElement.textContent = '';
      }
    }

    function isValidEmail(email) {
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      return emailRegex.test(email);
    }
  });

  // Add CSS for spinner animation
  const style = document.createElement('style');
  style.textContent = `
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    
    .error-message {
      color: #E74C3C;
      font-size: 0.875rem;
      margin-top: 0.5rem;
      display: block;
    }
    
    .form-group input.error,
    .form-group textarea.error {
      border-color: #E74C3C;
      box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.15);
    }
    
    .alert {
      padding: 1rem 1.25rem;
      margin-bottom: 1rem;
      border: 1px solid transparent;
      border-radius: 0.375rem;
      font-weight: 500;
    }
    
    .alert-success {
      color: #155724;
      background-color: #d4edda;
      border-color: #c3e6cb;
    }
    
    .alert-error {
      color: #721c24;
      background-color: #f8d7da;
      border-color: #f5c6cb;
    }
    
    #submit-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  `;
  document.head.appendChild(style);
  </script>
</body>
</html>
