// ============================================================
// DriveSafe Cover — API Client
// Connects all frontend forms to the PHP backend
// ============================================================

const API_BASE = '/api'; // Change to 'https://yourdomain.com.au/api' after deployment

// ─── HTTP Helper ─────────────────────────────────────────────
async function apiCall(endpoint, method = 'GET', body = null, isFormData = false) {
  const headers = {};
  const token = localStorage.getItem('dsc_token');
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (body && !isFormData) headers['Content-Type'] = 'application/json';

  const config = { method, headers };
  if (body) config.body = isFormData ? body : JSON.stringify(body);

  try {
    const res = await fetch(`${API_BASE}/${endpoint}`, config);
    const data = await res.json();
    if (!res.ok) throw { status: res.status, message: data.message || 'Request failed', errors: data.errors };
    return data;
  } catch (err) {
    if (err.status) throw err;
    throw { status: 0, message: 'Network error. Please check your connection.' };
  }
}

// ─── Auth Store ───────────────────────────────────────────────
const Auth = {
  save(token, user, isAdmin = false) {
    localStorage.setItem('dsc_token', token);
    localStorage.setItem('dsc_user', JSON.stringify(user));
    localStorage.setItem('dsc_is_admin', isAdmin ? '1' : '0');
  },
  clear() {
    ['dsc_token','dsc_user','dsc_is_admin','dsc_quote'].forEach(k => localStorage.removeItem(k));
  },
  user()    { try { return JSON.parse(localStorage.getItem('dsc_user') || 'null'); } catch { return null; } },
  token()   { return localStorage.getItem('dsc_token'); },
  isAdmin() { return localStorage.getItem('dsc_is_admin') === '1'; },
  isLoggedIn() { return !!this.token(); },
};

// ─── Quote Form (index.html) ─────────────────────────────────
const quoteForm = document.getElementById('quoteForm');
if (quoteForm) {
  // Auto-set default dates
  const today = new Date();
  const sevenDays = new Date(today); sevenDays.setDate(today.getDate() + 7);
  const fmt = d => d.toISOString().split('T')[0];
  const startEl = document.getElementById('quoteStartDate');
  const endEl   = document.getElementById('quoteEndDate');
  if (startEl && !startEl.value) startEl.value = fmt(today);
  if (endEl   && !endEl.value)   endEl.value   = fmt(sevenDays);
  startEl.min = fmt(today);

  // Duration chip
  function updateDuration() {
    const s = new Date(startEl.value), e = new Date(endEl.value);
    const chip = document.getElementById('quoteDuration');
    if (!chip) return;
    if (s && e && e > s) {
      const days = Math.ceil((e - s) / 86400000);
      const calendarIcon = '<svg style="position:relative;top:3px;margin-right:4px" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
      chip.innerHTML = `${calendarIcon} ${days} day${days !== 1 ? 's' : ''} rental period`;
      chip.style.display = 'block';
    } else {
      chip.style.display = 'none';
    }
  }
  startEl.addEventListener('change', updateDuration);
  endEl.addEventListener('change', updateDuration);
  updateDuration();

  // Enforce end > start
  startEl.addEventListener('change', () => {
    endEl.min = startEl.value;
    if (endEl.value && endEl.value <= startEl.value) {
      const d = new Date(startEl.value); d.setDate(d.getDate() + 1);
      endEl.value = fmt(d);
    }
    updateDuration();
  });

  quoteForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('quoteSubmit');
    btn.disabled = true;
    btn.innerHTML = '<span style="opacity:.7">Calculating...</span>';

    const payload = {
      state:      document.getElementById('quoteState').value,
      start_date: startEl.value,
      start_time: document.getElementById('quoteStartTime').value,
      end_date:   endEl.value,
      end_time:   document.getElementById('quoteEndTime').value,
    };

    if (!payload.state) {
      showToast('Please select a state.', 'error');
      btn.disabled = false; btn.innerHTML = 'GET A QUOTE →'; return;
    }

    try {
      const res = await apiCall('quotes', 'POST', payload);
      // Save minimal context (no price)
      sessionStorage.setItem('dsc_quote', JSON.stringify({
        quote_id:   res.data.quote_id,
        state:      payload.state,
        start_date: payload.start_date,
        start_time: payload.start_time,
        end_date:   payload.end_date,
        end_time:   payload.end_time,
        days:       res.data.days,
      }));
      window.location.href = Auth.isLoggedIn() ? `/dashboard-quote.html?q=${res.data.quote_id}` : `/quote-result.html?q=${res.data.quote_id}`;
    } catch (err) {
      showToast(err.message, 'error');
      btn.disabled = false; btn.innerHTML = 'GET A QUOTE →';
    }
  });
}

