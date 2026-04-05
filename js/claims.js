// ============================================================
// DriveSafe Cover — Claims Submission (Real API)
// ============================================================

(function () {
  const form = document.getElementById('claimForm');
  if (!form) return;

  // ── Load customer's active policies into dropdown ────────
  const policySelect = document.getElementById('claimPolicyId');
  const loadMsg      = document.getElementById('policyLoadMsg');

  async function loadPolicies() {
    if (!Auth.isLoggedIn()) {
      if (loadMsg) loadMsg.textContent = 'Please sign in to submit a claim.';
      policySelect.innerHTML = '<option value="">Sign in required</option>';
      return;
    }

    try {
      const res = await apiCall('policies');
      const policies = (res.data || []).filter(p => p.status === 'active');

      if (policies.length === 0) {
        policySelect.innerHTML = '<option value="">No active policies found</option>';
        if (loadMsg) loadMsg.textContent = 'You need an active policy to submit a claim.';
      } else {
        policySelect.innerHTML = '<option value="">Select your policy</option>';
        policies.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = `${p.policy_number} — $${parseInt(p.coverage_amount).toLocaleString()} (${p.state})`;
          policySelect.appendChild(opt);
        });
        if (loadMsg) loadMsg.style.display = 'none';
      }
    } catch (err) {
      if (loadMsg) loadMsg.textContent = 'Could not load policies. Please try again.';
    }
  }

  loadPolicies();

  // ── Damage chips toggle ──────────────────────────────────
  document.querySelectorAll('.damage-chip').forEach(chip => {
    chip.addEventListener('click', () => chip.classList.toggle('selected'));
  });

  // ── File upload visual feedback ──────────────────────────
  const fileInputs = [
    { input: 'fileRentalAgreement', label: 'fname-rental', zone: 'zone-rental' },
    { input: 'fileInvoice',        label: 'fname-invoice', zone: 'zone-invoice' },
    { input: 'fileDriverLicence',  label: 'fname-licence', zone: 'zone-licence' },
    { input: 'fileDamagePhotos',   label: 'fname-photos',  zone: 'zone-photos' },
  ];

  fileInputs.forEach(({ input, label, zone }) => {
    const fileEl  = document.getElementById(input);
    const labelEl = document.getElementById(label);
    const zoneEl  = document.getElementById(zone);
    if (!fileEl) return;

    fileEl.addEventListener('change', () => {
      const files = fileEl.files;
      if (files.length === 0) {
        if (labelEl) labelEl.textContent = '';
        if (zoneEl) zoneEl.classList.remove('has-file');
        return;
      }

      const names = Array.from(files).map(f => f.name).join(', ');
      if (labelEl) labelEl.textContent = `✓ ${names}`;
      if (zoneEl) zoneEl.classList.add('has-file');
    });
  });

  // ── Form submission ──────────────────────────────────────
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!Auth.isLoggedIn()) {
      showToast('Please sign in to submit a claim.', 'error');
      window.location.href = 'login.html?next=claims';
      return;
    }

    const submitBtn = document.getElementById('claimSubmitBtn');

    // Collect damage types
    const damageTypes = Array.from(document.querySelectorAll('.damage-chip.selected'))
      .map(c => c.dataset.type);

    if (damageTypes.length === 0) {
      showToast('Please select at least one damage type.', 'error');
      return;
    }

    // Validate required files
    const rental = document.getElementById('fileRentalAgreement');
    const invoice = document.getElementById('fileInvoice');
    const licence = document.getElementById('fileDriverLicence');
    const photos = document.getElementById('fileDamagePhotos');

    if (!rental?.files.length || !invoice?.files.length || !licence?.files.length || !photos?.files.length) {
      showToast('Please upload all required documents.', 'error');
      return;
    }

    // Build FormData
    const fd = new FormData();
    fd.append('policy_id', document.getElementById('claimPolicyId').value);
    fd.append('rental_company', document.getElementById('claimRentalCompany').value);
    fd.append('incident_date', document.getElementById('claimIncidentDate').value);
    fd.append('damage_types', JSON.stringify(damageTypes));
    fd.append('description', document.getElementById('claimDescription').value);
    fd.append('amount_charged', document.getElementById('claimAmount').value);

    // Append files
    fd.append('rental_agreement', rental.files[0]);
    fd.append('invoice', invoice.files[0]);
    fd.append('driver_licence', licence.files[0]);
    Array.from(photos.files).forEach(f => fd.append('damage_photos[]', f));

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Submitting...';

    try {
      const res = await apiCall('claims', 'POST', fd, true);
      showToast('Claim submitted successfully!', 'success');
      setTimeout(() => {
        window.location.href = 'my-claims.html';
      }, 1500);
    } catch (err) {
      showToast(err.message || 'Failed to submit claim. Please try again.', 'error');
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'SUBMIT CLAIM <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    }
  });

  // ── Dynamic Warning Trigger ───────────────────────────────
  const confirmCheck = document.getElementById('claimConfirm');
  const warningBlock = document.getElementById('claimWarningBlock');
  if (confirmCheck && warningBlock) {
    confirmCheck.addEventListener('change', (e) => {
      warningBlock.style.display = 'block';
      if (e.target.checked) {
        // smooth scroll to it just in case
        warningBlock.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });

    // Also trigger if they focus any file input (gets them aware early)
    document.querySelectorAll('input[type="file"]').forEach(inp => {
       inp.addEventListener('change', () => { warningBlock.style.display = 'block'; }, { once: true });
    });
  }
})();
