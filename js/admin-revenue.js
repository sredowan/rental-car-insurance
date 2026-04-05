// ============================================================
// DriveSafe Cover — Admin Revenue Analytics
// ============================================================

(function () {
  const tierBars   = document.getElementById('tierBars');
  const stateBars  = document.getElementById('stateBars');
  const monthly    = document.getElementById('monthlyChart');
  if (!tierBars) return;

  let currentPeriod = 'month';

  const fmtCurrency = (n) => `$${parseFloat(n).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

  const tierColors = {
    4000: 'linear-gradient(90deg,var(--crimson),#ff4d6d)',
    5000: 'linear-gradient(90deg,#7C3AED,#A855F7)',
    6000: 'linear-gradient(90deg,var(--amber),#FCD34D)',
    7000: 'linear-gradient(90deg,var(--emerald),#34D399)',
    8000: 'linear-gradient(90deg,var(--blue),#60A5FA)',
  };

  async function load() {
    try {
      const res = await apiCall(`admin/revenue?period=${currentPeriod}`);
      const d = res.data;

      // KPIs
      const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
      set('kpi-revenue', fmtCurrency(d.kpi.total_revenue));
      set('kpi-net', fmtCurrency(d.kpi.net_revenue));
      set('kpi-aov', fmtCurrency(d.kpi.avg_order_value));
      set('kpi-loss', `${d.kpi.loss_ratio}%`);

      // Tier bars
      const maxTier = Math.max(...d.by_tier.map(t => parseFloat(t.revenue)), 1);
      tierBars.innerHTML = d.by_tier.map(t => {
        const pct = (parseFloat(t.revenue) / maxTier * 100).toFixed(0);
        const bg = tierColors[t.tier] || 'var(--crimson)';
        return `
          <div class="chart-bar-item">
            <span class="chart-bar-label">$${parseInt(t.tier).toLocaleString()}</span>
            <div class="chart-bar-track"><div class="chart-bar-fill" style="width:${pct}%;background:${bg}"></div></div>
            <span class="chart-bar-val">${fmtCurrency(t.revenue)}</span>
          </div>
        `;
      }).join('') || '<p style="color:var(--text-muted);font-size:13px">No data for this period</p>';

      // State bars
      const maxState = Math.max(...d.by_state.map(s => parseFloat(s.revenue)), 1);
      stateBars.innerHTML = d.by_state.slice(0, 8).map(s => {
        const pct = (parseFloat(s.revenue) / maxState * 100).toFixed(0);
        return `
          <div class="chart-bar-item">
            <span class="chart-bar-label">${s.state}</span>
            <div class="chart-bar-track"><div class="chart-bar-fill" style="width:${pct}%;background:linear-gradient(90deg,var(--blue),#818CF8)"></div></div>
            <span class="chart-bar-val">${fmtCurrency(s.revenue)}</span>
          </div>
        `;
      }).join('') || '<p style="color:var(--text-muted);font-size:13px">No data for this period</p>';

      // Monthly chart
      const maxMonth = Math.max(...d.monthly.map(m => parseFloat(m.revenue)), 1);
      monthly.innerHTML = d.monthly.map(m => {
        const pct = (parseFloat(m.revenue) / maxMonth * 100).toFixed(0);
        const label = m.month.split('-')[1];
        const months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `
          <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
            <div style="font-size:10px;font-weight:700;color:var(--text-muted)">${fmtCurrency(m.revenue)}</div>
            <div style="width:100%;background:var(--bg-soft);border-radius:4px;height:120px;display:flex;align-items:flex-end">
              <div style="width:100%;height:${pct}%;background:linear-gradient(180deg,var(--crimson),#ff4d6d);border-radius:4px;transition:height 1s ease"></div>
            </div>
            <div style="font-size:10px;color:var(--text-muted);font-weight:600">${months[parseInt(label)]}</div>
          </div>
        `;
      }).join('') || '<p style="color:var(--text-muted);font-size:13px;padding:20px">No revenue data yet</p>';

    } catch (err) {
      tierBars.innerHTML = `<p style="color:var(--crimson);font-size:13px">${err.message || 'Failed to load revenue data'}</p>`;
    }
  }

  // Period tab switching
  document.querySelectorAll('.period-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.period-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentPeriod = tab.dataset.period;
      load();
    });
  });

  load();
})();