// ─── Quote Result Page ────────────────────────────────────────
if (document.getElementById('coverageTiers')) {
  const params = new URLSearchParams(window.location.search);
  const quoteId = params.get('q') || JSON.parse(sessionStorage.getItem('dsc_quote') || '{}').quote_id;

  if (!quoteId) { window.location.href = '/'; }

  let selectedCoverage = 4000;
  let quoteData = null;

  async function loadQuote() {
    try {
      const res = await apiCall(`quotes?id=${quoteId}`);
      quoteData = res.data;
      renderQuoteResult(quoteData);
    } catch (err) {
      showToast('Could not load quote. Please try again.', 'error');
    }
  }

  function renderQuoteResult(q) {
    // Trip summary
    const fmt = d => new Date(d).toLocaleDateString('en-AU', { day:'numeric', month:'short', year:'numeric' });
    document.getElementById('result-state') && (document.getElementById('result-state').textContent = q.state);
    document.getElementById('result-start') && (document.getElementById('result-start').textContent = fmt(q.start_date));
    document.getElementById('result-end')   && (document.getElementById('result-end').textContent   = fmt(q.end_date));
    document.getElementById('result-days')  && (document.getElementById('result-days').textContent  = `${q.days} days`);

    // Render tier cards
    const container = document.getElementById('coverageTiers');
    if (!container) return;
    container.innerHTML = '';
    q.options.forEach(opt => {
      const isDefault = opt.coverage_amount === 4000;
      const isBest    = opt.coverage_amount === 6000;
      const card = document.createElement('div');
      card.className = `tier-card${isDefault ? ' selected' : ''}`;
      card.dataset.coverage    = opt.coverage_amount;
      card.dataset.pricePerDay = opt.price_per_day;
      card.dataset.total       = opt.total_price;
      card.innerHTML = `
        ${isBest ? '<span class="tier-best">⭐ Best Value</span>' : ''}
        <span class="tier-limit">$${opt.coverage_amount.toLocaleString()}</span>
        <span class="tier-price">$${opt.price_per_day}<small>/day</small></span>
        <span class="tier-badge">Total: $${opt.total_price}</span>
      `;
      card.addEventListener('click', () => selectTier(card, opt));
      container.appendChild(card);
    });

    // Default selection
    const defaultOpt = q.options.find(o => o.coverage_amount === 4000);
    if (defaultOpt) {
      updateBreakdown(defaultOpt, q.days);
      // Save default to sessionStorage so checkout has it
      const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
      if (!saved.coverage) {
        sessionStorage.setItem('dsc_quote', JSON.stringify({
          ...saved,
          coverage:    defaultOpt.coverage_amount,
          pricePerDay: defaultOpt.price_per_day,
          totalPrice:  defaultOpt.total_price,
          tierLabel:   `$${defaultOpt.coverage_amount.toLocaleString()} Coverage`,
        }));
      }
    }
  }

  function selectTier(card, opt) {
    document.querySelectorAll('.tier-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedCoverage = opt.coverage_amount;
    updateBreakdown(opt, quoteData.days);
    // Update saved quote with selection
    const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
    sessionStorage.setItem('dsc_quote', JSON.stringify({
      ...saved,
      coverage:    opt.coverage_amount,
      pricePerDay: opt.price_per_day,
      totalPrice:  opt.total_price,
      tierLabel:   `$${opt.coverage_amount.toLocaleString()} Coverage`,
    }));
  }

  function updateBreakdown(opt, days) {
    const animate = el => { el.classList.remove('price-update'); void el.offsetWidth; el.classList.add('price-update'); };
    const set = (id, v) => { const el = document.getElementById(id); if (el) { el.textContent = v; animate(el); } };
    set('result-coverage',      `$${opt.coverage_amount.toLocaleString()}`);
    set('result-price-per-day', `$${opt.price_per_day}/day`);
    set('result-duration',      `${days} days`);
    set('result-total',         `$${opt.total_price}`);
  }

  document.getElementById('checkoutBtn')?.addEventListener('click', () => {
    window.location.href = '/checkout.html';
  });

  loadQuote();
}

// ─── Login Form ───────────────────────────────────────────────
const loginForm = document.getElementById('loginForm');
if (loginForm) {
  const pwdSection = document.getElementById('passwordSection');
  const otpSection = document.getElementById('otpSection');
  const emailInput = document.getElementById('loginEmail');
  const passInput  = document.getElementById('loginPass');
  const otpInput   = document.getElementById('loginOtp');
  const signInPassBtn = document.getElementById('signInPassBtn');
  const verifyOtpBtn  = document.getElementById('verifyOtpBtn');

  // Handle traditional submit (Enter key)
  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (pwdSection.style.display !== 'none') {
      signInPassBtn.click();
    } else {
      verifyOtpBtn.click();
    }
  });

  // Flow: Switch to OTP
  document.getElementById('useOtpBtn')?.addEventListener('click', async () => {
    if (!emailInput.value) {
      showToast('Please enter your email first to receive a code.', 'error');
      emailInput.focus();
      return;
    }
    const btn = document.getElementById('useOtpBtn');
    const oldText = btn.textContent;
    btn.textContent = 'Sending code...'; btn.disabled = true;

    try {
      await apiCall('auth/send-otp', 'POST', { email: emailInput.value });
      pwdSection.style.display = 'none';
      otpSection.style.display = 'block';
      showToast('A 6-digit code has been sent to your email.', 'success');
      otpInput.focus();
    } catch (err) {
      showToast(err.message, 'error');
    }
    btn.textContent = oldText; btn.disabled = false;
  });

  // Flow: Switch back to Password
  document.getElementById('backToPasswordBtn')?.addEventListener('click', () => {
    otpSection.style.display = 'none';
    pwdSection.style.display = 'block';
  });

  // Handle Login via Password
  signInPassBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!emailInput.value || !passInput.value) return showToast('Please enter email and password.', 'error');
    
    signInPassBtn.textContent = 'Signing in...'; signInPassBtn.disabled = true;
    try {
      const res = await apiCall('auth/login', 'POST', { email: emailInput.value, password: passInput.value });
      Auth.save(res.data.token, res.data.customer);
      const next = new URLSearchParams(window.location.search).get('next');
      window.location.href = next ? `/${next}.html` : '/dashboard.html';
    } catch (err) {
      showToast(err.message, 'error');
      signInPassBtn.textContent = 'Sign In →'; signInPassBtn.disabled = false;
    }
  });

  // Handle Login via OTP
  verifyOtpBtn?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!emailInput.value || !otpInput.value) return showToast('Please enter your email and the 6-digit code.', 'error');
    
    verifyOtpBtn.textContent = 'Verifying...'; verifyOtpBtn.disabled = true;
    try {
      const res = await apiCall('auth/verify-otp', 'POST', { email: emailInput.value, otp_code: otpInput.value });
      Auth.save(res.data.token, res.data.customer);
      const next = new URLSearchParams(window.location.search).get('next');
      window.location.href = next ? `/${next}.html` : '/dashboard.html';
    } catch (err) {
      showToast(err.message, 'error');
      verifyOtpBtn.textContent = 'Verify Code & Sign In →'; verifyOtpBtn.disabled = false;
    }
  });
}

