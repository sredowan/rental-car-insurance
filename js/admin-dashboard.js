(function () {
  const admin = Auth.admin();
  if (!admin) {
    window.location.href = 'admin-login.html';
    return;
  }

  const fmtCurrency = val => new Intl.NumberFormat('en-AU', { style: 'currency', currency: 'AUD' }).format(val || 0);

  async function loadDashboard() {
    try {
      const res = await apiCall('admin/dashboard');
      const data = res.data;
      if (!data) return;

      renderKPIs(data.kpis);
      renderTiers(data.coverage_tiers);
      renderClaimsDonut(data.claims_by_status);
      renderRecentQuotes(data.recent_quotes);
      renderPendingClaims(data.pending_claims_list, data.kpis.pending_claims);
      
    } catch (err) {
      console.error('Failed to load dashboard:', err);
    }
  }

  function renderKPIs(kpis) {
    // Quotes Today
    document.getElementById('kpi-quotes_today').textContent = kpis.quotes_today;
    const diff_quotes = kpis.quotes_today - kpis.quotes_yesterday;
    const elQTrend = document.getElementById('kpi-quotes_trend');
    if (diff_quotes > 0) { elQTrend.className = 'kpi-trend up'; elQTrend.textContent = `↑ ${diff_quotes} more than yesterday`; }
    else if (diff_quotes < 0) { elQTrend.className = 'kpi-trend down'; elQTrend.textContent = `↓ ${Math.abs(diff_quotes)} down from yesterday`; elQTrend.style.color = 'var(--crimson)'; }
    else { elQTrend.className = 'kpi-trend'; elQTrend.textContent = 'Same as yesterday'; elQTrend.style.color = 'var(--text-muted)'; }

    // Active Policies
    document.getElementById('kpi-active_policies').textContent = kpis.active_policies.toLocaleString();
    const elPTrend = document.getElementById('kpi-policies_trend');
    if (kpis.policies_last_month > 0) { elPTrend.className = 'kpi-trend up'; elPTrend.textContent = `↑ ${kpis.policies_last_month} new this month`; }
    else { elPTrend.className = 'kpi-trend'; elPTrend.textContent = 'Steady'; elPTrend.style.color = 'var(--text-muted)'; }

    // Pending Claims
    document.getElementById('kpi-pending_claims').textContent = kpis.pending_claims;
    const elCTrend = document.getElementById('kpi-claims_trend');
    if (kpis.new_claims_today > 0) { elCTrend.className = 'kpi-trend warn'; elCTrend.textContent = `${kpis.new_claims_today} new today`; }
    else { elCTrend.className = 'kpi-trend'; elCTrend.textContent = 'No new claims today'; elCTrend.style.color = 'var(--text-muted)'; }

    // Revenue
    document.getElementById('kpi-revenue_this_month').textContent = fmtCurrency(kpis.revenue_this_month);
    const elRTrend = document.getElementById('kpi-revenue_trend');
    if (kpis.revenue_change_pct > 0) { elRTrend.className = 'kpi-trend up'; elRTrend.textContent = `↑ ${kpis.revenue_change_pct}% vs last month`; }
    else if (kpis.revenue_change_pct < 0) { elRTrend.className = 'kpi-trend down'; elRTrend.textContent = `↓ ${Math.abs(kpis.revenue_change_pct)}% vs last month`; elRTrend.style.color = 'var(--crimson)'; }
    else { elRTrend.className = 'kpi-trend'; elRTrend.textContent = 'Flat vs last month'; elRTrend.style.color = 'var(--text-muted)'; }
  }

  function renderTiers(tiers) {
    if (!tiers || tiers.length === 0) {
      document.getElementById('dash-tier-bars').innerHTML = '<div style="margin:auto;color:var(--text-muted)">No coverage data yet</div>';
      return;
    }

    const maxCount = Math.max(...tiers.map(t => t.count)) || 1;
    let mostChosen = { tier: '', count: -1 };
    let bestRev = { tier: '', rev: -1 };
    let baseCount = tiers.find(t => t.coverage_amount === 4000)?.count || 0;
    let upgradeCount = tiers.filter(t => t.coverage_amount > 4000).reduce((a, t) => a + t.count, 0);

    const colors = [
      'var(--primary)',
      'linear-gradient(90deg,#7C3AED,#A855F7)',
      'linear-gradient(90deg,var(--amber),#FCD34D)',
      'linear-gradient(90deg,var(--emerald),#34D399)',
      'linear-gradient(90deg,var(--blue),#60A5FA)'
    ];

    const html = tiers.map((t, idx) => {
      if (t.count > mostChosen.count) { mostChosen = { tier: fmtCurrency(t.coverage_amount).replace('.00',''), count: t.count }; }
      if (t.revenue > bestRev.rev) { bestRev = { tier: fmtCurrency(t.coverage_amount).replace('.00',''), rev: t.revenue }; }

      const w = Math.round((t.count / maxCount) * 100);
      const isBest = t.revenue === Math.max(...tiers.map(x => x.revenue));
      const lblColor = isBest ? 'color:var(--amber);font-weight:800' : 'color:var(--text-dark)';
      const bg = colors[idx % colors.length];

      return `
        <div class="chart-bar-item">
          <span class="chart-bar-label" style="${lblColor}">${fmtCurrency(t.coverage_amount).replace('.00','')}</span>
          <div class="chart-bar-track">
            <div class="chart-bar-fill" style="width:${Math.max(w, 2)}%;background:${bg}"></div>
          </div>
          <span class="chart-bar-val">${t.count}</span>
        </div>
      `;
    }).join('');

    document.getElementById('dash-tier-bars').innerHTML = html;

    const upgPct = (baseCount + upgradeCount) > 0 ? Math.round(upgradeCount / (baseCount + upgradeCount) * 100) : 0;
    document.getElementById('dash-tier-badges').innerHTML = `
      <span class="badge badge-primary" style="font-size:11px">Most chosen: ${mostChosen.tier || 'None'}</span>
      <span class="badge badge-amber" style="font-size:11px">Best revenue: ${bestRev.tier || 'None'}</span>
      <span class="badge badge-navy" style="font-size:11px">${upgPct}% upgrade from base</span>
    `;
  }

  function renderClaimsDonut(statusMap) {
    statusMap = statusMap || {};
    const total = Object.values(statusMap).reduce((a, b) => a + b, 0);
    
    document.getElementById('dash-claims-total').innerHTML = `
      ${total}
      <div style="font-size:13px;font-weight:500;color:var(--text-muted)">total claims</div>
    `;

    if (total === 0) {
      document.getElementById('dash-claims-legend').innerHTML = '<div style="margin:auto;color:var(--text-muted)">No claims data</div>';
      return;
    }

    const m = {
      'submitted':    { lbl: 'Submitted', color: 'var(--text-light)' },
      'under_review': { lbl: 'Under Review', color: 'var(--amber)' },
      'approved':     { lbl: 'Approved', color: 'var(--emerald)' },
      'denied':       { lbl: 'Denied', color: 'var(--red)' },
      'paid':         { lbl: 'Paid', color: 'var(--blue)' }
    };

    const html = Object.keys(m).map(k => {
      const cnt = statusMap[k] || 0;
      return `
        <div class="donut-row">
          <div class="donut-label">
            <div class="donut-dot" style="background:${m[k].color}"></div>${m[k].lbl}
          </div>
          <span class="donut-count">${cnt}</span>
        </div>
      `;
    }).join('');

    document.getElementById('dash-claims-legend').innerHTML = html;
  }

  function renderRecentQuotes(quotes) {
    const tbody = document.getElementById('dash-recent-quotes');
    if (!quotes || quotes.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No quotes found.</td></tr>';
      return;
    }

    tbody.innerHTML = quotes.map(q => {
      const badgeClass = q.status === 'converted' ? 'badge-emerald' : 'badge-grey';
      const badgeText = q.status.charAt(0).toUpperCase() + q.status.slice(1);
      const goStr = q.status === 'converted' ? 'View' : 'Convert';

      return `
        <tr>
          <td class="mono" style="color:var(--blue)">#${q.id}</td>
          <td>${q.customer_name || q.customer_email || 'Guest'}</td>
          <td>${q.state || '—'}</td>
          <td>${fmtCurrency(q.coverage_amount).replace('.00','')}</td>
          <td><span class="badge ${badgeClass}" style="font-size:10px">${badgeText}</span></td>
          <td><a href="admin-quotes.html" style="color:var(--primary);font-size:12px;font-weight:600;text-decoration:none">${goStr}</a></td>
        </tr>
      `;
    }).join('');
  }

  function renderPendingClaims(claimsList, count) {
    document.getElementById('dash-pending-count').textContent = count || 0;
    const listDiv = document.getElementById('dash-pending-list');

    if (!claimsList || claimsList.length === 0) {
      listDiv.innerHTML = '<div style="text-align:center;color:var(--text-muted);padding:16px 0">All caught up! No pending claims.</div>';
      return;
    }

    const html = claimsList.map(c => {
      const waitDays = Math.floor((new Date() - new Date(c.created_at)) / (1000 * 60 * 60 * 24));
      
      let styleObj = { bg: 'var(--bg-soft)', lblColor: 'var(--text-muted)', lblText: 'NEW' };
      if (waitDays > 4) { styleObj = { bg: 'var(--red-lt)', lblColor: 'var(--red)', lblText: 'URGENT' }; }
      else if (waitDays > 1) { styleObj = { bg: 'var(--amber-lt)', lblColor: 'var(--amber)', lblText: 'REVIEW' }; }

      const sub = waitDays === 0 ? 'Today' : `${waitDays} day${waitDays>1?'s':''} waiting`;

      return `
        <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:${styleObj.bg};border-radius:var(--r-sm)">
          <div>
            <div style="font-size:13px;font-weight:700;color:var(--text-dark)">${c.claim_number} — ${c.customer_name}</div>
            <div style="font-size:11px;color:var(--text-muted)">${sub}</div>
          </div>
          <span style="font-size:11px;font-weight:700;color:${styleObj.lblColor}">${styleObj.lblText}</span>
        </div>
      `;
    }).join('');

    listDiv.innerHTML = html;
  }

  // Init
  loadDashboard();
})();
