// ============================================================
// DriveSafe Cover — My Policies Page (Real API Data)
// ============================================================

(function () {
  const container = document.getElementById('policiesContainer');
  if (!container) return;

  const user = Auth.user();
  document.querySelectorAll('.topbar-avatar').forEach(el => {
    const initials = (user?.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    el.textContent = initials;
  });

  let allPolicies = [];
  let currentFilter = 'all';

  async function loadPolicies() {
    container.innerHTML = '<div style="text-align:center;padding:48px;color:var(--text-muted)">Loading policies...</div>';

    try {
      const res = await apiCall('policies');
      allPolicies = res.data || [];
      updateFilterCounts();
      renderPolicies();
    } catch (err) {
      container.innerHTML = `<div style="text-align:center;padding:48px;color:var(--text-muted)">
        <p>Failed to load policies.</p>
        <p style="font-size:13px;color:var(--crimson)">${err.message}</p>
      </div>`;
    }
  }

  function updateFilterCounts() {
    const counts = { all: allPolicies.length, active: 0, expired: 0, cancelled: 0 };
    allPolicies.forEach(p => { if (counts[p.status] !== undefined) counts[p.status]++; });

    document.querySelectorAll('[data-filter]').forEach(btn => {
      const filter = btn.dataset.filter;
      const count = counts[filter] ?? 0;
      const label = filter === 'all' ? 'All' : filter.charAt(0).toUpperCase() + filter.slice(1);
      btn.textContent = `${label} (${count})`;
    });
  }

  function renderPolicies() {
    const filtered = currentFilter === 'all'
      ? allPolicies
      : allPolicies.filter(p => p.status === currentFilter);

    if (filtered.length === 0) {
      container.innerHTML = `<div style="text-align:center;padding:60px 24px;background:var(--bg-soft);border:2px dashed var(--border);border-radius:var(--r-xl)">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" style="margin-bottom:16px"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <h3 style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:800;color:var(--text-dark);margin-bottom:8px">No Policies Found</h3>
        <p style="color:var(--text-muted);margin-bottom:20px">${currentFilter === 'all' ? "You haven't purchased any policies yet." : `No ${currentFilter} policies.`}</p>
        <a href="index.html#quote" class="btn btn-primary">Get a Quote →</a>
      </div>`;
      return;
    }

    const fmt = d => new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });

    container.innerHTML = filtered.map(p => {
      const isActive = p.status === 'active';
      const isFuture = p.status === 'future';
      
      let badgeHtml = '';
      let borderColor = 'var(--border)';
      
      if (isActive) {
        badgeHtml = '<span class="badge badge-emerald"><span class="status-dot dot-green"></span> Active</span>';
        borderColor = 'var(--emerald)';
      } else if (isFuture) {
        badgeHtml = '<span class="badge badge-blue"><span class="status-dot dot-blue" style="background:#3B82F6"></span> Scheduled</span>';
        borderColor = 'var(--blue)';
      } else if (p.status === 'cancelled') {
        badgeHtml = '<span class="badge badge-crimson">Cancelled</span>';
        borderColor = 'var(--crimson)';
      } else {
        badgeHtml = '<span class="badge badge-grey">Expired</span>';
        borderColor = 'var(--border)';
      }

      return `
        <div class="card" style="border-left:4px solid ${borderColor};margin-bottom:16px;${p.status === 'expired' ? 'opacity:0.6' : ''}">
          <div class="flex-between" style="margin-bottom:16px;flex-wrap:wrap;gap:12px">
            <div>
              <div class="mono" style="font-size:18px;color:${(isActive || isFuture) ? 'var(--blue)' : 'var(--text-muted)'};margin-bottom:4px">${p.policy_number}</div>
              ${badgeHtml}
            </div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <button class="btn btn-outline btn-sm download-btn" data-policy="${encodeURIComponent(JSON.stringify(p))}" style="display:flex;align-items:center;gap:6px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Download PDF
              </button>
              ${isActive ? `<a href="claims.html" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:6px">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Make Claim</a>` : ''}
            </div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">State</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">${p.state}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Coverage</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">$${parseInt(p.coverage_amount).toLocaleString()}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Start</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">${fmt(p.start_date)}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">End</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">${fmt(p.end_date)}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Duration</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">${p.days} days</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Per Day</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700">$${parseFloat(p.price_per_day).toFixed(2)}</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Excess</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--emerald)">$0</div></div>
            <div><div style="font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--text-muted);font-weight:600">Total</div><div style="font-family:'Plus Jakarta Sans',sans-serif;font-weight:700;color:var(--crimson)">$${parseFloat(p.total_price).toFixed(2)}</div></div>
          </div>
          ${isActive ? `<div style="display:flex;gap:8px;flex-wrap:wrap">
            <span class="badge badge-emerald" style="font-size:11px">✓ Collision</span>
            <span class="badge badge-emerald" style="font-size:11px">✓ Theft</span>
            <span class="badge badge-emerald" style="font-size:11px">✓ Windscreen</span>
            <span class="badge badge-emerald" style="font-size:11px">✓ $0 Excess</span>
            <span class="badge badge-emerald" style="font-size:11px">✓ All Drivers</span>
          </div>` : ''}
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
      renderPolicies();
    });
  });

  container.addEventListener('click', (e) => {
    const btn = e.target.closest('.download-btn');
    if (btn) {
      e.preventDefault();
      try {
        const pObj = JSON.parse(decodeURIComponent(btn.dataset.policy));
        const oldText = btn.innerHTML;
        btn.innerText = "Generating PDF...";
        window.generatePolicyPDF(pObj, user).then(() => {
            btn.innerHTML = oldText;
        });
      } catch(err) {
        console.error("PDF generation error:", err);
      }
    }
  });

  loadPolicies();
})();