// ─── Register Form ────────────────────────────────────────────
document.getElementById('registerForm')?.addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn  = e.target.querySelector('button[type=submit]');
  const body = {
    full_name: document.getElementById('regName').value,
    email:     document.getElementById('regEmail').value,
    phone:     document.getElementById('regPhone').value,
    state:     document.getElementById('regState').value,
    password:  document.getElementById('password').value,
  };
  btn.textContent = 'Creating account...'; btn.disabled = true;
  try {
    const res = await apiCall('auth/register', 'POST', body);
    Auth.save(res.data.token, res.data.customer);
    showToast('Account created! Redirecting...', 'success');
    setTimeout(() => window.location.href = '/dashboard.html', 1000);
  } catch (err) {
    showToast(err.message, 'error');
    if (err.errors) {
      Object.entries(err.errors).forEach(([f, m]) => {
        const el = document.getElementById(`reg-${f}`);
        if (el) el.textContent = m;
      });
    }
    btn.textContent = 'Create Account →'; btn.disabled = false;
  }
});

// ─── Admin Login Flow ─────────────────────────────────────────
let adminId = null;
document.getElementById('adminLoginBtn')?.addEventListener('click', async () => {
  const email = document.getElementById('adminEmail').value;
  const pass  = document.getElementById('adminPass').value;
  try {
    const res = await apiCall('admin/login', 'POST', { email, password: pass });
    adminId = res.data.admin_id;
    // Show OTP step
    document.getElementById('credStep').classList.remove('visible');
    document.getElementById('otpStep').classList.add('visible');
    showToast('OTP sent to your email.', 'info');
    startOtpCountdown(60);
  } catch (err) {
    showToast(err.message, 'error');
  }
});

