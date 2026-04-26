// ============================================================
// DriveSafe Cover — Admin Settings
// ============================================================

(function () {
  const saveBtn = document.getElementById('saveSettingsBtn');
  if (!saveBtn) return;

  const fmt = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

  const roleBadge = r => r === 'super_admin' ? '<span class="badge badge-crimson">Super Admin</span>' : '<span class="badge badge-blue">Admin</span>';
  const statusBadge = s => s === 'active' ? '<span class="badge badge-emerald">Active</span>' : '<span class="badge badge-grey">Suspended</span>';

  // ── Load settings ──────────────────────────────────────────
  async function loadSettings() {
    try {
      const res = await apiCall('admin/settings');
      const s = res.data || {};

      // Populate fields
      const fields = ['app_name', 'app_url', 'support_email', 'mail_host', 'mail_port',
        'mail_encryption', 'mail_username', 'mail_from_name', 'mail_from_email',
        'stripe_mode', 'stripe_test_pub_key', 'stripe_live_pub_key',
        'plan_price_essential', 'plan_price_premium', 'plan_price_ultimate'];

      fields.forEach(key => {
        const el = document.getElementById(`set-${key}`);
        if (el && s[key] !== undefined) el.value = s[key];
      });
    } catch (err) {
      showToast(err.message || 'Failed to load settings', 'error');
    }
  }

  // ── Save settings ─────────────────────────────────────────
  saveBtn.addEventListener('click', async () => {
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="spinner"></span> Saving...';

    const data = {};
    const fields = ['app_name', 'app_url', 'support_email', 'mail_host', 'mail_port',
      'mail_encryption', 'mail_username', 'mail_from_name', 'mail_from_email', 'stripe_mode',
      'stripe_test_pub_key', 'stripe_live_pub_key',
      'plan_price_essential', 'plan_price_premium', 'plan_price_ultimate'];

    fields.forEach(key => {
      const el = document.getElementById(`set-${key}`);
      if (el && el.value) data[key] = el.value;
    });

    for (const key of ['plan_price_essential', 'plan_price_premium', 'plan_price_ultimate']) {
      if (data[key] !== undefined && (Number.isNaN(Number(data[key])) || Number(data[key]) < 0)) {
        showToast('Plan prices must be valid positive numbers.', 'error');
        saveBtn.disabled = false;
        saveBtn.textContent = 'Save All Settings';
        return;
      }
    }

    // Only include password/secrets if user typed something
    const mailPass = document.getElementById('set-mail_password');
    if (mailPass && mailPass.value) data.mail_password = mailPass.value;

    const testSec = document.getElementById('set-stripe_test_sec_key');
    if (testSec && testSec.value) data.stripe_test_sec_key = testSec.value;

    const liveSec = document.getElementById('set-stripe_live_sec_key');
    if (liveSec && liveSec.value) data.stripe_live_sec_key = liveSec.value;

    try {
      await apiCall('admin/settings', 'POST', data);
      showToast('Settings saved successfully!', 'success');
    } catch (err) {
      if (err.status === 403) {
        showToast('Only a super admin can save settings.', 'error');
      } else {
        showToast(err.message || 'Failed to save settings', 'error');
      }
    } finally {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save All Settings';
    }
  });

  // ── Load admin users ──────────────────────────────────────
  async function loadUsers() {
    const tbody = document.getElementById('adminUsersBody');
    try {
      const res = await apiCall('admin/users');
      const users = res.data || [];

      if (users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--text-muted)">No admin users found</td></tr>';
        return;
      }

      tbody.innerHTML = users.map(u => `
        <tr>
          <td style="font-weight:700">${u.full_name}</td>
          <td style="font-size:13px">${u.email}</td>
          <td>${roleBadge(u.role)}</td>
          <td>${statusBadge(u.status)}</td>
          <td style="font-size:12px;color:var(--text-muted)">${fmt(u.last_login)}</td>
          <td>
            ${u.status === 'active'
              ? `<button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px" onclick="deactivateAdmin(${u.id})">Suspend</button>`
              : `<button class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px" onclick="reactivateAdmin(${u.id})">Reactivate</button>`}
          </td>
        </tr>
      `).join('');
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:var(--crimson)">${err.message || 'Access denied'}</td></tr>`;
    }
  }

  window.deactivateAdmin = async (id) => {
    if (!confirm('Deactivate this admin?')) return;
    try {
      await apiCall(`admin/users?id=${id}`, 'DELETE');
      showToast('Admin deactivated', 'success');
      loadUsers();
    } catch (err) { showToast(err.message, 'error'); }
  };

  window.reactivateAdmin = async (id) => {
    try {
      await apiCall(`admin/users?id=${id}`, 'PUT', { status: 'active' });
      showToast('Admin reactivated', 'success');
      loadUsers();
    } catch (err) { showToast(err.message, 'error'); }
  };

  // ── Add admin modal ───────────────────────────────────────
  document.getElementById('addAdminBtn')?.addEventListener('click', () => {
    document.getElementById('addAdminModal').classList.add('open');
  });

  document.getElementById('addAdminForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('createAdminBtn');
    btn.disabled = true; btn.textContent = 'Creating...';

    try {
      await apiCall('admin/users', 'POST', {
        full_name: document.getElementById('newAdminName').value,
        email: document.getElementById('newAdminEmail').value,
        password: document.getElementById('newAdminPass').value,
        role: document.getElementById('newAdminRole').value,
      });
      showToast('Admin created!', 'success');
      document.getElementById('addAdminModal').classList.remove('open');
      document.getElementById('addAdminForm').reset();
      loadUsers();
    } catch (err) {
      showToast(err.message || 'Failed to create admin', 'error');
    } finally {
      btn.disabled = false; btn.textContent = 'Create Admin';
    }
  });

  // ── Load audit log preview ────────────────────────────────
  async function loadAuditPreview() {
    const container = document.getElementById('auditLog');
    try {
      const res = await apiCall('admin/audit?limit=10');
      const logs = res.data || [];

      if (logs.length === 0) {
        container.innerHTML = '<p style="color:var(--text-muted);font-size:13px;padding:12px 0">No activity recorded yet</p>';
        return;
      }

      container.innerHTML = logs.map(l => `
        <div style="display:flex;gap:12px;align-items:center;padding:8px 12px;background:var(--bg-soft);border-radius:var(--r-sm)">
          <div style="width:8px;height:8px;border-radius:50%;background:var(--emerald);flex-shrink:0"></div>
          <div style="flex:1">
            <div style="font-size:13px;font-weight:600;color:var(--text-dark)">${l.action || '—'}</div>
            <div style="font-size:11px;color:var(--text-muted)">${l.admin_name || 'System'} · ${l.details || ''}</div>
          </div>
          <div style="font-size:11px;color:var(--text-muted);flex-shrink:0">${fmt(l.created_at)}</div>
        </div>
      `).join('');
    } catch (err) {
      container.innerHTML = `<p style="color:var(--text-muted);font-size:13px;padding:12px 0">Could not load audit log</p>`;
    }
  }

  loadSettings();
  loadUsers();
  loadAuditPreview();
})();
