// DriveSafe Cover — Main App JS

// ─── Navbar ───────────────────────────────────────────────────
const navbar = document.querySelector('.navbar');
const hamburger = document.querySelector('.hamburger');
const mobileMenu = document.querySelector('.mobile-menu');

window.addEventListener('scroll', () => {
  if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 20);
});

if (hamburger) {
  hamburger.addEventListener('click', () => {
    const open = mobileMenu?.classList.toggle('show');
    hamburger.querySelectorAll('span').forEach((s, i) => {
      if (open) {
        if (i === 0) s.style.transform = 'rotate(45deg) translate(5px, 5px)';
        if (i === 1) s.style.opacity = '0';
        if (i === 2) s.style.transform = 'rotate(-45deg) translate(5px, -5px)';
      } else {
        s.style.transform = ''; s.style.opacity = '';
      }
    });
  });
  // Close menu on link click
  mobileMenu?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    mobileMenu.classList.remove('show');
    hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
  }));
}

// ─── FAQ Accordion ────────────────────────────────────────────
document.querySelectorAll('.faq-item').forEach(item => {
  item.querySelector('.faq-btn')?.addEventListener('click', () => {
    const isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
    if (!isOpen) item.classList.add('open');
  });
});

// ─── Scroll Animations ────────────────────────────────────────
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('animate-in');
      observer.unobserve(e.target);
    }
  });
}, { threshold: 0.12 });
document.querySelectorAll('[data-animate]').forEach(el => observer.observe(el));

// ─── Auth Tabs (login page) ───────────────────────────────────
document.querySelectorAll('[data-tab-btn]').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.dataset.tabBtn;
    document.querySelectorAll('[data-tab-btn]').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('[data-tab]').forEach(t => t.classList.toggle('hidden', t.dataset.tab !== target));
    btn.classList.add('active');
  });
});

// ─── Password Toggle ──────────────────────────────────────────
document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp = btn.previousElementSibling;
    if (!inp) return;
    const isPass = inp.type === 'password';
    inp.type = isPass ? 'text' : 'password';
    btn.textContent = isPass ? '🙈' : '👁️';
  });
});

// ─── Password Strength ───────────────────────────────────────
const passInput = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');
if (passInput && strengthBar) {
  passInput.addEventListener('input', () => {
    const v = passInput.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const labels = ['', 'Weak', 'Fair', 'Strong', 'Excellent'];
    const colors = ['', '#EF4444', '#F59E0B', '#3B82F6', '#059669'];
    const widths = ['0%', '25%', '50%', '75%', '100%'];
    strengthBar.style.width = widths[score];
    strengthBar.style.background = colors[score];
    if (strengthText) { strengthText.textContent = labels[score]; strengthText.style.color = colors[score]; }
  });
}

// ─── Sidebar (Dashboard) ──────────────────────────────────────
document.querySelectorAll('.sidebar-item').forEach(item => {
  item.addEventListener('click', function() {
    if (this.dataset.page) {
      document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
      this.classList.add('active');
      document.querySelectorAll('[data-panel]').forEach(p => p.classList.toggle('hidden', p.dataset.panel !== this.dataset.page));
    }
  });
});

// ─── OTP Input ────────────────────────────────────────────────
document.querySelectorAll('.otp-input').forEach((inp, i, all) => {
  inp.addEventListener('input', () => {
    inp.value = inp.value.replace(/\D/g, '').slice(-1);
    if (inp.value && all[i + 1]) all[i + 1].focus();
    if ([...all].every(o => o.value)) {
      document.getElementById('otpSubmit')?.click();
    }
  });
  inp.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !inp.value && all[i - 1]) all[i - 1].focus();
  });
});

// ─── OTP Countdown ────────────────────────────────────────────
function startOtpCountdown(seconds = 60) {
  const btn  = document.getElementById('resendOtp');
  const timer= document.getElementById('otpTimer');
  if (!btn || !timer) return;
  let remaining = seconds;
  btn.disabled = true;
  const iv = setInterval(() => {
    remaining--;
    timer.textContent = `${remaining}s`;
    if (remaining <= 0) {
      clearInterval(iv);
      btn.disabled = false;
      timer.textContent = '';
    }
  }, 1000);
}
if (document.querySelector('.otp-input')) startOtpCountdown(60);

// ─── Toast Notifications ─────────────────────────────────────
window.showToast = function(msg, type = 'info') {
  const t = document.createElement('div');
  t.className = `toast toast-${type}`;
  t.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()">×</button>`;
  document.body.appendChild(t);
  requestAnimationFrame(() => t.classList.add('show'));
  setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
};

// ─── Smooth Scroll ────────────────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});

// Toast styles injected
const toastStyles = document.createElement('style');
toastStyles.textContent = `
  .toast{position:fixed;bottom:24px;right:24px;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:14px 20px;display:flex;align-items:center;gap:12px;box-shadow:0 8px 32px rgba(11,30,61,0.16);z-index:9999;transform:translateY(20px);opacity:0;transition:all .3s ease;max-width:360px;font-size:14px;font-weight:500;color:#374151}
  .toast.show{transform:translateY(0);opacity:1}
  .toast-success{border-left:4px solid #059669}
  .toast-error{border-left:4px solid #DC2626}
  .toast-info{border-left:4px solid #2563EB}
  .toast button{background:none;border:none;font-size:18px;color:#9CA3AF;cursor:pointer;padding:0;margin-left:auto;line-height:1}
  .mobile-menu.show{display:block}
`;
document.head.appendChild(toastStyles);