document.getElementById('otpSubmit')?.addEventListener('click', async () => {
  const otp = [...document.querySelectorAll('.otp-input')].map(i => i.value).join('');
  if (otp.length < 6) { showToast('Please enter the full 6-digit code.', 'error'); return; }
  try {
    const res = await apiCall('admin/otp', 'POST', { admin_id: adminId, otp });
    Auth.save(res.data.token, res.data.admin, true);
    window.location.href = '/admin-dashboard.html';
  } catch (err) {
    showToast(err.message, 'error');
  }
});

// ─── Load Dashboard KPIs (admin) ─────────────────────────────
async function loadAdminDashboard() {
  if (!document.querySelector('.kpi-grid') || !Auth.isAdmin()) return;
  try {
    const res = await apiCall('admin/dashboard');
    const k   = res.data.kpis;

    document.getElementById('kpi-quotes')    && (document.getElementById('kpi-quotes').textContent    = k.quotes_today);
    document.getElementById('kpi-policies')  && (document.getElementById('kpi-policies').textContent  = k.active_policies.toLocaleString());
    document.getElementById('kpi-claims')    && (document.getElementById('kpi-claims').textContent    = k.pending_claims);
    document.getElementById('kpi-revenue')   && (document.getElementById('kpi-revenue').textContent   = `$${k.revenue_this_month.toLocaleString()}`);
  } catch (err) {
    console.warn('Dashboard load failed:', err.message);
  }
}
loadAdminDashboard();

// ─── Sign Out ─────────────────────────────────────────────────
document.querySelectorAll('[data-signout]').forEach(btn => {
  btn.addEventListener('click', () => {
    Auth.clear();
    window.location.href = '/login.html';
  });
});

// ─── Auth Guard ───────────────────────────────────────────────
const protectedPages  = ['dashboard.html','dashboard-quote.html','my-policies.html','my-claims.html','claims.html','my-profile.html'];
const adminPages      = ['admin-dashboard.html'];
const currentPage     = window.location.pathname.split('/').pop();
if (protectedPages.includes(currentPage)  && !Auth.isLoggedIn()) window.location.href = '/login.html?next=' + currentPage.replace('.html','');
if (adminPages.includes(currentPage) && !Auth.isAdmin())         window.location.href = '/admin-login.html';

