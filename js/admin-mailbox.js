(function () {
  if (!document.getElementById('threadList')) return;

  const admin = Auth.admin();
  if (!admin) {
    window.location.href = 'admin-login.html';
    return;
  }

  const threadList = document.getElementById('threadList');
  const activeHeader = document.getElementById('activeHeader');
  const activeBody = document.getElementById('activeBody');
  const activeReply = document.getElementById('activeReply');
  const replyText = document.getElementById('replyText');
  const btnSendReply = document.getElementById('btnSendReply');

  let currentCustomerId = null;

  async function loadInbox() {
    try {
      const res = await apiCall('admin/mailbox');
      const threads = res.data || [];

      if (threads.length === 0) {
        threadList.innerHTML = '<div style="padding:40px;text-align:center;color:var(--text-muted)">Inbox is empty</div>';
        return;
      }

      threadList.innerHTML = threads.map(t => {
        const isUnread = parseInt(t.unread_count) > 0;
        const d = new Date(t.created_at);
        const timeStr = d.toLocaleDateString() === new Date().toLocaleDateString() ? 
          d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : 
          d.toLocaleDateString([], {month:'short', day:'numeric'});

        return `
          <div class="mail-item ${isUnread ? 'unread' : ''}" data-customer="${t.customer_id}">
            <div class="mail-name">${t.customer_name} <span class="mail-time">${timeStr}</span></div>
            <div class="mail-subject">${t.subject}</div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              ${t.direction === 'outbound' ? 'You: ' : ''}${t.message}
            </div>
          </div>
        `;
      }).join('');

      document.querySelectorAll('.mail-item').forEach(el => {
        el.addEventListener('click', () => {
          document.querySelectorAll('.mail-item').forEach(i => i.style.background = '');
          el.style.background = 'var(--bg-soft)';
          el.classList.remove('unread');
          openThread(el.dataset.customer);
        });
      });

      // Update sidebar badge
      const totalUnread = threads.reduce((acc, t) => acc + parseInt(t.unread_count), 0);
      const badge = document.getElementById('sidebarMailBadge');
      if (badge) {
        badge.textContent = totalUnread;
        badge.style.display = totalUnread > 0 ? 'inline-block' : 'none';
      }

    } catch (e) {
      threadList.innerHTML = '<div style="padding:40px;text-align:center;color:var(--red)">Failed to load inbox</div>';
    }
  }

  async function openThread(customerId) {
    currentCustomerId = customerId;
    activeBody.innerHTML = '<div style="margin:auto;color:var(--text-muted)">Loading conversation...</div>';
    activeHeader.style.display = 'flex';
    activeReply.style.display = 'block';

    try {
      const res = await apiCall(`admin/mailbox?thread=${customerId}`);
      const msgs = res.data || [];

      if (msgs.length > 0) {
        document.getElementById('activeName').textContent = msgs[0].customer_name;
        document.getElementById('activeEmail').textContent = msgs[0].customer_email;
        document.getElementById('activeSubject').textContent = msgs[0].subject || "Support Request";
      }

      activeBody.innerHTML = msgs.map(m => {
        const isOut = m.direction === 'outbound';
        const d = new Date(m.created_at);
        const timeStr = d.toLocaleDateString([], {month:'short', day:'numeric'}) + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        return `
          <div style="display:flex; flex-direction:column; gap:4px">
            <span style="font-size:11px;color:var(--text-muted);align-self:${isOut ? 'flex-end' : 'flex-start'}">${isOut ? 'You' : m.customer_name} • ${timeStr}</span>
            <div class="chat-bubble ${isOut ? 'chat-outbound' : 'chat-inbound'}">
              ${m.message}
            </div>
          </div>
        `;
      }).join('');

      activeBody.scrollTop = activeBody.scrollHeight; // anchor bottom
    } catch (e) {
      activeBody.innerHTML = '<div style="margin:auto;color:var(--red)">Failed to load thread</div>';
    }
  }

  btnSendReply.addEventListener('click', async () => {
    if (!currentCustomerId) return;
    const msg = replyText.value.trim();
    if (!msg) return;

    btnSendReply.disabled = true;
    btnSendReply.textContent = 'Sending...';

    try {
      const res = await apiCall('admin/mailbox', 'POST', {
        customer_id: currentCustomerId,
        message: msg
      });
      replyText.value = '';
      
      // refresh thread
      await openThread(currentCustomerId);
      loadInbox(); // refresh list snippet logic if we want to bubble it to top
    } catch(err) {
      alert(err.message || "Failed to send reply");
    } finally {
      btnSendReply.disabled = false;
      btnSendReply.textContent = 'Send Reply';
    }
  });

  loadInbox();
})();
