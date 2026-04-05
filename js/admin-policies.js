// ============================================================
// DriveSafe Cover — Admin Policies Management
// ============================================================

(function () {
  const tbody = document.getElementById('policiesTableBody');
  if (!tbody) return;

  let allPolicies = [];
  const fmt = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

  const statusBadge = (status) => {
    const map = {
      active:    ['badge-emerald', 'Active'],
      expired:   ['badge-grey',    'Expired'],
      cancelled: ['badge-crimson', 'Cancelled'],
    };
    const [cls, label] = map[status] || ['badge-grey', status];
    return `<span class="badge ${cls}">${label}</span>`;
  };

  async function load() {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">Loading...</td></tr>';

    try {
      const status = document.getElementById('policyStatusFilter')?.value || '';
      let url = 'admin/policies';
      if (status) url += `?status=${status}`;

      const res = await apiCall(url);
      const data = res.data;
      allPolicies = data.policies || [];

      // KPI
      const kpi = data.kpi || {};
      const setKpi = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      setKpi('kpi-total',   kpi.total || 0);
      setKpi('kpi-active',  kpi.active || 0);
      setKpi('kpi-expired', kpi.expired || 0);
      setKpi('kpi-revenue', `$${parseFloat(kpi.revenue || 0).toLocaleString('en-AU', { minimumFractionDigits: 2 })}`);

      renderTable();
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--crimson)">${err.message || 'Failed to load'}</td></tr>`;
    }
  }

  function renderTable() {
    const search = (document.getElementById('policySearch')?.value || '').toLowerCase();
    const filtered = allPolicies.filter(p => {
      if (!search) return true;
      return (p.policy_number || '').toLowerCase().includes(search)
          || (p.customer_name || '').toLowerCase().includes(search);
    });

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">No policies found</td></tr>';
      return;
    }

    tbody.innerHTML = filtered.map(p => `
      <tr>
        <td class="mono" style="color:var(--blue);font-weight:600">${p.policy_number || '—'}</td>
        <td>${p.customer_name || '—'}</td>
        <td>${p.state || '—'}</td>
        <td>$${parseInt(p.coverage_amount || 0).toLocaleString()}</td>
        <td style="font-size:12px">${fmt(p.start_date)}</td>
        <td style="font-size:12px">${fmt(p.end_date)}</td>
        <td style="font-weight:700;color:var(--crimson)">$${parseFloat(p.total_price || 0).toFixed(2)}</td>
        <td>${statusBadge(p.status)}</td>
      </tr>
    `).join('');
  }

  document.getElementById('policySearch')?.addEventListener('input', renderTable);
  document.getElementById('policyStatusFilter')?.addEventListener('change', load);

  load();
})();
