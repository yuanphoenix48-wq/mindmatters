/* ========================================
   MIND MATTERS - MODAL DEBUG SCRIPT
   ======================================== */

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== MODAL DEBUG SCRIPT LOADED ===');
    
    // Check if elements exist
    const loginModal = document.getElementById('loginModal');
    const signupModal = document.getElementById('signupModal');
    const loginBtn = document.querySelector('.loginbtn');
    const signupBtn = document.querySelector('.signupbtn');
    
    console.log('Elements found:');
    console.log('- Login Modal:', loginModal);
    console.log('- Signup Modal:', signupModal);
    console.log('- Login Button:', loginBtn);
    console.log('- Signup Button:', signupBtn);
    
    // Check modal styles
    if (loginModal) {
        const styles = window.getComputedStyle(loginModal);
        console.log('Login Modal computed styles:');
        console.log('- display:', styles.display);
        console.log('- visibility:', styles.visibility);
        console.log('- opacity:', styles.opacity);
        console.log('- z-index:', styles.zIndex);
    }
    
    // Add click listeners with debugging
    if (loginBtn) {
        loginBtn.addEventListener('click', function(e) {
            console.log('Login button clicked!');
            e.preventDefault();
            if (loginModal) {
                console.log('Attempting to show login modal...');
                loginModal.style.display = 'flex';
                loginModal.classList.add('show');
                loginModal.setAttribute('aria-hidden', 'false');
                console.log('Login modal should now be visible');
            } else {
                console.error('Login modal not found!');
            }
        });
    } else {
        console.error('Login button not found!');
    }
    
    if (signupBtn) {
        signupBtn.addEventListener('click', function(e) {
            console.log('Signup button clicked!');
            e.preventDefault();
            if (signupModal) {
                console.log('Attempting to show signup modal...');
                signupModal.style.display = 'flex';
                signupModal.classList.add('show');
                signupModal.setAttribute('aria-hidden', 'false');
                console.log('Signup modal should now be visible');
            } else {
                console.error('Signup modal not found!');
            }
        });
    } else {
        console.error('Signup button not found!');
    }
    
    // Add close functionality
    const closeButtons = document.querySelectorAll('.modal .close');
    console.log('Close buttons found:', closeButtons.length);
    
    closeButtons.forEach((btn, index) => {
        btn.addEventListener('click', function() {
            console.log(`Close button ${index + 1} clicked`);
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                console.log('Modal closed');
            }
        });
    });
    
    // Test modal visibility
    setTimeout(() => {
        console.log('=== TESTING MODAL VISIBILITY ===');
        if (loginModal) {
            console.log('Testing login modal visibility...');
            loginModal.style.display = 'flex';
            loginModal.classList.add('show');
            loginModal.setAttribute('aria-hidden', 'false');
            
            setTimeout(() => {
                const styles = window.getComputedStyle(loginModal);
                console.log('After show attempt:');
                console.log('- display:', styles.display);
                console.log('- visibility:', styles.visibility);
                console.log('- opacity:', styles.opacity);
                
                // Hide it again
                loginModal.style.display = 'none';
                loginModal.classList.remove('show');
                loginModal.setAttribute('aria-hidden', 'true');
            }, 2000);
        }
    }, 1000);
});

