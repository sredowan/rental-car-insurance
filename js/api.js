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

  // Prefer trailing slash to prevent LiteSpeed 301 redirect (POST -> GET),
  // but retry without it if a host routes extensionless API paths differently.
  const normalizedEndpoint = endpoint.endsWith('/') || endpoint.includes('?') ? endpoint : endpoint + '/';
  const alternateEndpoint = !endpoint.includes('?')
    ? (normalizedEndpoint.endsWith('/') ? normalizedEndpoint.slice(0, -1) : normalizedEndpoint + '/')
    : null;
  const routeOnly = endpoint.split('?')[0].replace(/^\/+|\/+$/g, '');
  const queryOnly = endpoint.includes('?') ? endpoint.slice(endpoint.indexOf('?') + 1) : '';
  const indexFallback = `index.php?route=${encodeURIComponent(routeOnly)}${queryOnly ? `&${queryOnly}` : ''}`;

  async function requestApi(path) {
    const res = await fetch(`${API_BASE}/${path}`, config);
    const text = await res.text();
    let data = null;

    if (text) {
      try {
        data = JSON.parse(text);
      } catch (parseErr) {
        const clean = text.replace(/<style[\s\S]*?<\/style>/gi, ' ')
          .replace(/<script[\s\S]*?<\/script>/gi, ' ')
          .replace(/<[^>]*>/g, ' ')
          .replace(/\s+/g, ' ')
          .trim();
        return {
          res,
          data: null,
          invalidJsonMessage: res.status === 404
            ? `API endpoint not found: /api/${path}`
            : (clean ? clean.slice(0, 180) : `Server returned an invalid response (${res.status})`),
        };
      }
    }

    return { res, data, invalidJsonMessage: null };
  }

  try {
    let { res, data, invalidJsonMessage } = await requestApi(normalizedEndpoint);

    if (res.status === 404 && alternateEndpoint) {
      const retry = await requestApi(alternateEndpoint);
      if (retry.res.status !== 404 || retry.data) {
        res = retry.res;
        data = retry.data;
        invalidJsonMessage = retry.invalidJsonMessage;
      }
    }

    if (res.status === 404 && routeOnly) {
      const retry = await requestApi(indexFallback);
      if (retry.res.status !== 404 || retry.data) {
        res = retry.res;
        data = retry.data;
        invalidJsonMessage = retry.invalidJsonMessage;
      }
    }

    if (invalidJsonMessage) {
      throw { status: res.status || 0, message: invalidJsonMessage };
    }

    if (!res.ok) {
      throw {
        status: res.status,
        message: data?.message || `Request failed (${res.status})`,
        errors: data?.errors,
      };
    }

    if (!data) {
      throw { status: res.status, message: 'Server returned an empty response.' };
    }

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

    const vehicleEl = document.getElementById('quoteVehicleType');
    const payload = {
      state:        document.getElementById('quoteState').value,
      start_date:   startEl.value,
      start_time:   document.getElementById('quoteStartTime').value,
      end_date:     endEl.value,
      end_time:     document.getElementById('quoteEndTime').value,
      vehicle_type: vehicleEl ? vehicleEl.value : 'car',
    };

    if (!payload.state) {
      showToast('Please select a state.', 'error');
      btn.disabled = false; btn.innerHTML = 'GET A QUOTE →'; return;
    }

    try {
      const res = await apiCall('quotes', 'POST', payload);
      // Save minimal context (no price)
      sessionStorage.setItem('dsc_quote', JSON.stringify({
        quote_id:     res.data.quote_id,
        state:        payload.state,
        vehicle_type: payload.vehicle_type,
        start_date:   payload.start_date,
        start_time:   payload.start_time,
        end_date:     payload.end_date,
        end_time:     payload.end_time,
        days:         res.data.days,
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

  let selectedPlan = 'essential';
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
    const fmt = d => new Date(d).toLocaleDateString('en-AU', { day:'numeric', month:'short', year:'numeric' });
    document.getElementById('result-state') && (document.getElementById('result-state').textContent = q.state);
    document.getElementById('result-start') && (document.getElementById('result-start').textContent = fmt(q.start_date));
    document.getElementById('result-end')   && (document.getElementById('result-end').textContent   = fmt(q.end_date));
    document.getElementById('result-days')  && (document.getElementById('result-days').textContent  = `${q.days} days`);

    // Show vehicle type
    const vt = q.vehicle_type || 'car';
    const vtData = CoveragePricing.vehicleSurcharges[vt] || CoveragePricing.vehicleSurcharges.car;

    // Vehicle icon + label
    const vtIconEl = document.getElementById('result-vehicle-icon');
    const vtLabelEl = document.getElementById('result-vehicle-label');
    if (vtIconEl) vtIconEl.textContent = vtData.icon;
    if (vtLabelEl) vtLabelEl.textContent = vtData.label;

    // Vehicle coverage rows
    const vtNotesEl = document.getElementById('vehicleCoverageNotes');
    if (vtNotesEl) {
      vtNotesEl.innerHTML = `
        <div class="vt-row">
          <span class="vt-row-tag covered">✓ Covered</span>
          <span class="vt-row-text">${vtData.covered}</span>
        </div>
        <div class="vt-row">
          <span class="vt-row-tag not-covered">✗ Not covered</span>
          <span class="vt-row-text">${vtData.exclusionBrief || vtData.notCovered}</span>
          <button class="excl-details-btn" id="openExclBtn" type="button" style="margin-left:auto">
            Details <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M9 18l6-6-6-6"/></svg>
          </button>
        </div>
      `;
      // Re-bind the Details button since it was just created
      document.getElementById('openExclBtn')?.addEventListener('click', openExclModal);
    }

    // Render plan cards (compact v2)
    const container = document.getElementById('coverageTiers');
    if (!container) return;
    container.innerHTML = '';
    q.options.forEach(opt => {
      const isDefault = opt.plan === 'essential';
      const isBest    = opt.plan === 'premium';
      const isMax     = opt.plan === 'ultimate';
      const card = document.createElement('div');
      card.className = `plan-card-v2${isDefault ? ' selected' : ''}`;
      card.dataset.plan       = opt.plan;
      card.dataset.pricePerDay = opt.price_per_day;
      card.dataset.total       = opt.total_price;

      card.innerHTML = `
        ${isBest ? '<span class="plan-badge-top plan-badge-best">★ Best Value</span>' : ''}
        ${isMax ? '<span class="plan-badge-top plan-badge-max">Max Protection</span>' : ''}
        <span class="plan-name">${opt.plan_label}</span>
        <span class="plan-price">$${opt.price_per_day}<small>/day</small></span>
        <span class="plan-tag">${opt.badge}</span>
        <span class="plan-total-mini">$${opt.total_price} total</span>
        <span class="plan-cta">${isDefault ? 'Selected' : 'Select'}</span>
      `;
      card.addEventListener('click', () => selectPlan(card, opt));
      container.appendChild(card);
    });

    // Default selection
    const defaultOpt = q.options.find(o => o.plan === 'essential');
    if (defaultOpt) {
      updateBreakdown(defaultOpt, q.days);
      updateIncludedFeatures(defaultOpt);
      const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
      if (!saved.plan) {
        sessionStorage.setItem('dsc_quote', JSON.stringify({
          ...saved,
          plan:         defaultOpt.plan,
          planLabel:    defaultOpt.plan_label,
          vehicle_type: vt,
          coverage:     defaultOpt.coverage_amount,
          pricePerDay:  defaultOpt.price_per_day,
          totalPrice:   defaultOpt.total_price,
        }));
      }
    }
  }

  function selectPlan(card, opt) {
    document.querySelectorAll('.plan-card-v2').forEach(c => {
      c.classList.remove('selected');
      const cta = c.querySelector('.plan-cta');
      if (cta) cta.textContent = 'Select';
    });
    card.classList.add('selected');
    const cta = card.querySelector('.plan-cta');
    if (cta) cta.textContent = 'Selected';
    selectedPlan = opt.plan;
    updateBreakdown(opt, quoteData.days);
    updateIncludedFeatures(opt);
    const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
    sessionStorage.setItem('dsc_quote', JSON.stringify({
      ...saved,
      plan:         opt.plan,
      planLabel:    opt.plan_label,
      vehicle_type: quoteData.vehicle_type || 'car',
      coverage:     opt.coverage_amount,
      pricePerDay:  opt.price_per_day,
      totalPrice:   opt.total_price,
    }));
  }

  function updateBreakdown(opt, days) {
    const animate = el => { el.classList.remove('price-update'); void el.offsetWidth; el.classList.add('price-update'); };
    const set = (id, v) => { const el = document.getElementById(id); if (el) { el.textContent = v; animate(el); } };
    set('result-plan',          opt.plan_label);
    set('result-price-per-day', `$${opt.price_per_day}`);
    set('result-duration',      `${days} days`);
    set('result-total',         `$${opt.total_price}`);
    // Mobile sticky bar
    set('mbb-total', `$${opt.total_price}`);
    set('mbb-plan', `${opt.plan_label} · ${days} days`);
  }

  function updateIncludedFeatures(opt) {
    const listEl = document.getElementById('includedFeaturesList');
    if (!listEl) return;
    const checkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="3" style="flex-shrink:0"><polyline points="20 6 9 17 4 12"></polyline></svg>';
    const crossSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#D1D5DB" stroke-width="2" style="flex-shrink:0"><path d="M18 6L6 18M6 6l12 12"></path></svg>';

    // All possible features from Ultimate plan
    const allFeatures = CoveragePricing.plans.ultimate.features;
    const included = new Set(opt.features);

    listEl.innerHTML = allFeatures.map(f => {
      const isIncluded = included.has(f);
      return `<div class="feat-item${isIncluded ? '' : ' excluded'}">
        ${isIncluded ? checkSvg : crossSvg} ${f}
      </div>`;
    }).join('');
  }

  // Checkout buttons (desktop + mobile)
  document.getElementById('checkoutBtn')?.addEventListener('click', () => {
    window.location.href = '/checkout.html';
  });
  document.getElementById('mbbCheckoutBtn')?.addEventListener('click', () => {
    window.location.href = '/checkout.html';
  });

  // ── Compare Plans Modal ──────────────────────────────────
  const compareOverlay = document.getElementById('compareOverlay');
  const compareTable   = document.getElementById('compareTable');

  function buildCompareTable() {
    if (!compareTable || !quoteData?.options) return;
    const plans = quoteData.options; // [essential, premium, ultimate]
    const allFeatures = CoveragePricing.plans.ultimate.features;
    const checkMark = '<span class="cmp-check">✓</span>';
    const crossMark = '<span class="cmp-cross">✗</span>';

    // Build feature sets for quick lookup
    const featureSets = plans.map(p => new Set(p.features));

    // Header row
    let html = '<thead><tr><th>Feature</th>';
    plans.forEach((p, i) => {
      const isBest = p.plan === 'premium';
      html += `<th class="${isBest ? 'cmp-best' : ''}">${p.plan_label}</th>`;
    });
    html += '</tr></thead><tbody>';

    // Feature rows
    allFeatures.forEach(feat => {
      html += '<tr>';
      html += `<td>${feat}</td>`;
      plans.forEach((p, i) => {
        const has = featureSets[i].has(feat);
        const isBest = p.plan === 'premium';
        html += `<td class="${isBest ? 'cmp-col-best' : ''}">${has ? checkMark : crossMark}</td>`;
      });
      html += '</tr>';
    });

    // Price row
    html += '<tr class="cmp-price-row"><td>Per day</td>';
    plans.forEach(p => {
      html += `<td>$${p.price_per_day}</td>`;
    });
    html += '</tr>';

    // Total row
    html += '<tr class="cmp-price-row"><td>Total</td>';
    plans.forEach(p => {
      html += `<td>$${p.total_price}</td>`;
    });
    html += '</tr>';

    html += '</tbody>';
    compareTable.innerHTML = html;
  }

  document.getElementById('openCompareBtn')?.addEventListener('click', () => {
    buildCompareTable();
    compareOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  });

  document.getElementById('closeCompareBtn')?.addEventListener('click', () => {
    compareOverlay?.classList.remove('open');
    document.body.style.overflow = '';
  });

  compareOverlay?.addEventListener('click', (e) => {
    if (e.target === compareOverlay) {
      compareOverlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });

  // ── Exclusion Details Modal ──────────────────────────────
  const exclOverlay = document.getElementById('exclOverlay');
  const exclModalList = document.getElementById('exclModalList');
  const exclModalTitle = document.getElementById('exclModalTitle');

  function openExclModal() {
    const vt = quoteData?.vehicle_type || 'car';
    const vtData = CoveragePricing.vehicleSurcharges[vt] || CoveragePricing.vehicleSurcharges.car;
    if (exclModalTitle) exclModalTitle.textContent = `What's not covered — ${vtData.label}`;
    if (exclModalList) {
      const crossIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5" style="flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
      exclModalList.innerHTML = (vtData.exclusions || []).map(item =>
        `<div class="excl-modal-item">${crossIcon}<span>${item}</span></div>`
      ).join('');
    }
    exclOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  document.getElementById('closeExclBtn')?.addEventListener('click', () => {
    exclOverlay?.classList.remove('open');
    document.body.style.overflow = '';
  });

  exclOverlay?.addEventListener('click', (e) => {
    if (e.target === exclOverlay) {
      exclOverlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });

  // ── Full Protection Details Modal ──────────────────────────
  const fpOverlay = document.getElementById('fpOverlay');

  // Full protection data — generic, safe content
  const fpData = {
    covered: [
      { label: 'Excess Charges', desc: 'The full excess amount you are liable for under your rental agreement' },
      { label: 'Body Damage', desc: 'Damage to the bodywork, including roof and undercarriage' },
      { label: 'Windscreen & Glass', desc: 'All glass including windscreens, windows, and sunroofs' },
      { label: 'Tyres & Wheels', desc: 'Including punctures, repairs, and full wheel replacement' },
      { label: 'Lights & Mirrors', desc: 'Headlights, taillights, indicators, and side mirrors' },
      { label: 'Theft', desc: 'The excess amount if the rental vehicle is stolen' },
      { label: 'Admin & Processing Fees', desc: 'All admin or claim handling fees charged by the rental company' },
      { label: 'Towing & Recovery', desc: 'Recovery costs for accidents or breakdowns' },
      { label: 'Key Replacement', desc: 'Costs for lost, stolen, or damaged rental car keys' },
      { label: 'Misfuelling', desc: 'Costs to flush the engine and fuel system if the wrong fuel is used' }
    ],
    notCovered: [
      'You breach any terms of your rental agreement (e.g. unauthorized drivers)',
      'Driving on unsealed roads (unless the vehicle is a 4x4) or in prohibited areas',
      'Damage caused by extreme negligence, driving under the influence, or intentional damage',
      'Interior damage not caused by a collision (e.g. cigarette burns, spills)',
      'Theft or damage to personal items inside the vehicle',
      'Mechanical failure not related to an accident'
    ],
    conditions: [
      'You must have at least the basic CDW/LDW provided by the rental company',
      'Only drivers listed on the rental agreement are covered',
      'You must notify us of any claim as soon as possible after the incident',
      'Coverage applies up to the maximum benefit of $100,000 per claim'
    ],
    claims: [
      { step: '1', label: 'Pay the rental company', desc: 'Pay the damage charges to the rental company at the desk' },
      { step: '2', label: 'Gather documents', desc: 'Rental agreement, damage report, and final invoice showing charges' },
      { step: '3', label: 'Submit online', desc: 'Log in to your account and upload documents to receive your refund' }
    ]
  };

  function buildFpModal() {
    const checkSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
    const crossSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>';
    const infoSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';
    const stepSvg = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="2"><path d="M9 18l6-6-6-6"/></svg>';

    const coveredEl = document.getElementById('fpCoveredList');
    if (coveredEl) {
      coveredEl.innerHTML = fpData.covered.map(c =>
        `<div class="fp-item">${checkSvg}<span><strong>${c.label}</strong> — ${c.desc}</span></div>`
      ).join('');
    }

    const notCoveredEl = document.getElementById('fpNotCoveredList');
    if (notCoveredEl) {
      notCoveredEl.innerHTML = fpData.notCovered.map(t =>
        `<div class="fp-item">${crossSvg}<span>${t}</span></div>`
      ).join('');
    }

    const conditionsEl = document.getElementById('fpConditionsList');
    if (conditionsEl) {
      conditionsEl.innerHTML = fpData.conditions.map(t =>
        `<div class="fp-item">${infoSvg}<span>${t}</span></div>`
      ).join('');
    }

    const claimsEl = document.getElementById('fpClaimsList');
    if (claimsEl) {
      claimsEl.innerHTML = fpData.claims.map(c =>
        `<div class="fp-item">${stepSvg}<span><strong>Step ${c.step}: ${c.label}</strong> — ${c.desc}</span></div>`
      ).join('');
    }
  }

  document.getElementById('openFpBtn')?.addEventListener('click', () => {
    buildFpModal();
    fpOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
  });

  document.getElementById('closeFpBtn')?.addEventListener('click', () => {
    fpOverlay?.classList.remove('open');
    document.body.style.overflow = '';
  });

  fpOverlay?.addEventListener('click', (e) => {
    if (e.target === fpOverlay) {
      fpOverlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  });

  // ── Editable Trip Dates ──────────────────────────────────
  const qrEditBtn    = document.getElementById('qrTripEditBtn');
  const qrEditForm   = document.getElementById('qrTripEditForm');
  const qrEditStart  = document.getElementById('qrEditStart');
  const qrEditEnd    = document.getElementById('qrEditEnd');
  const qrSaveBtn    = document.getElementById('qrEditSave');
  const qrCancelBtn  = document.getElementById('qrEditCancel');

  if (qrEditBtn && qrEditForm) {
    qrEditBtn.addEventListener('click', () => {
      qrEditForm.classList.toggle('show');
      // Pre-fill from current quote data
      const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
      if (saved.start_date) qrEditStart.value = saved.start_date;
      if (saved.end_date) qrEditEnd.value = saved.end_date;
      qrEditStart.min = new Date().toISOString().split('T')[0];
    });

    qrCancelBtn.addEventListener('click', () => {
      qrEditForm.classList.remove('show');
    });

    qrEditStart.addEventListener('change', () => {
      qrEditEnd.min = qrEditStart.value;
      if (qrEditEnd.value && qrEditEnd.value <= qrEditStart.value) {
        const d = new Date(qrEditStart.value);
        d.setDate(d.getDate() + 1);
        qrEditEnd.value = d.toISOString().split('T')[0];
      }
    });

    qrSaveBtn.addEventListener('click', async () => {
      const newStart = qrEditStart.value;
      const newEnd   = qrEditEnd.value;
      if (!newStart || !newEnd || newEnd <= newStart) {
        showToast('Please select valid dates.', 'error');
        return;
      }

      qrSaveBtn.textContent = 'Updating...';
      qrSaveBtn.disabled = true;

      try {
        const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
        // Step 1: Create new quote with updated dates
        const res = await apiCall('quotes', 'POST', {
          state:        saved.state || quoteData?.state || 'NSW',
          start_date:   newStart,
          start_time:   saved.start_time || '09:00',
          end_date:     newEnd,
          end_time:     saved.end_time || '09:00',
          vehicle_type: saved.vehicle_type || quoteData?.vehicle_type || 'car',
        });

        const newQuoteId = res.data.quote_id;

        // Step 2: GET the quote to fetch pricing
        const priceRes = await apiCall(`quotes?id=${newQuoteId}`);

        // Update session
        const updatedQuote = {
          ...saved,
          quote_id:    newQuoteId,
          start_date:  newStart,
          end_date:    newEnd,
          days:        priceRes.data.days,
        };
        sessionStorage.setItem('dsc_quote', JSON.stringify(updatedQuote));

        // Update quoteData with FULL pricing and re-render
        quoteData = priceRes.data;
        renderQuoteResult(quoteData);
        qrEditForm.classList.remove('show');
        showToast('Dates updated! Prices refreshed.', 'success');

        // Update URL with new quote ID
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('q', newQuoteId);
        window.history.replaceState({}, '', newUrl);
      } catch (err) {
        showToast(err.message || 'Failed to update dates.', 'error');
      }

      qrSaveBtn.textContent = 'Update Dates';
      qrSaveBtn.disabled = false;
    });
  }

  // ── Editable Trip Dates (dashboard-quote page) ──────────
  const dqEditBtn    = document.getElementById('dqTripEditBtn');
  const dqEditForm   = document.getElementById('dqTripEditForm');
  const dqEditStart  = document.getElementById('dqEditStart');
  const dqEditEnd    = document.getElementById('dqEditEnd');
  const dqSaveBtn    = document.getElementById('dqEditSave');
  const dqCancelBtn  = document.getElementById('dqEditCancel');

  if (dqEditBtn && dqEditForm) {
    dqEditBtn.addEventListener('click', () => {
      const isOpen = dqEditForm.style.display === 'block';
      dqEditForm.style.display = isOpen ? 'none' : 'block';
      const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
      if (saved.start_date) dqEditStart.value = saved.start_date;
      if (saved.end_date) dqEditEnd.value = saved.end_date;
      dqEditStart.min = new Date().toISOString().split('T')[0];
    });

    dqCancelBtn.addEventListener('click', () => {
      dqEditForm.style.display = 'none';
    });

    dqEditStart.addEventListener('change', () => {
      dqEditEnd.min = dqEditStart.value;
      if (dqEditEnd.value && dqEditEnd.value <= dqEditStart.value) {
        const d = new Date(dqEditStart.value);
        d.setDate(d.getDate() + 1);
        dqEditEnd.value = d.toISOString().split('T')[0];
      }
    });

    dqSaveBtn.addEventListener('click', async () => {
      const newStart = dqEditStart.value;
      const newEnd   = dqEditEnd.value;
      if (!newStart || !newEnd || newEnd <= newStart) {
        showToast('Please select valid dates.', 'error');
        return;
      }

      dqSaveBtn.textContent = 'Updating...';
      dqSaveBtn.disabled = true;

      try {
        const saved = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
        // Step 1: Create new quote with updated dates
        const res = await apiCall('quotes', 'POST', {
          state:        saved.state || quoteData?.state || 'NSW',
          start_date:   newStart,
          start_time:   saved.start_time || '09:00',
          end_date:     newEnd,
          end_time:     saved.end_time || '09:00',
          vehicle_type: saved.vehicle_type || quoteData?.vehicle_type || 'car',
        });

        const newQuoteId = res.data.quote_id;

        // Step 2: GET the quote to fetch pricing
        const priceRes = await apiCall(`quotes?id=${newQuoteId}`);

        const updatedQuote = {
          ...saved,
          quote_id:    newQuoteId,
          start_date:  newStart,
          end_date:    newEnd,
          days:        priceRes.data.days,
        };
        sessionStorage.setItem('dsc_quote', JSON.stringify(updatedQuote));

        // Update with FULL pricing and re-render
        quoteData = priceRes.data;
        renderQuoteResult(quoteData);
        dqEditForm.style.display = 'none';
        showToast('Dates updated! Prices refreshed.', 'success');
      } catch (err) {
        showToast(err.message || 'Failed to update dates.', 'error');
      }

      dqSaveBtn.textContent = 'Update Dates';
      dqSaveBtn.disabled = false;
    });
  }

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
const adminPages      = ['admin-dashboard.html','admin-quotes.html','admin-policies.html','admin-claims.html','admin-customers.html','admin-revenue.html','admin-mailbox.html','admin-settings.html','admin-audit.html'];
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
            <div class="form-row quote-hire-row">
            <div class="form-group">
              <label class="form-label" for="pqState">Where are you hiring your car from?</label>
              <div class="input-icon-wrap">
                <svg class="input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <select id="pqState" name="state" class="form-input form-select" style="padding-left:44px" required>
                  <option value="NSW" selected>NSW</option>
                  <option value="VIC">VIC</option>
                  <option value="QLD">QLD</option>
                  <option value="WA">WA</option>
                  <option value="SA">SA</option>
                  <option value="TAS">TAS</option>
                  <option value="ACT">ACT</option>
                  <option value="NT">NT</option>
                  <option value="Overseas">Overseas</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label class="form-label" for="pqVehicleType">I'm renting a:</label>
              <select id="pqVehicleType" name="vehicle_type" class="form-input form-select">
                <option value="car">🚗 Car</option>
                <option value="campervan">🚐 Campervan</option>
                <option value="motorhome">🏠 Motorhome / RV</option>
                <option value="bus">🚌 Bus / Small Coach</option>
                <option value="4x4">🚙 4x4</option>
              </select>
            </div>
            </div>

            <div class="form-row date-time-row">
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

            <div class="form-row date-time-row">
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
        vehicle_type: document.getElementById('pqVehicleType').value,
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
          vehicle_type: payload.vehicle_type,
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
