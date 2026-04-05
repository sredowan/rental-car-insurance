// ============================================================
// DriveSafe Cover — Customer Dashboard (Real API Data)
// ============================================================

(function () {
  if (!document.getElementById('dashboardPage')) return;

  const user = Auth.user();
  if (!user) return;

  // ── Greeting ────────────────────────────────────────────────
  const greetEl = document.getElementById('dashGreeting');
  if (greetEl) {
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';
    const firstName = (user.full_name || 'there').split(' ')[0];
    greetEl.textContent = `${greeting}, ${firstName}`;
  }

  // Avatar initials
  document.querySelectorAll('.topbar-avatar').forEach(el => {
    const initials = (user.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    el.textContent = initials;
  });

  // ── Load active policy ──────────────────────────────────────
  async function loadActivePolicy() {
    const heroCard = document.getElementById('policyHeroCard');
    const emptyState = document.getElementById('noPolicyState');
    if (!heroCard) return;

    try {
      const res = await apiCall('policies');
      const policies = res.data || [];
      // Prioritize active, then future
      const active = policies.find(p => p.status === 'active') || policies.find(p => p.status === 'future');

      if (active) {
        heroCard.style.display = '';
        if (emptyState) emptyState.style.display = 'none';

        const fmt = d => new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });
        const badgeLabel = active.status === 'future' ? '● Scheduled Policy' : '● Active Policy';
        const badgeColor = active.status === 'future' ? 'bg-blue-500' : 'badge-emerald'; // fallback class or modify style
        
        const badgeEl = document.getElementById('hero-status-badge');
        badgeEl.textContent = badgeLabel;
        if(active.status === 'future') {
            badgeEl.className = 'badge badge-blue';
        } else {
            badgeEl.className = 'badge badge-emerald';
        }
        document.getElementById('hero-policy-number').textContent = active.policy_number;
        document.getElementById('hero-coverage').textContent = `$${parseInt(active.coverage_amount).toLocaleString()}`;
        document.getElementById('hero-state').textContent = active.state;
        document.getElementById('hero-start').textContent = fmt(active.start_date);
        document.getElementById('hero-end').textContent = fmt(active.end_date);
        document.getElementById('hero-days').textContent = `${active.days} days`;
        document.getElementById('hero-rate').textContent = `$${parseFloat(active.price_per_day).toFixed(2)}`;
        document.getElementById('hero-excess').textContent = '$0';
        document.getElementById('hero-total').textContent = `$${parseFloat(active.total_price).toFixed(2)}`;
      } else {
        heroCard.style.display = 'none';
        if (emptyState) emptyState.style.display = '';
      }
    } catch (err) {
      console.warn('Failed to load policies:', err.message);
      heroCard.style.display = 'none';
      if (emptyState) emptyState.style.display = '';
    }
  }

  // ── Load recent claims ──────────────────────────────────────
  async function loadRecentActivity() {
    const tbody = document.getElementById('activityTableBody');
    const noActivity = document.getElementById('noActivity');
    if (!tbody) return;

    try {
      const res = await apiCall('claims');
      const claims = res.data || [];

      if (claims.length === 0) {
        tbody.innerHTML = '';
        if (noActivity) noActivity.style.display = '';
        return;
      }

      if (noActivity) noActivity.style.display = 'none';

      const statusBadge = (s) => {
        const map = {
          submitted: 'badge-grey',
          under_review: 'badge-amber',
          approved: 'badge-emerald',
          denied: 'badge-crimson',
          paid: 'badge-blue',
        };
        const label = s.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
        return `<span class="badge ${map[s] || 'badge-grey'}" style="font-size:11px">${label}</span>`;
      };

      const fmt = d => new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' });

      tbody.innerHTML = claims.slice(0, 5).map(c => `
        <tr>
          <td class="mono" style="color:var(--blue)">${c.claim_number}</td>
          <td>${c.damage_types ? JSON.parse(c.damage_types).join(', ') : '—'}</td>
          <td>${c.policy_number || '—'}</td>
          <td>${fmt(c.created_at)}</td>
          <td>${statusBadge(c.status)}</td>
        </tr>
      `).join('');
    } catch (err) {
      console.warn('Failed to load claims:', err.message);
    }
  }

  loadActivePolicy();
  loadRecentActivity();
})();
