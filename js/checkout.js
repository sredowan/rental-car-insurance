// ============================================================
// Rental Shield — Checkout (Redesigned)
// Handles Stripe payment + editable trip dates
// ============================================================

(function () {
  const payBtn        = document.getElementById('payBtn');
  const mobPayBtn     = document.getElementById('coMobPayBtn');
  const cardContainer = document.getElementById('stripe-card-element');
  const cardErrors    = document.getElementById('card-errors');
  if (!payBtn || !cardContainer) return;

  // ── Get quote data from sessionStorage ───────────────────
  let quote = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');
  if (!quote.quote_id) {
    showToast('No quote found. Please get a quote first.', 'error');
    setTimeout(() => window.location.href = 'index.html#quote', 2000);
    return;
  }

  // ── Populate order summary ───────────────────────────────
  function populateOrder() {
    const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    const vtData = CoveragePricing.vehicleSurcharges[quote.vehicle_type || 'car'] || CoveragePricing.vehicleSurcharges.car;
    const fmtDate = d => d ? new Date(d).toLocaleDateString('en-AU', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';

    // Trip bar
    setEl('co-state', quote.state || '—');
    setEl('co-start', fmtDate(quote.start_date));
    setEl('co-end', fmtDate(quote.end_date));
    setEl('co-days', `${quote.days || 0} days`);

    // Order summary
    setEl('co-coverage', `${quote.planLabel || 'Essential'}`);
    setEl('co-vehicle', `${vtData.icon} ${vtData.label}`);
    setEl('co-rate', `$${parseFloat(quote.pricePerDay || 0).toFixed(2)}/day`);
    setEl('co-duration', `${quote.days || 0} days`);
    setEl('co-total', `$${parseFloat(quote.totalPrice || 0).toFixed(2)}`);

    // Mobile bar
    setEl('co-mob-total', `$${parseFloat(quote.totalPrice || 0).toFixed(2)}`);
    setEl('co-mob-plan', `${quote.planLabel || 'Essential'} · ${quote.days || 0} days`);
  }
  populateOrder();

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
    if (email) email.value = user.email || '';
    if (phone) phone.value = user.phone || '';
  }

  // ── Editable Trip Dates ──────────────────────────────────
  const editBtn    = document.getElementById('coTripEditBtn');
  const editForm   = document.getElementById('coTripEditForm');
  const editStart  = document.getElementById('coEditStart');
  const editEnd    = document.getElementById('coEditEnd');
  const saveBtn    = document.getElementById('coEditSave');
  const cancelBtn  = document.getElementById('coEditCancel');

  if (editBtn && editForm) {
    editBtn.addEventListener('click', () => {
      editForm.classList.toggle('show');
      // Pre-fill current dates
      if (quote.start_date) editStart.value = quote.start_date;
      if (quote.end_date) editEnd.value = quote.end_date;
      editStart.min = new Date().toISOString().split('T')[0];
    });

    cancelBtn.addEventListener('click', () => {
      editForm.classList.remove('show');
    });

    editStart.addEventListener('change', () => {
      editEnd.min = editStart.value;
      if (editEnd.value && editEnd.value <= editStart.value) {
        const d = new Date(editStart.value);
        d.setDate(d.getDate() + 1);
        editEnd.value = d.toISOString().split('T')[0];
      }
    });

    saveBtn.addEventListener('click', async () => {
      const newStart = editStart.value;
      const newEnd   = editEnd.value;
      if (!newStart || !newEnd || newEnd <= newStart) {
        showToast('Please select valid dates.', 'error');
        return;
      }

      saveBtn.textContent = 'Updating...';
      saveBtn.disabled = true;

      try {
        // Step 1: Create new quote with updated dates
        const res = await apiCall('quotes', 'POST', {
          state:        quote.state,
          start_date:   newStart,
          start_time:   quote.start_time || '09:00',
          end_date:     newEnd,
          end_time:     quote.end_time || '09:00',
          vehicle_type: quote.vehicle_type || 'car',
        });

        const newQuoteId = res.data.quote_id;
        const newDays    = res.data.days;

        // Step 2: GET the quote to fetch pricing options
        const priceRes = await apiCall(`quotes?id=${newQuoteId}`);
        const plan = quote.plan || 'essential';
        const matchedOpt = priceRes.data.options?.find(o => o.plan === plan) || priceRes.data.options?.[0];

        // Step 3: Update session with new pricing
        quote = {
          ...quote,
          quote_id:    newQuoteId,
          start_date:  newStart,
          end_date:    newEnd,
          days:        newDays,
          plan:        matchedOpt?.plan || plan,
          planLabel:   matchedOpt?.plan_label || quote.planLabel,
          pricePerDay: matchedOpt?.price_per_day || quote.pricePerDay,
          totalPrice:  matchedOpt?.total_price || (matchedOpt?.price_per_day * newDays),
        };
        sessionStorage.setItem('dsc_quote', JSON.stringify(quote));
        populateOrder();
        editForm.classList.remove('show');
        showToast('Dates updated successfully!', 'success');
      } catch (err) {
        showToast(err.message || 'Failed to update dates.', 'error');
      }

      saveBtn.textContent = 'Update Dates';
      saveBtn.disabled = false;
    });
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

  async function processPayment() {
    if (isProcessing) return;

    // Validate terms
    const termsCheck = document.getElementById('termsCheck');
    if (termsCheck && !termsCheck.checked) {
      showToast('Please agree to the Terms & Conditions', 'error');
      return;
    }

    isProcessing = true;
    payBtn.disabled = true;
    payBtn.innerHTML = '<span class="spinner"></span> Processing...';
    if (mobPayBtn) { mobPayBtn.disabled = true; mobPayBtn.innerHTML = '<span class="spinner"></span> Processing...'; }

    const resetBtn = () => {
      const btnHtml = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> PAY NOW <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
      payBtn.innerHTML = btnHtml;
      payBtn.disabled = false;
      if (mobPayBtn) {
        mobPayBtn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg> PAY NOW';
        mobPayBtn.disabled = false;
      }
      isProcessing = false;
    };

    try {
      const firstName = document.getElementById('checkoutFirstName')?.value || '';
      const lastName  = document.getElementById('checkoutLastName')?.value || '';
      const fullName  = `${firstName} ${lastName}`.trim();
      const email     = document.getElementById('checkoutEmail')?.value || '';
      const phone     = document.getElementById('checkoutPhone')?.value || '';

      if (!email) {
        showToast('Please enter your email address.', 'error');
        resetBtn();
        return;
      }

      // Refresh quote from sessionStorage in case dates were updated
      const currentQuote = JSON.parse(sessionStorage.getItem('dsc_quote') || '{}');

      // Step 1: Create PaymentIntent
      const intentRes = await apiCall('payments/create-intent', 'POST', {
        quote_id: currentQuote.quote_id,
        plan: currentQuote.plan || 'essential',
        vehicle_type: currentQuote.vehicle_type || 'car',
        email: email,
      });

      if (!intentRes.data?.client_secret) {
        throw new Error(intentRes.message || 'Failed to create payment');
      }

      // Step 2: Confirm with Stripe
      const { error, paymentIntent } = await stripe.confirmCardPayment(
        intentRes.data.client_secret,
        {
          payment_method: {
            card: card,
            billing_details: { name: fullName, email: email },
          },
        }
      );

      if (error) throw new Error(error.message);

      if (paymentIntent.status === 'succeeded') {
        // Step 3: Create policy
        const confirmRes = await apiCall('payments/confirm', 'POST', {
          payment_intent_id: paymentIntent.id,
          quote_id: currentQuote.quote_id,
          plan: currentQuote.plan || 'essential',
          vehicle_type: currentQuote.vehicle_type || 'car',
          email: email,
          name: fullName,
          phone: phone,
        });

        if (confirmRes.success) {
          if (confirmRes.data.token && confirmRes.data.customer) {
            Auth.save(confirmRes.data.token, confirmRes.data.customer);
          }
          sessionStorage.setItem('dsc_new_policy', JSON.stringify(confirmRes.data.policy));
          sessionStorage.removeItem('dsc_quote');
          window.location.href = 'payment-success.html';
        } else {
          throw new Error(confirmRes.message || 'Failed to create policy');
        }
      }
    } catch (err) {
      showToast(err.message || 'Payment failed. Please try again.', 'error');
      resetBtn();
    }
  }

  payBtn.addEventListener('click', (e) => { e.preventDefault(); processPayment(); });
  if (mobPayBtn) {
    mobPayBtn.addEventListener('click', (e) => { e.preventDefault(); processPayment(); });
  }
})();
