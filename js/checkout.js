// ============================================================
// DriveSafe Cover — Stripe Checkout Integration
// ============================================================

(function () {
  const payBtn        = document.getElementById('payBtn');
  const cardContainer = document.getElementById('stripe-card-element');
  const cardErrors    = document.getElementById('card-errors');
  if (!payBtn || !cardContainer) return;

  // ── Get quote data from sessionStorage ───────────────────
  const quote = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
  if (!quote.quote_id) {
    showToast('No quote found. Please get a quote first.', 'error');
    setTimeout(() => window.location.href = 'index.html#quote', 2000);
    return;
  }

  // ── Populate order summary ───────────────────────────────
  const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
  setEl('co-coverage', `$${parseInt(quote.coverage || 0).toLocaleString()} — ${quote.tierLabel || ''}`);
  setEl('co-state', quote.state || '—');
  setEl('co-start', quote.start_date ? new Date(quote.start_date).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
  setEl('co-end', quote.end_date ? new Date(quote.end_date).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
  setEl('co-days', `${quote.days || 0} days`);
  setEl('co-rate', `$${parseFloat(quote.pricePerDay || 0).toFixed(2)}/day`);
  setEl('co-total', `$${parseFloat(quote.totalPrice || 0).toFixed(2)}`);

  // ── Pre-fill customer details ────────────────────────────
  const user = Auth.user();
  if (user) {
    const n = (user.full_name || '').split(' ');
    const firstName = document.getElementById('checkoutFirstName');
    const lastName  = document.getElementById('checkoutLastName');
    const email     = document.getElementById('checkoutEmail');
    const phone     = document.getElementById('checkoutPhone');
    if (firstName && n[0]) firstName.value = n[0];
    if (lastName && n.length > 1) lastName.value = n.slice(1).join(' ');
    if (email) {
      email.value = user.email || '';
    }
    if (phone) phone.value = user.phone || '';
  }

  // ── Initialize Stripe ────────────────────────────────────
  const stripeKey = document.querySelector('meta[name="stripe-key"]')?.content;
  if (!stripeKey) {
    showToast('Payment system not configured', 'error');
    return;
  }

  const stripe   = Stripe(stripeKey);
  const elements = stripe.elements();
  const card     = elements.create('card', {
    style: {
      base: {
        fontSize: '16px',
        color: '#1F2937',
        fontFamily: "'Plus Jakarta Sans', sans-serif",
        '::placeholder': { color: '#9CA3AF' },
      },
      invalid: { color: '#DC2626' },
    },
    hidePostalCode: true,
  });
  card.mount('#stripe-card-element');

  card.on('change', (event) => {
    if (cardErrors) {
      cardErrors.textContent = event.error ? event.error.message : '';
    }
  });

  // ── Handle Payment ───────────────────────────────────────
  let isProcessing = false;

  payBtn.addEventListener('click', async (e) => {
    e.preventDefault();
    if (isProcessing) return;

    // Validate terms checkbox
    const termsCheck = document.getElementById('termsCheck');
    if (termsCheck && !termsCheck.checked) {
      showToast('Please agree to the Terms & Conditions', 'error');
      return;
    }

    isProcessing = true;
    payBtn.disabled = true;
    payBtn.innerHTML = '<span class="spinner"></span> Processing...';

    try {
      // Gather customer details from form
      const firstName = document.getElementById('checkoutFirstName')?.value || '';
      const lastName  = document.getElementById('checkoutLastName')?.value || '';
      const fullName  = `${firstName} ${lastName}`.trim();
      const email     = document.getElementById('checkoutEmail')?.value || '';
      const phone     = document.getElementById('checkoutPhone')?.value || '';

      if (!email) {
        showToast('Please enter your email address.', 'error');
        payBtn.disabled = false;
        payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;vertical-align:-3px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> COMPLETE PURCHASE <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
        isProcessing = false;
        return;
      }

      // Step 1: Create PaymentIntent on server
      const intentRes = await apiCall('payments/create-intent', 'POST', {
        quote_id: quote.quote_id,
        coverage_amount: parseInt(quote.coverage),
        email: email,
      });

      if (!intentRes.data?.client_secret) {
        throw new Error(intentRes.message || 'Failed to create payment');
      }

      // Step 2: Confirm payment with Stripe
      const { error, paymentIntent } = await stripe.confirmCardPayment(
        intentRes.data.client_secret,
        {
          payment_method: {
            card: card,
            billing_details: {
              name: fullName,
              email: email,
            },
          },
        }
      );

      if (error) {
        throw new Error(error.message);
      }

      if (paymentIntent.status === 'succeeded') {
        // Step 3: Create policy on server
        const confirmRes = await apiCall('payments/confirm', 'POST', {
          payment_intent_id: paymentIntent.id,
          quote_id: quote.quote_id,
          coverage_amount: parseInt(quote.coverage),
          email: email,
          name: fullName,
          phone: phone,
        });

        if (confirmRes.success) {
          // If guest checkout auto-created an account, log them in transparently
          if (confirmRes.data.token && confirmRes.data.customer) {
              Auth.save(confirmRes.data.token, confirmRes.data.customer);
          }
          // Store policy info and redirect
          sessionStorage.setItem('dsc_new_policy', JSON.stringify(confirmRes.data.policy));
          sessionStorage.removeItem('dsc_quote');
          window.location.href = 'payment-success.html';
        } else {
          throw new Error(confirmRes.message || 'Failed to create policy');
        }
      }
    } catch (err) {
      showToast(err.message || 'Payment failed. Please try again.', 'error');
      payBtn.disabled = false;
      payBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;vertical-align:-3px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> COMPLETE PURCHASE <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
      isProcessing = false;
    }
  });
})();
