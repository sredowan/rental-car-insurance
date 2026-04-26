/**
 * Rental Shield - Browser Policy PDF Generator
 * Dependencies: jsPDF, jsPDF-AutoTable
 */

(function () {
  const BRAND = {
    navy: [11, 30, 61],
    blue: [30, 127, 216],
    lightBlue: [240, 247, 255],
    border: [226, 232, 240],
    text: [31, 41, 55],
    muted: [100, 116, 139],
    emerald: [5, 150, 105],
    amber: [245, 158, 11],
    red: [185, 28, 28],
  };

  const money = (value) => `AUD $${Number(value || 0).toLocaleString('en-AU', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;

  const moneyWhole = (value) => `AUD $${Number(value || 0).toLocaleString('en-AU', {
    maximumFractionDigits: 0,
  })}`;

  const safe = (value, fallback = '-') => {
    if (value === null || value === undefined || value === '') return fallback;
    return String(value);
  };

  const formatDate = (dateStr) => {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('en-AU', { day: '2-digit', month: 'short', year: 'numeric' });
  };

  const durationDays = (policyData) => {
    const explicit = Number(policyData.days || policyData.duration_days || 0);
    if (explicit > 0) return explicit;
    const start = new Date(policyData.start_date);
    const end = new Date(policyData.end_date);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 1;
    return Math.max(1, Math.ceil((end - start) / 86400000));
  };

  const loadLogo = async () => {
    const candidates = [
      'assets/images/logo.png',
      './assets/images/logo.png',
      '/assets/images/logo.png',
    ];

    for (const url of candidates) {
      try {
        const response = await fetch(url, { cache: 'no-store' });
        if (!response.ok) continue;
        const blob = await response.blob();
        return await new Promise((resolve, reject) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.onerror = reject;
          reader.readAsDataURL(blob);
        });
      } catch (error) {
        // Try the next path, then fall back to drawn branding.
      }
    }

    return null;
  };

  const drawLogoFallback = (doc, x, y) => {
    doc.setFillColor(...BRAND.lightBlue);
    doc.setDrawColor(...BRAND.border);
    doc.roundedRect(x, y, 51, 18, 3, 3, 'FD');
    doc.setFillColor(...BRAND.blue);
    doc.roundedRect(x + 4, y + 3, 12, 12, 2, 2, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7);
    doc.text('RS', x + 10, y + 11, { align: 'center' });
    doc.setTextColor(...BRAND.navy);
    doc.setFontSize(13);
    doc.text('Rental', x + 20, y + 8);
    doc.setTextColor(...BRAND.blue);
    doc.text('Shield', x + 20, y + 14);
  };

  const drawHeader = async (doc, title) => {
    const logo = await loadLogo();
    doc.setFillColor(255, 255, 255);
    doc.rect(0, 0, 210, 35, 'F');
    doc.setDrawColor(...BRAND.border);
    doc.line(14, 35, 196, 35);

    if (logo) {
      try {
        doc.addImage(logo, 'PNG', 14, 9, 55, 18);
      } catch (error) {
        drawLogoFallback(doc, 14, 8);
      }
    } else {
      drawLogoFallback(doc, 14, 8);
    }

    doc.setFillColor(...BRAND.navy);
    doc.roundedRect(130, 10, 66, 13, 6.5, 6.5, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text(title.toUpperCase(), 163, 18.5, { align: 'center' });
  };

  const drawFooter = (doc) => {
    const pageCount = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pageCount; i += 1) {
      doc.setPage(i);
      doc.setDrawColor(...BRAND.navy);
      doc.line(14, 280, 196, 280);
      doc.setTextColor(...BRAND.muted);
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(7.5);
      doc.text('Rental Shield · ABN: 19 686 732 043 · Level 25/6 Parramatta Sq, Parramatta NSW 2150', 105, 285, { align: 'center' });
      doc.text('info@rentalshield.com.au · www.rentalshield.com.au · This document is computer-generated and requires no signature.', 105, 290, { align: 'center' });
    }
  };

  const labelValue = (doc, label, value, x, y, width = 82, accent = BRAND.text) => {
    doc.setTextColor(...BRAND.muted);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.3);
    doc.text(label.toUpperCase(), x, y);
    doc.setTextColor(...accent);
    doc.setFontSize(10);
    doc.text(doc.splitTextToSize(safe(value), width), x, y + 5);
  };

  const drawInfoPanel = (doc, x, y, w, h, title, body, color) => {
    const bg = color === BRAND.amber ? [255, 251, 235] : [240, 247, 255];
    doc.setFillColor(...bg);
    doc.setDrawColor(color[0], color[1], color[2]);
    doc.roundedRect(x, y, w, h, 2.5, 2.5, 'FD');
    doc.setFillColor(...color);
    doc.rect(x, y, 1.8, h, 'F');
    doc.setTextColor(...color);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    doc.text(title, x + 6, y + 8);
    doc.setTextColor(...BRAND.text);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.2);
    doc.text(doc.splitTextToSize(body, w - 12), x + 6, y + 14);
  };

  window.generatePolicyPDF = async function (policyData = {}, userData = {}) {
    if (!window.jspdf) {
      console.error('jsPDF is not loaded.');
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'p', unit: 'mm', format: 'a4' });
    const days = durationDays(policyData);
    const name = safe(userData.full_name || userData.name || policyData.customer_name, 'Policy Holder');
    const email = safe(userData.email || policyData.customer_email, '-');
    const plan = safe(policyData.plan_label || policyData.plan, 'Essential').replace(/^./, (c) => c.toUpperCase());
    const vehicle = safe(policyData.vehicle_type, 'Car').replace(/^./, (c) => c.toUpperCase());
    const policyNumber = safe(policyData.policy_number, 'Document');

    await drawHeader(doc, 'Certificate of Insurance');

    doc.setFillColor(...BRAND.lightBlue);
    doc.setDrawColor(191, 219, 254);
    doc.roundedRect(14, 44, 182, 18, 3, 3, 'FD');
    doc.setTextColor(...BRAND.muted);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.text('POLICY NUMBER', 20, 51);
    doc.setTextColor(...BRAND.blue);
    doc.setFontSize(14);
    doc.text(policyNumber, 20, 58);
    doc.setFillColor(220, 252, 231);
    doc.roundedRect(171, 49, 17, 7, 1.5, 1.5, 'F');
    doc.setTextColor(...BRAND.emerald);
    doc.setFontSize(7);
    doc.text('ACTIVE', 179.5, 53.8, { align: 'center' });

    doc.setFillColor(255, 255, 255);
    doc.setDrawColor(...BRAND.border);
    doc.roundedRect(14, 69, 182, 54, 3, 3, 'D');
    labelValue(doc, 'Policy Holder', name, 20, 80);
    labelValue(doc, 'Email', email, 108, 80);
    labelValue(doc, 'Plan', plan, 20, 96);
    labelValue(doc, 'Vehicle Type', vehicle, 108, 96);
    labelValue(doc, 'State / Territory', safe(policyData.state), 20, 112);
    labelValue(doc, 'Coverage Period', `${formatDate(policyData.start_date)} - ${formatDate(policyData.end_date)} (${days} days)`, 108, 112);

    doc.setFillColor(...BRAND.navy);
    doc.roundedRect(14, 132, 182, 26, 3, 3, 'F');
    doc.setTextColor(170, 188, 213);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(7.5);
    doc.text('MAXIMUM COVERAGE LIMIT', 20, 142);
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(20);
    doc.text(moneyWhole(policyData.coverage_amount || 100000), 20, 153);
    doc.setFillColor(6, 95, 70);
    doc.roundedRect(165, 141, 23, 8, 2, 2, 'F');
    doc.setTextColor(52, 211, 153);
    doc.setFontSize(7.5);
    doc.text('$0 EXCESS', 176.5, 146.3, { align: 'center' });

    doc.setFillColor(255, 255, 255);
    doc.setDrawColor(...BRAND.border);
    doc.roundedRect(14, 166, 88, 34, 3, 3, 'D');
    doc.roundedRect(108, 166, 88, 34, 3, 3, 'D');
    labelValue(doc, 'Daily Rate', money(policyData.price_per_day), 20, 178, 72);
    labelValue(doc, 'Total Premium Paid', money(policyData.total_price), 20, 194, 72, BRAND.blue);
    labelValue(doc, 'Payment Reference', safe(policyData.payment_reference || policyData.payment_intent_id), 114, 178, 72);
    labelValue(doc, 'Issue Date', formatDate(policyData.created_at || new Date()), 114, 194, 72);

    drawInfoPanel(
      doc,
      14,
      209,
      182,
      22,
      'At the rental counter',
      'When staff offer Collision Damage Waiver (CDW) or Loss Damage Waiver (LDW), you may politely decline. You are already covered by Rental Shield.',
      BRAND.amber
    );

    doc.autoTable({
      startY: 239,
      margin: { left: 14, right: 14 },
      theme: 'grid',
      head: [['Included cover', 'Status']],
      body: [
        ['Collision damage up to your coverage limit', 'Included'],
        ['Theft of vehicle, windscreen, glass, tyres, undercarriage and keys', 'Included'],
        ['Admin, processing, loss-of-use and towing fees', 'Included'],
        ['All authorised drivers listed by the rental company', 'Included'],
      ],
      headStyles: { fillColor: BRAND.blue, textColor: 255, fontStyle: 'bold', fontSize: 8 },
      bodyStyles: { fontSize: 8, textColor: BRAND.text },
      columnStyles: { 0: { cellWidth: 142 }, 1: { cellWidth: 40, textColor: BRAND.emerald, fontStyle: 'bold' } },
    });

    doc.addPage();
    await drawHeader(doc, 'Policy Conditions');

    doc.autoTable({
      startY: 47,
      margin: { left: 14, right: 14 },
      theme: 'grid',
      head: [['General exclusions']],
      body: [
        ['Driving under the influence of alcohol or drugs.'],
        ['Breach of rental agreement, local traffic laws, or unauthorised drivers.'],
        ['Peer-to-peer or private rental platforms unless expressly approved.'],
        ['Personal belongings, mechanical failure, racing, competition or racetrack use.'],
        ['Incidents outside the policy period or lodged more than 30 days after the incident.'],
      ],
      headStyles: { fillColor: BRAND.red, textColor: 255, fontStyle: 'bold', fontSize: 8 },
      bodyStyles: { fontSize: 8, textColor: BRAND.text },
    });

    drawInfoPanel(
      doc,
      14,
      doc.lastAutoTable.finalY + 12,
      182,
      32,
      'How to make a claim',
      'Report the incident to the rental company immediately. Lodge your claim within 30 days via your Rental Shield dashboard. Provide the rental agreement, damage report, excess invoice or receipt, photos, and police report if applicable.',
      BRAND.blue
    );

    const termsY = doc.lastAutoTable.finalY + 55;
    doc.setTextColor(...BRAND.muted);
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.text(doc.splitTextToSize('This certificate is subject to the full Terms and Conditions and Product Disclosure Statement (PDS). By purchasing this policy, you confirm that you have read, understood and accepted those terms. This policy is governed by the laws of New South Wales, Australia.', 182), 14, termsY);

    drawFooter(doc);

    doc.save(`Rental_Shield_Policy_${policyNumber}.pdf`);
  };
})();
