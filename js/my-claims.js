// ============================================================
// DriveSafe Cover — My Claims Page (Real API Data)
// ============================================================

(function () {
  const container = document.getElementById('claimsContainer');
  if (!container) return;

  const user = Auth.user();
  document.querySelectorAll('.topbar-avatar').forEach(el => {
    const initials = (user?.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    el.textContent = initials;
  });

  let allClaims = [];
  let currentFilter = 'all';

  async function loadClaims() {
    container.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted)">Loading claims...</div>';

    try {
      const res = await apiCall('claims');
      allClaims = res.data || [];
      updateFilterCounts();
      renderClaims();
    } catch (err) {
      container.innerHTML = `<div style="text-align:center;padding:48px;color:var(--text-muted)">
        <p>Failed to load claims.</p>
        <p style="font-size:13px;color:var(--crimson)">${err.message}</p>
      </div>`;
    }
  }

  function updateFilterCounts() {
    const counts = { all: allClaims.length, submitted: 0, under_review: 0, approved: 0, paid: 0, denied: 0 };
    allClaims.forEach(c => { if (counts[c.status] !== undefined) counts[c.status]++; });

    document.querySelectorAll('[data-filter]').forEach(btn => {
      const filter = btn.dataset.filter;
      const count = counts[filter] ?? 0;
      const label = filter === 'all' ? 'All' : filter === 'under_review' ? 'Under Review' : filter.charAt(0).toUpperCase() + filter.slice(1);
      btn.textContent = `${label} (${count})`;
    });
  }

  function renderClaims() {
    const filtered = currentFilter === 'all'
      ? allClaims
      : allClaims.filter(c => c.status === currentFilter);

    if (filtered.length === 0) {
      container.innerHTML = `<div style="text-align:center;padding:60px 24px;background:var(--bg-soft);border:2px dashed var(--border);border-radius:var(--r-xl)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin-bottom:16px"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--text-dark);margin-bottom:8px">No Claims Found</h3>
        <p style="color:var(--text-muted);margin-bottom:20px">${currentFilter === 'all' ? "You haven't submitted any claims yet." : `No ${currentFilter.replace('_', ' ')} claims.`}</p>
        <a href="claims.html" class="btn btn-primary">Submit a Claim →</a>
      </div>`;
      return;
    }

    const fmt = d => new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });

    const statusConfig = {
      submitted:    { badge: 'badge-grey',    label: 'Submitted',    color: 'var(--text-muted)', dots: [1, 0, 0, 0] },
      under_review: { badge: 'badge-amber',   label: 'Under Review', color: 'var(--amber)',      dots: [1, 2, 0, 0] },
      approved:     { badge: 'badge-emerald',  label: 'Approved',     color: 'var(--emerald)',    dots: [1, 1, 1, 0] },
      denied:       { badge: 'badge-crimson',  label: 'Denied',       color: 'var(--crimson)',    dots: [1, 1, 3, 0] },
      paid:         { badge: 'badge-blue',     label: 'Paid',         color: 'var(--blue)',       dots: [1, 1, 1, 1] },
    };

    container.innerHTML = filtered.map(c => {
      const cfg = statusConfig[c.status] || statusConfig.submitted;
      let damageStr = '—';
      try { damageStr = JSON.parse(c.damage_types || '[]').join(' + '); } catch (e) { damageStr = c.damage_types || '—'; }

      // Timeline dots: 0=pending, 1=done, 2=active, 3=denied
      const dotHtml = cfg.dots.map(state => {
        if (state === 1) return '<div class="timeline-dot done">✓</div>';
        if (state === 2) return '<div class="timeline-dot active">●</div>';
        if (state === 3) return '<div class="timeline-dot" style="background:var(--crimson);border-color:var(--crimson);color:#fff">✕</div>';
        return '<div class="timeline-dot"></div>';
      }).join('<div class="timeline-line' + (cfg.dots.filter(d => d > 0).length > 1 ? '' : '') + '"></div>');

      // Calculate days since submission
      const daysSince = Math.floor((Date.now() - new Date(c.created_at).getTime()) / 86400000);
      const timeLabel = daysSince === 0 ? 'Submitted today' : daysSince === 1 ? 'Submitted yesterday' : `Submitted ${daysSince} days ago`;

      const amountLabel = c.status === 'paid' || c.status === 'approved'
        ? `<p style="color:var(--emerald);font-weight:700">$${parseFloat(c.amount_paid || c.amount_claimed).toFixed(2)}</p>`
        : `<p style="color:var(--crimson)">$${parseFloat(c.amount_claimed).toFixed(2)}</p>`;

      const amountHeader = c.status === 'paid' || c.status === 'approved' ? 'Payout' : 'Amount Claimed';

      return `
        <div class="claim-card ${c.status}" style="margin-bottom:16px">
          <div class="claim-card-header">
            <span class="claim-ref">${c.claim_number}</span>
            <span class="badge ${cfg.badge}"><span class="status-dot dot-${cfg.badge.replace('badge-', '')}"></span> ${cfg.label}</span>
          </div>
          <div class="claim-grid">
            <div class="claim-item"><label>Submitted</label><p>${fmt(c.created_at)}</p></div>
            <div class="claim-item"><label>Policy</label><p class="mono" style="color:var(--blue);font-size:12px">${c.policy_number || '—'}</p></div>
            <div class="claim-item"><label>Damage Type</label><p>${damageStr}</p></div>
            <div class="claim-item"><label>${amountHeader}</label>${amountLabel}</div>
          </div>
          <div style="display:flex;align-items:center;gap:0;margin-top:16px">
            ${dotHtml}
          </div>
          <div style="display:flex;gap:12px;justify-content:space-between;margin-top:16px;align-items:center">
            <p style="font-size:12px;color:${cfg.color};font-weight:600">${timeLabel}</p>
          </div>
        </div>
      `;
    }).join('');
  }

  // Filter buttons
  document.querySelectorAll('[data-filter]').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
      });
      btn.classList.remove('btn-outline');
      btn.classList.add('btn-primary');
      currentFilter = btn.dataset.filter;
      renderClaims();
    });
  });

  loadClaims();
})();