// ─── Update Navbar if Logged In ───────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (Auth.isLoggedIn() && (!adminPages.includes(currentPage) && !currentPage.startsWith('admin'))) {
    document.querySelectorAll('.navbar .nav-right a, .mobile-menu a').forEach(a => {
      if (a.textContent.trim().toLowerCase() === 'sign in') {
        a.textContent = 'Dashboard';
        a.href = 'dashboard.html';
      }
    });
  }
});

// ─── Quote Modal Interceptor ──────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  if (Auth.isLoggedIn() && (!adminPages.includes(currentPage) && !currentPage.startsWith('admin'))) {
    document.querySelectorAll('a[href="index.html#quote"], a[href="#quote"]').forEach(a => {
      // Exclude actual index.html "Get a quote" buttons that are already handled nicely if we want to
      // But actually, even on index, a popup might be nice. 
      // The user said "do not move them to again front page".
      if (currentPage !== 'index.html' && currentPage !== '') {
        a.addEventListener('click', (e) => {
          e.preventDefault();
          openQuoteModal();
        });
      }
    });
  }
});

window.openQuoteModal = function() {
  let modal = document.getElementById('dashQuoteModal');
  if (!modal) {
    const html = `
    <style>
      .quote-modal-overlay {
        position: fixed; inset: 0; background: rgba(11, 30, 61, 0.7); backdrop-filter: blur(4px);
        z-index: 9999; display: flex; align-items: center; justify-content: center;
        opacity: 0; pointer-events: none; transition: opacity 0.2s ease;
      }
      .quote-modal-overlay.show { opacity: 1; pointer-events: auto; }
      .quote-modal-content {
        background: #fff; width: 100%; max-width: 500px; border-radius: var(--r-xl);
        padding: 32px; box-shadow: var(--shadow-xl); position: relative;
        transform: translateY(20px); transition: transform 0.2s ease;
      }
      .quote-modal-overlay.show .quote-modal-content { transform: translateY(0); }
    </style>
    <div class="quote-modal-overlay" id="dashQuoteModal">
      <div class="quote-modal-content quote-card" style="margin:auto; max-height:90vh; overflow-y:auto;">
        <button style="position:absolute;top:20px;right:20px;background:none;border:none;font-size:24px;cursor:pointer;color:#9CA3AF" onclick="document.getElementById('dashQuoteModal').classList.remove('show')">×</button>
        <div class="quote-card-title">Get a Quote</div>
        <div class="quote-card-rule"></div>
        <form class="quote-form" id="pqForm" novalidate>
            <div class="form-group">
              <label class="form-label" for="pqState">Where are you hiring your car from?</label>
              <div class="input-icon-wrap">
                <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <select id="pqState" name="state" class="form-input form-select" style="padding-left:44px" required>
                  <option value="">Select a state</option>
                  <option value="NSW">NSW — New South Wales</option>
                  <option value="VIC">VIC — Victoria</option>
                  <option value="QLD">QLD — Queensland</option>
                  <option value="WA">WA — Western Australia</option>
                  <option value="SA">SA — South Australia</option>
                  <option value="TAS">TAS — Tasmania</option>
                  <option value="ACT">ACT — Australian Capital Territory</option>
                  <option value="NT">NT — Northern Territory</option>
                  <option value="Overseas">Overseas</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="pqStartDate">Start Date</label>
                <input type="date" id="pqStartDate" name="start_date" class="form-input" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="pqStartTime">Time</label>
                <select id="pqStartTime" name="start_time" class="form-input form-select">
                  <option value="06:00">6:00 AM</option><option value="07:00">7:00 AM</option>
                  <option value="08:00">8:00 AM</option><option value="09:00" selected>9:00 AM</option>
                  <option value="10:00">10:00 AM</option><option value="11:00">11:00 AM</option>
                  <option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option>
                  <option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option>
                  <option value="16:00">4:00 PM</option><option value="17:00">5:00 PM</option>
                  <option value="18:00">6:00 PM</option>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label class="form-label" for="pqEndDate">End Date</label>
                <input type="date" id="pqEndDate" name="end_date" class="form-input" required>
              </div>
              <div class="form-group">
                <label class="form-label" for="pqEndTime">Time</label>
                <select id="pqEndTime" name="end_time" class="form-input form-select">
                  <option value="06:00">6:00 AM</option><option value="07:00">7:00 AM</option>
                  <option value="08:00">8:00 AM</option><option value="09:00" selected>9:00 AM</option>
                  <option value="10:00">10:00 AM</option><option value="11:00">11:00 AM</option>
                  <option value="12:00">12:00 PM</option><option value="13:00">1:00 PM</option>
                  <option value="14:00">2:00 PM</option><option value="15:00">3:00 PM</option>
                  <option value="16:00">4:00 PM</option><option value="17:00">5:00 PM</option>
                  <option value="18:00">6:00 PM</option>
                </select>
              </div>
            </div>

            <div id="pqDuration" class="duration-chip" style="display:none"></div>

            <button type="submit" class="btn-quote" id="pqSubmit">
              GET A QUOTE
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </button>
        </form>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    modal = document.getElementById('dashQuoteModal');

    const pqForm = document.getElementById('pqForm');
    const startEl = document.getElementById('pqStartDate');
    const endEl = document.getElementById('pqEndDate');
    const chip = document.getElementById('pqDuration');

    const today = new Date();
    const sevenDays = new Date(today); sevenDays.setDate(today.getDate() + 7);
    const fmt = d => d.toISOString().split('T')[0];
    startEl.value = fmt(today);
    endEl.value = fmt(sevenDays);
    startEl.min = fmt(today);

    function updateDuration() {
      const s = new Date(startEl.value), e = new Date(endEl.value);
      if (s && e && e > s) {
        const days = Math.ceil((e - s) / 86400000);
        const calendarIcon = '<svg style="position:relative;top:3px;margin-right:4px" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
        chip.innerHTML = `${calendarIcon} ${days} day${days !== 1 ? 's' : ''} rental period`;
        chip.style.display = 'block';
      } else {
        chip.style.display = 'none';
      }
    }
    startEl.addEventListener('change', () => { endEl.min = startEl.value; updateDuration(); });
    endEl.addEventListener('change', updateDuration);
    updateDuration();

    pqForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('pqSubmit');
      btn.disabled = true; btn.innerHTML = '<span style="opacity:.7">Calculating...</span>';
      
      const payload = {
        state: document.getElementById('pqState').value,
        start_date: startEl.value,
        start_time: document.getElementById('pqStartTime').value,
        end_date: endEl.value,
        end_time: document.getElementById('pqEndTime').value,
      };

      if (!payload.state) {
        showToast('Please select a state.', 'error');
        btn.disabled = false; btn.innerHTML = 'GET A QUOTE →'; return;
      }

      try {
        const res = await apiCall('quotes', 'POST', payload);
        sessionStorage.setItem('dsc_quote', JSON.stringify({
          quote_id: res.data.quote_id,
          state: payload.state,
          start_date: payload.start_date,
          start_time: payload.start_time,
          end_date: payload.end_date,
          end_time: payload.end_time,
          days: res.data.days,
        }));
        window.location.href = Auth.isLoggedIn() ? `/dashboard-quote.html?q=${res.data.quote_id}` : `/quote-result.html?q=${res.data.quote_id}`;
      } catch (err) {
        showToast(err.message, 'error');
        btn.disabled = false; btn.innerHTML = 'GET A QUOTE →';
      }
    });

    modal.addEventListener('click', (e) => {
      if (e.target === modal) modal.classList.remove('show');
    });
  }
  
  requestAnimationFrame(() => modal.classList.add('show'));
};
