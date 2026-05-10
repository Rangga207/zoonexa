// script.js - Zoonexa Client-side Scripts

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initBMICalculator();
    initFormValidation();
    initMobileMenu();
    initUserDropdown();
});

// =============================================
// THEME TOGGLE
// =============================================
function initThemeToggle() {
    const body          = document.body;
    const toggleBtn     = document.getElementById('theme-toggle');
    const toggleMobile  = document.getElementById('theme-toggle-mobile');

    function applyTheme(isDark) {
        if (isDark) {
            body.classList.add('theme-dark');
        } else {
            body.classList.remove('theme-dark');
        }
        // Update all toggle button icons
        [toggleBtn, toggleMobile].forEach(btn => {
            if (!btn) return;
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            }
        });
    }

    // Apply saved theme on page load — default: dark
    const savedTheme = localStorage.getItem('zoonexa-theme');
    applyTheme(savedTheme !== 'light'); // dark unless explicitly set to light

    function handleToggle() {
        const isDark = body.classList.toggle('theme-dark');
        localStorage.setItem('zoonexa-theme', isDark ? 'dark' : 'light');
        applyTheme(isDark);
    }

    if (toggleBtn)    toggleBtn.addEventListener('click', handleToggle);
    if (toggleMobile) toggleMobile.addEventListener('click', handleToggle);
}

// =============================================
// MOBILE MENU (Hamburger + Drawer)
// =============================================
function initMobileMenu() {
    const hamburger    = document.getElementById('hamburger');
    const drawer       = document.getElementById('mobileDrawer');
    const overlay      = document.getElementById('mobileOverlay');
    const drawerClose  = document.getElementById('drawerClose');

    if (!hamburger || !drawer) return;

    function openDrawer() {
        drawer.classList.add('open');
        overlay.classList.add('active');
        hamburger.classList.add('active');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeDrawer() {
        drawer.classList.remove('open');
        overlay.classList.remove('active');
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        if (drawer.classList.contains('open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    if (drawerClose) drawerClose.addEventListener('click', closeDrawer);
    if (overlay)     overlay.addEventListener('click', closeDrawer);

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeDrawer();
    });

    // Close when a nav link is clicked (for SPA-like feel)
    const mobileLinks = drawer.querySelectorAll('.mobile-nav-item');
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            setTimeout(closeDrawer, 150);
        });
    });
}

// =============================================
// USER DROPDOWN (desktop)
// =============================================
function initUserDropdown() {
    const userBtn    = document.getElementById('userMenuBtn');
    const dropdown   = document.getElementById('userDropdown');

    if (!userBtn || !dropdown) return;

    userBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        const isOpen = dropdown.classList.toggle('dropdown-open');
        userBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    document.addEventListener('click', () => {
        dropdown.classList.remove('dropdown-open');
        if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
    });
}

// =============================================
// BMI AUTO-CALCULATOR (health_log.php)
// =============================================
function initBMICalculator() {
    const weightInput = document.getElementById('weight_kg');
    const heightInput = document.getElementById('height_m');
    const bmiInput    = document.getElementById('bmi');
    const dateInput   = document.getElementById('log_date');

    // Set default date to today
    if (dateInput && !dateInput.value) {
        dateInput.value = new Date().toISOString().split('T')[0];
    }

    if (!weightInput || !heightInput || !bmiInput) return;

    function updateBMI() {
        const weight = parseFloat(weightInput.value);
        const height = parseFloat(heightInput.value);

        if (!weight || !height || height <= 0 || weight <= 0) {
            bmiInput.value = '';
            clearBMIFeedback();
            return;
        }

        const bmi = weight / (height * height);
        bmiInput.value = bmi.toFixed(2);
        updateBMIFeedback(bmi);
    }

    weightInput.addEventListener('input', updateBMI);
    heightInput.addEventListener('input', updateBMI);
}

