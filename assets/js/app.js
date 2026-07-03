// Dark/Light mode
const html = document.documentElement;
const savedMode = localStorage.getItem('mode') || 'light';
html.setAttribute('data-mode', savedMode);
updateModeUI(savedMode);

function toggleMode() {
  const current = html.getAttribute('data-mode');
  const next = current === 'light' ? 'dark' : 'light';
  html.setAttribute('data-mode', next);
  localStorage.setItem('mode', next);
  updateModeUI(next);
}

function updateModeUI(mode) {
  const icon = document.getElementById('modeIcon');
  const lbl = document.getElementById('modeLbl');
  const logo = document.getElementById('topbarLogo');
  if (!icon) return;
  if (mode === 'dark') {
    icon.className = 'ti ti-moon';
    if (lbl) lbl.textContent = 'Dark';
    if (logo) logo.src = '/assignhub/assets/img/logo-white.png';
  } else {
    icon.className = 'ti ti-sun';
    if (lbl) lbl.textContent = 'Light';
    if (logo) logo.src = '/assignhub/assets/img/logo-dark.png';
  }
}

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

const logoutBtn = document.getElementById('logoutBtn');
const logoutModal = document.getElementById('logoutModal');
const logoutCancelBtn = document.getElementById('logoutCancelBtn');
const logoutConfirmBtn = document.getElementById('logoutConfirmBtn');
if (logoutBtn && logoutModal && logoutCancelBtn && logoutConfirmBtn) {
  logoutBtn.addEventListener('click', function(event) {
    event.preventDefault();
    logoutModal.classList.add('active');
  });
  logoutCancelBtn.addEventListener('click', function() {
    logoutModal.classList.remove('active');
  });
  logoutConfirmBtn.addEventListener('click', function() {
    window.location.href = logoutBtn.href;
  });
  logoutModal.addEventListener('click', function(event) {
    if (event.target === logoutModal) {
      logoutModal.classList.remove('active');
    }
  });
}

// File upload preview
const fileInput = document.getElementById('file');
const uploadZone = document.getElementById('uploadZone');
if (fileInput && uploadZone) {
  uploadZone.addEventListener('click', () => fileInput.click());
  uploadZone.addEventListener('dragover', e => { e.preventDefault(); uploadZone.style.borderColor = 'var(--accent)'; });
  uploadZone.addEventListener('dragleave', () => uploadZone.style.borderColor = '');
  uploadZone.addEventListener('drop', e => {
    e.preventDefault();
    fileInput.files = e.dataTransfer.files;
    updateFilePreview(fileInput.files[0]);
  });
  fileInput.addEventListener('change', () => updateFilePreview(fileInput.files[0]));
}

function updateFilePreview(file) {
  if (!file) return;
  const zone = document.getElementById('uploadZone');
  zone.innerHTML = `<i class="ti ti-file-check" style="color:var(--success)"></i><strong>${file.name}</strong><br><span style="font-size:13px;color:var(--text3)">${(file.size/1024/1024).toFixed(2)} MB</span>`;
}
