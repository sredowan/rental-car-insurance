// ============================================================
// Rental Shield — My Profile Controller
// ============================================================

(function () {
  const user = Auth.user();
  if (!user) return;

  // Avatar initials
  document.querySelectorAll('.topbar-avatar').forEach(el => {
    const initials = (user.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
    el.textContent = initials;
  });

  // ── Load Profile ──────────────────────────────────────────
  async function loadProfile() {
    try {
      const res = await apiCall('profile');
      const p = res.data;

      document.getElementById('profileName').textContent = p.full_name || 'User';
      document.getElementById('profileEmail').textContent = p.email || '—';
      document.getElementById('editName').value = p.full_name || '';
      document.getElementById('editEmail').value = p.email || '';
      document.getElementById('editPhone').value = p.phone || '';
      if (p.state) document.getElementById('editState').value = p.state;

      // Stats
      if (p.stats) {
        document.getElementById('statPolicies').textContent = p.stats.total_policies || '0';
        document.getElementById('statClaims').textContent = p.stats.total_claims || '0';
        document.getElementById('statSpent').textContent = '$' + parseFloat(p.stats.total_spent || 0).toFixed(0);
      }

      // Avatar
      const avatarEl = document.getElementById('profileAvatar');
      if (p.profile_photo) {
        avatarEl.innerHTML = `<img src="/uploads/${p.profile_photo}" alt="Profile" style="width:96px;height:96px;border-radius:50%;object-fit:cover">`;
      } else {
        const initials = (p.full_name || 'U').split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
        avatarEl.textContent = initials;
      }

      // Licence preview
      if (p.driving_licence) {
        const licArea = document.getElementById('licenceUploadArea');
        const licPreview = document.getElementById('licencePreview');
        const licStatus = document.getElementById('licenceStatus');
        licArea.classList.add('has-file');
        
        const ext = p.driving_licence.split('.').pop().toLowerCase();
        if (ext === 'pdf') {
          licPreview.style.display = 'none';
          licStatus.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> PDF uploaded`;
        } else {
          licPreview.src = '/uploads/' + p.driving_licence;
          licPreview.style.display = 'block';
          licStatus.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Uploaded`;
        }
        licStatus.className = 'doc-status uploaded';
      }

      // Photo preview
      if (p.profile_photo) {
        const photoArea = document.getElementById('photoUploadArea');
        const photoPreview = document.getElementById('photoPreview');
        const photoStatus = document.getElementById('photoStatus');
        photoArea.classList.add('has-file');
        photoPreview.src = '/uploads/' + p.profile_photo;
        photoPreview.style.display = 'block';
        photoStatus.innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg> Uploaded`;
        photoStatus.className = 'doc-status uploaded';
      }

    } catch (err) {
      console.error('Profile load error:', err);
    }
  }

  // ── Save Profile ──────────────────────────────────────────
  document.getElementById('profileForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Saving...'; btn.disabled = true;

    try {
      await apiCall('profile', 'PUT', {
        full_name: document.getElementById('editName').value,
        phone: document.getElementById('editPhone').value,
        state: document.getElementById('editState').value,
      });

      // Update local storage
      const u = Auth.user();
      if (u) {
        u.full_name = document.getElementById('editName').value;
        u.phone = document.getElementById('editPhone').value;
        u.state = document.getElementById('editState').value;
        localStorage.setItem('dsc_user', JSON.stringify(u));
      }

      showToast('Profile updated successfully!', 'success');
      loadProfile();
    } catch (err) {
      showToast(err.message || 'Failed to update profile.', 'error');
    }
    btn.innerHTML = oldHtml; btn.disabled = false;
  });

  // ── Change Password ───────────────────────────────────────
  document.getElementById('passwordForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const current = document.getElementById('currentPass').value;
    const newPass = document.getElementById('newPass').value;
    const confirmPass = document.getElementById('confirmPass').value;

    if (!current || !newPass) return showToast('Please fill in all password fields.', 'error');
    if (newPass !== confirmPass) return showToast('New passwords do not match.', 'error');
    if (newPass.length < 8) return showToast('Password must be at least 8 characters.', 'error');

    const btn = document.getElementById('changePassBtn');
    const oldHtml = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span> Updating...'; btn.disabled = true;

    try {
      await apiCall('profile', 'PUT', {
        current_password: current,
        new_password: newPass,
      });
      showToast('Password updated successfully!', 'success');
      document.getElementById('currentPass').value = '';
      document.getElementById('newPass').value = '';
      document.getElementById('confirmPass').value = '';
    } catch (err) {
      showToast(err.message || 'Failed to change password.', 'error');
    }
    btn.innerHTML = oldHtml; btn.disabled = false;
  });

  // ── Document Uploads ──────────────────────────────────────
  async function uploadDocument(file, uploadType) {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_type', uploadType);

    try {
      const res = await apiCall('profile', 'POST', formData, true);
      showToast(res.message || 'Upload successful!', 'success');
      loadProfile(); // Refresh to show preview
      return true;
    } catch (err) {
      showToast(err.message || 'Upload failed.', 'error');
      return false;
    }
  }

  // Driving Licence upload
  document.getElementById('licenceFileInput')?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const statusEl = document.getElementById('licenceStatus');
    statusEl.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Uploading...';
    statusEl.className = 'doc-status pending';
    await uploadDocument(file, 'driving_licence');
  });

  // Profile Photo upload (from documents section)
  document.getElementById('photoFileInput')?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const statusEl = document.getElementById('photoStatus');
    statusEl.innerHTML = '<span class="spinner" style="width:14px;height:14px;border-width:2px"></span> Uploading...';
    statusEl.className = 'doc-status pending';
    await uploadDocument(file, 'profile_photo');
  });

  // Avatar quick-upload (same as profile photo)
  document.getElementById('avatarFileInput')?.addEventListener('change', async (e) => {
    const file = e.target.files[0];
    if (!file) return;
    await uploadDocument(file, 'profile_photo');
  });

  // ── Init ──────────────────────────────────────────────────
  loadProfile();
})();