// =============================================
// BMI VISUAL FEEDBACK
// =============================================
function updateBMIFeedback(bmi) {
    const bmiInput = document.getElementById('bmi');
    if (!bmiInput) return;

    clearBMIFeedback();

    if (bmi < 18.5) {
        bmiInput.classList.add('bmi-underweight');
        bmiInput.title = 'Underweight';
    } else if (bmi < 25) {
        bmiInput.classList.add('bmi-normal');
        bmiInput.title = 'Normal weight';
    } else if (bmi < 30) {
        bmiInput.classList.add('bmi-overweight');
        bmiInput.title = 'Overweight';
    } else {
        bmiInput.classList.add('bmi-obese');
        bmiInput.title = 'Obese';
    }
}

function clearBMIFeedback() {
    const bmiInput = document.getElementById('bmi');
    if (!bmiInput) return;
    bmiInput.classList.remove('bmi-underweight', 'bmi-normal', 'bmi-overweight', 'bmi-obese');
    bmiInput.title = '';
}

// =============================================
// FORM VALIDATION ENHANCEMENT
// =============================================
function initFormValidation() {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const requiredInputs = form.querySelectorAll('[required]');
            let isValid = true;

            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#e74c3c';
                } else {
                    input.style.borderColor = '';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Mohon isi semua kolom yang wajib diisi.');
            }
        });
    });
}

// =============================================
// SUPER SMOOTH SPA PAGE ROUTER
// =============================================
document.addEventListener('DOMContentLoaded', () => {
    initSPARouter();
});

function initSPARouter() {
    const mainContent = document.querySelector('main.page');
    if (!mainContent) return;

    // Attach click listener to all internal links
    document.body.addEventListener('click', e => {
        const link = e.target.closest('a');
        if (!link) return;
        
        const href = link.getAttribute('href');
        if (!href || href.startsWith('#') || href.startsWith('mailto:') || link.target === '_blank') return;

        // Ensure it's the same origin
        const url = new URL(link.href);
        if (url.origin !== window.location.origin) return;
        
        // Skip some URLs like logout
        if (href.includes('logout.php')) return;

        e.preventDefault();
        navigateTo(url.pathname + url.search);
    });

    window.addEventListener('popstate', () => {
        navigateTo(window.location.pathname + window.location.search, true);
    });
}

async function navigateTo(url, isPopState = false) {
    const mainContent = document.querySelector('main.page');
    if (!mainContent) return;

    // Fade out
    mainContent.classList.add('page-fade-out');

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        // Check if we got redirected (e.g., requireSubscription redirect)
        if (response.redirected) {
            url = response.url; // Update url to the redirected one
        }

        const html = await response.text();

        // Create a temporary document to extract title and main content
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        const newMain = doc.querySelector('main.page');
        const newTitle = doc.querySelector('title');

        if (!newMain) {
            // Fallback: If parsing fails, do a normal redirect
            window.location.href = url;
            return;
        }

        // Wait for fade-out animation
        await new Promise(r => setTimeout(r, 200));

        // Swap content
        mainContent.innerHTML = newMain.innerHTML;
        if (newTitle) document.title = newTitle.textContent;

        // Execute inline scripts inside the new main content
        const scripts = mainContent.querySelectorAll('script');
        scripts.forEach(oldScript => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.appendChild(document.createTextNode(oldScript.innerHTML));
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });

        // Re-initialize global scripts for new DOM
        initBMICalculator();
        initFormValidation();

        // Update history
        if (!isPopState) {
            window.history.pushState({}, '', url);
        }

        // Fade in
        mainContent.classList.remove('page-fade-out');
        mainContent.classList.add('page-fade-in');
        
        // Scroll to top smoothly
        window.scrollTo({ top: 0, behavior: 'smooth' });

        setTimeout(() => {
            mainContent.classList.remove('page-fade-in');
        }, 400);

    } catch (err) {
        console.error('SPA Error:', err);
        window.location.href = url;
    }
}
