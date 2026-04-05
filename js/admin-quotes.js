// ============================================================
// DriveSafe Cover — Admin Quotes Management
// ============================================================

(function () {
  const tbody = document.getElementById('quotesTableBody');
  if (!tbody) return;

  let allQuotes = [];
  const fmt = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

  const statusBadge = (status) => {
    const map = {
      pending:   ['badge-amber',   'Pending'],
      converted: ['badge-emerald',  'Converted'],
      expired:   ['badge-grey',    'Expired'],
    };
    const [cls, label] = map[status] || ['badge-grey', status];
    return `<span class="badge ${cls}">${label}</span>`;
  };

  async function load() {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">Loading...</td></tr>';

    try {
      const status = document.getElementById('quoteStatusFilter')?.value || '';
      let url = 'admin/quotes';
      if (status) url += `?status=${status}`;

      const res = await apiCall(url);
      const data = res.data;
      allQuotes = data.quotes || [];

      // KPI
      const kpi = data.kpi || {};
      const total     = parseInt(kpi.total) || 0;
      const converted = parseInt(kpi.converted) || 0;
      const rate      = total > 0 ? ((converted / total) * 100).toFixed(1) : '0';

      const setKpi = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      setKpi('kpi-total',     total);
      setKpi('kpi-pending',   kpi.pending || 0);
      setKpi('kpi-converted', converted);
      setKpi('kpi-rate',      `${rate}%`);

      renderTable();
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--crimson)">${err.message || 'Failed to load'}</td></tr>`;
    }
  }

  function renderTable() {
    const search = (document.getElementById('quoteSearch')?.value || '').toLowerCase();
    const filtered = allQuotes.filter(q => {
      if (!search) return true;
      return (q.customer_name || '').toLowerCase().includes(search)
          || (q.customer_email || '').toLowerCase().includes(search);
    });

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">No quotes found</td></tr>';
      return;
    }

    tbody.innerHTML = filtered.map(q => `
      <tr>
        <td style="font-weight:600">#${q.id}</td>
        <td>${q.customer_name || '<span style="color:var(--text-muted)">Guest</span>'}</td>
        <td>${q.state || '—'}</td>
        <td style="font-size:12px">${fmt(q.start_date)}</td>
        <td style="font-size:12px">${fmt(q.end_date)}</td>
        <td>${q.duration_days || '—'}</td>
        <td>${statusBadge(q.status)}</td>
        <td style="font-size:12px;color:var(--text-muted)">${fmt(q.created_at)}</td>
      </tr>
    `).join('');
  }

  document.getElementById('quoteSearch')?.addEventListener('input', renderTable);
  document.getElementById('quoteStatusFilter')?.addEventListener('change', load);

  load();
})();
