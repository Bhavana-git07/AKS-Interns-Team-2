/* ============================================
   COMPLIANCE AUDIT PLATFORM — GLOBAL JS
   ============================================ */

function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  if (typeof str !== 'string') str = String(str);
  return str.replace(/[&<>"']/g, function(m) {
    switch (m) {
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return m;
    }
  });
}

// ---- CSRF PROTECTION ----
let csrfToken = sessionStorage.getItem('csrfToken') || '';
window.csrfToken = csrfToken;

// Intercept all fetch requests to inject CSRF token
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const isRunMapping = typeof url === 'string' && url.includes('run_mapping.php');
  
  if (window.csrfToken && (method !== 'GET' || isRunMapping)) {
    options.headers = options.headers || {};
    if (options.headers instanceof Headers) {
      options.headers.set('X-CSRF-Token', window.csrfToken);
    } else if (Array.isArray(options.headers)) {
      options.headers.push(['X-CSRF-Token', window.csrfToken]);
    } else {
      options.headers['X-CSRF-Token'] = window.csrfToken;
    }

    if (isRunMapping && !url.includes('csrf_token=')) {
      const separator = url.includes('?') ? '&' : '?';
      url = `${url}${separator}csrf_token=${encodeURIComponent(window.csrfToken)}`;
    }
  }
  return originalFetch.call(this, url, options).then(response => {
    if (response.status === 401) {
      localStorage.removeItem('currentUser');
      sessionStorage.removeItem('csrfToken');
      const isLoginPage = window.location.pathname.endsWith('login.html') || window.location.pathname.split('/').pop() === '';
      if (!isLoginPage) {
        const isPageSubdir = window.location.pathname.includes('/pages/');
        window.location.href = isPageSubdir ? '../login.html' : 'login.html';
      }
    }
    return response;
  });
};

function fetchCsrfToken() {
  const isPageSubdir = window.location.pathname.includes('/pages/');
  const url = (isPageSubdir ? '../' : '') + 'backend/api/csrf_token.php';
  return originalFetch(url)
    .then(r => r.json())
    .then(res => {
      if (res.success && res.data && res.data.csrf_token) {
        csrfToken = res.data.csrf_token;
        sessionStorage.setItem('csrfToken', csrfToken);
        window.csrfToken = csrfToken;
        injectCsrfInputs();
        return csrfToken;
      }
    })
    .catch(err => console.error('Failed to fetch CSRF token:', err));
}

function injectCsrfInputs() {
  if (!window.csrfToken) return;
  document.querySelectorAll('form').forEach(form => {
    let input = form.querySelector('input[name="csrf_token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'csrf_token';
      form.appendChild(input);
    }
    input.value = window.csrfToken;
  });
}

// Fetch token immediately on page load
fetchCsrfToken();
document.addEventListener('DOMContentLoaded', injectCsrfInputs);

