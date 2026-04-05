// ============================================================
// DriveSafe Cover — Admin Customers Management
// ============================================================

(function () {
  const tbody = document.getElementById('customersTableBody');
  if (!tbody) return;

  let allCustomers = [];
  const fmt = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

  async function load() {
    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">Loading...</td></tr>';

    try {
      const search = document.getElementById('customerSearch')?.value || '';
      let url = 'admin/customers';
      if (search) url += `?search=${encodeURIComponent(search)}`;

      const res = await apiCall(url);
      allCustomers = res.data || [];

      // KPIs
      const total = res.pagination?.total || allCustomers.length;
      const thisMonth = allCustomers.filter(c => {
        const d = new Date(c.created_at);
        const now = new Date();
        return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
      }).length;
      const withPolicy = allCustomers.filter(c => c.policy_count > 0).length;
      const totalClaims = allCustomers.reduce((sum, c) => sum + (c.claim_count || 0), 0);

      const setKpi = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      setKpi('kpi-total',  total);
      setKpi('kpi-month',  thisMonth);
      setKpi('kpi-active', withPolicy);
      setKpi('kpi-claims', totalClaims);

      renderTable();
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--crimson)">${err.message || 'Failed to load'}</td></tr>`;
    }
  }

  function renderTable() {
    if (allCustomers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted)">No customers found</td></tr>';
      return;
    }

    tbody.innerHTML = allCustomers.map(c => {
      const initials = (c.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
      return `
        <tr>
          <td style="display:flex;align-items:center;gap:10px">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--bg-navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">${initials}</div>
            <div>
              <div style="font-weight:700">${c.full_name || '—'}</div>
            </div>
          </td>
          <td style="font-size:13px">${c.email || '—'}</td>
          <td style="font-size:13px">${c.phone || '—'}</td>
          <td>${c.state || '—'}</td>
          <td><span class="badge ${c.policy_count > 0 ? 'badge-emerald' : 'badge-grey'}">${c.policy_count}</span></td>
          <td><span class="badge ${c.claim_count > 0 ? 'badge-amber' : 'badge-grey'}">${c.claim_count}</span></td>
          <td style="font-size:12px;color:var(--text-muted)">${fmt(c.created_at)}</td>
        </tr>
      `;
    }).join('');
  }

  // Debounced search
  let searchTimer;
  document.getElementById('customerSearch')?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 400);
  });

  load();
})();
