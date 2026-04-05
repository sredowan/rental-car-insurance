// ============================================================
// DriveSafe Cover — Admin Claims Management
// ============================================================

(function () {
  const tbody = document.getElementById('claimsTableBody');
  if (!tbody) return;

  let allClaims = [];
  let currentPage = 1;
  let currentClaimId = null;

  const fmt = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

  const statusBadge = (status) => {
    const map = {
      submitted:    ['badge-grey',    'Submitted'],
      under_review: ['badge-amber',   'Under Review'],
      approved:     ['badge-emerald',  'Approved'],
      denied:       ['badge-crimson',  'Denied'],
      paid:         ['badge-blue',     'Paid'],
    };
    const [cls, label] = map[status] || ['badge-grey', status];
    return `<span class="badge ${cls}">${label}</span>`;
  };

  async function loadClaims() {
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">Loading claims...</td></tr>';

    try {
      const status = document.getElementById('claimStatusFilter')?.value || '';
      let url = `admin/claims?page=${currentPage}`;
      if (status) url += `&status=${status}`;

      const res = await apiCall(url);
      allClaims = res.data || [];

      // Update pending count badge
      const pending = allClaims.filter(c => c.status === 'submitted' || c.status === 'under_review').length;
      const pendingEl = document.getElementById('pendingCount');
      if (pendingEl) pendingEl.textContent = pending > 0 ? pending : '';

      renderTable();
    } catch (err) {
      tbody.innerHTML = `<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--crimson)">${err.message || 'Failed to load claims'}</td></tr>`;
    }
  }

  function renderTable() {
    const search = (document.getElementById('claimSearch')?.value || '').toLowerCase();
    const filtered = allClaims.filter(c => {
      if (!search) return true;
      return (c.claim_number || '').toLowerCase().includes(search)
          || (c.customer_name || '').toLowerCase().includes(search)
          || (c.policy_number || '').toLowerCase().includes(search);
    });

    if (filtered.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--text-muted)">No claims found</td></tr>';
      return;
    }

    tbody.innerHTML = filtered.map(c => {
      let damageStr = '—';
      try { const dt = c.damage_types.replace(/&quot;/g, '"'); damageStr = JSON.parse(dt).join(', '); } catch { damageStr = c.damage_types || '—'; }

      return `
        <tr style="cursor:pointer" onclick="openPanel(${c.id})">
          <td class="mono" style="color:var(--blue);font-weight:600">${c.claim_number || '—'}</td>
          <td>${c.customer_name || '—'}</td>
          <td class="mono" style="font-size:12px">${c.policy_number || '—'}</td>
          <td>${damageStr}</td>
          <td style="font-weight:700;color:var(--crimson)">$${parseFloat(c.amount_claimed || 0).toFixed(2)}</td>
          <td>${statusBadge(c.status)}</td>
          <td style="font-size:12px;color:var(--text-muted)">${fmt(c.created_at)}</td>
          <td><button class="btn btn-outline btn-sm" style="font-size:11px;padding:4px 10px" onclick="event.stopPropagation();openPanel(${c.id})">View</button></td>
        </tr>
      `;
    }).join('');
  }

  // ── Search & Filter events ─────────────────────────────────
  document.getElementById('claimSearch')?.addEventListener('input', renderTable);
  document.getElementById('claimStatusFilter')?.addEventListener('change', () => {
    currentPage = 1;
    loadClaims();
  });

  // ── Panel ──────────────────────────────────────────────────
  window.openPanel = async function (claimId) {
    currentClaimId = claimId;
    const panel   = document.getElementById('claimPanel');
    const overlay = document.getElementById('panelOverlay');

    try {
      const res = await apiCall(`admin/claims?id=${claimId}`);
      const c = res.data;

      document.getElementById('panel-claimNumber').textContent = c.claim_number || '—';
      document.getElementById('panel-customer').textContent = c.customer_name || '—';
      document.getElementById('panel-policy').textContent = c.policy_number || '—';
      document.getElementById('panel-incidentDate').textContent = fmt(c.incident_date);
      document.getElementById('panel-submitted').textContent = fmt(c.created_at);
      document.getElementById('panel-rentalCompany').textContent = c.rental_company || '—';
      document.getElementById('panel-amountClaimed').textContent = `$${parseFloat(c.amount_claimed || 0).toFixed(2)}`;
      document.getElementById('panel-description').textContent = c.description || 'No description provided.';

      // Damage types
      let damageStr = '—';
      try { const dt = c.damage_types.replace(/&quot;/g, '"'); damageStr = JSON.parse(dt).join(', '); } catch { damageStr = c.damage_types || '—'; }
      document.getElementById('panel-damageTypes').textContent = damageStr;

      // Status badge and select
      document.getElementById('panel-status').innerHTML = statusBadge(c.status);
      document.getElementById('panel-statusSelect').value = c.status;
      document.getElementById('panel-notes').value = c.admin_notes || '';

      // Documents
      const docList = document.getElementById('panel-documents');
      const docs = c.documents || [];
      if (docs.length > 0) {
        docList.innerHTML = docs.map(d => `
          <div class="doc-item">
            <div class="doc-icon">📄</div>
            <div style="flex:1"><div style="font-weight:600">${d.document_type || 'Document'}</div><div style="font-size:11px;color:var(--text-muted)">${d.file_name || ''}</div></div>
            <a href="${d.file_path || '#'}" target="_blank" class="btn btn-outline btn-sm" style="font-size:11px;padding:3px 8px">View</a>
          </div>
        `).join('');
      } else {
        docList.innerHTML = '<div class="doc-item"><span style="color:var(--text-muted)">No documents uploaded</span></div>';
      }

      panel.classList.add('open');
      overlay.classList.add('open');
    } catch (err) {
      showToast(err.message || 'Failed to load claim details', 'error');
    }
  };

  window.closePanel = function () {
    document.getElementById('claimPanel').classList.remove('open');
    document.getElementById('panelOverlay').classList.remove('open');
    currentClaimId = null;
  };

  document.getElementById('panelOverlay')?.addEventListener('click', closePanel);

  // ── Update claim status ────────────────────────────────────
  document.getElementById('panel-updateBtn')?.addEventListener('click', async () => {
    if (!currentClaimId) return;

    const btn    = document.getElementById('panel-updateBtn');
    const status = document.getElementById('panel-statusSelect').value;
    const notes  = document.getElementById('panel-notes').value;

    btn.disabled = true;
    btn.textContent = 'Updating...';

    try {
      await apiCall(`admin/claims?id=${currentClaimId}`, 'PATCH', { status, admin_notes: notes });
      showToast('Claim updated successfully', 'success');
      closePanel();
      loadClaims();
    } catch (err) {
      showToast(err.message || 'Failed to update claim', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Update Claim';
    }
  });

  loadClaims();
})();