// ---- TOAST ----
function showToast(message, type = 'success') {
  const icons = {
    success: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--clr-accent)"><path d="M20 6L9 17l-5-5"/></svg>`,
    error:   `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--clr-danger)"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>`,
    info:    `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--clr-blue)"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>`
  };
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = `toast toast--${type}`;
  toast.innerHTML = `${icons[type]}<span>${message}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateX(20px)';
    toast.style.transition = '0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3200);
}

// ---- MODAL ----
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
// Close on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

// ---- SIDEBAR TOGGLE (mobile) ----
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.sidebar-overlay');
  if (sidebar) sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('open');
}

// ---- FORM VALIDATION ----
function validateRequired(formEl) {
  let valid = true;
  formEl.querySelectorAll('[required]').forEach(input => {
    const err = input.closest('.form-group')?.querySelector('.form-error');
    if (!input.value.trim()) {
      input.classList.add('error');
      if (err) err.classList.add('show');
      valid = false;
    } else {
      input.classList.remove('error');
      if (err) err.classList.remove('show');
    }
  });
  return valid;
}

// ---- PASSWORD STRENGTH ----
function checkPasswordStrength(password) {
  let score = 0;
  if (password.length >= 8)  score++;
  if (password.length >= 12) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;
  return score; // 0-5
}

function renderPasswordStrength(score, barEl, labelEl) {
  const levels = [
    { label: 'Very Weak', color: '#F75F4F', w: '10%' },
    { label: 'Weak',      color: '#F7924F', w: '25%' },
    { label: 'Fair',      color: '#F7B24F', w: '50%' },
    { label: 'Good',      color: '#4F8EF7', w: '75%' },
    { label: 'Strong',    color: '#3DD6AC', w: '100%' }
  ];
  const lvl = levels[Math.min(score, 4)];
  if (barEl) { barEl.style.width = lvl.w; barEl.style.background = lvl.color; }
  if (labelEl) { labelEl.textContent = lvl.label; labelEl.style.color = lvl.color; }
}

// ---- API HELPERS ----
function getApiUrl(endpoint) {
  const isPageSubdir = window.location.pathname.includes('/pages/');
  const base = isPageSubdir ? '../backend/api/' : 'backend/api/';
  return base + endpoint;
}

function handleLogout(e) {
  if (e) e.preventDefault();
  const endpoint = getApiUrl('logout.php');
  fetch(endpoint, { method: 'POST' })
    .finally(() => {
      localStorage.removeItem('currentUser');
      const isPageSubdir = window.location.pathname.includes('/pages/');
      window.location.href = isPageSubdir ? '../login.html' : 'login.html';
    });
}

// ---- ACTIVE NAV ----
function setActiveNav() {
  const path = window.location.pathname.split('/').pop();
  document.querySelectorAll('.nav-item').forEach(item => {
    const href = item.getAttribute('href') || '';
    item.classList.toggle('active', href === path || href.endsWith(path));
  });
}

document.addEventListener('DOMContentLoaded', () => {
  const isLoginPage = window.location.pathname.endsWith('login.html') || window.location.pathname.split('/').pop() === '';
  const currentUserStr = localStorage.getItem('currentUser');
  
  if (!currentUserStr) {
    if (!isLoginPage) {
      const isPageSubdir = window.location.pathname.includes('/pages/');
      window.location.href = isPageSubdir ? '../login.html' : 'login.html';
      return;
    }
  } else {
    // Already logged in
    const currentUser = JSON.parse(currentUserStr);
    
    // Redirect away from login page if already logged in
    if (isLoginPage) {
      window.location.href = 'dashboard.html';
      return;
    }

    // Redirect to change-password if first_login is true and they are not already there
    if (currentUser.first_login && !window.location.pathname.endsWith('change-password.html')) {
      const isPageSubdir = window.location.pathname.includes('/pages/');
      window.location.href = isPageSubdir ? 'change-password.html' : 'pages/change-password.html';
      return;
    }

    // Populate user profile info in sidebar
    const avatarEl = document.querySelector('.sidebar__avatar');
    const nameEl = document.querySelector('.sidebar__user-name');
    const roleEl = document.querySelector('.sidebar__user-role');
    
    if (currentUser.name) {
      if (avatarEl) {
        avatarEl.textContent = currentUser.name.split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
      }
      if (nameEl) {
        nameEl.textContent = currentUser.name;
      }
      if (roleEl) {
        const rolesMap = {
          'admin': 'Platform Admin',
          'user': 'Client User',
          'auditor': 'External Auditor'
        };
        roleEl.textContent = rolesMap[currentUser.role] || currentUser.role;
      }
    }

    // Add Logout button dynamically to sidebar nav
    const nav = document.querySelector('.sidebar__nav');
    if (nav && !document.getElementById('sidebarLogoutBtn')) {
      const logoutLink = document.createElement('a');
      logoutLink.href = '#';
      logoutLink.id = 'sidebarLogoutBtn';
      logoutLink.className = 'nav-item';
      logoutLink.style.marginTop = '20px';
      logoutLink.style.borderTop = '1px solid var(--clr-border)';
      logoutLink.style.paddingTop = '14px';
      logoutLink.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--clr-danger); width: 16px; height: 16px;"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span style="color:var(--clr-danger); font-weight: 600;">Logout</span>
      `;
      logoutLink.addEventListener('click', handleLogout);
      nav.appendChild(logoutLink);
    }
  }

  // Highlight active nav link
  setActiveNav();
});

// ---- TABLE SEARCH ----
function tableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
}

// ---- CONFIRM DELETE ----
function confirmDelete(message, onConfirm) {
  if (confirm(message || 'Are you sure? This action cannot be undone.')) {
    onConfirm();
  }
}
