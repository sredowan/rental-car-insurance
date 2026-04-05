/**
 * DriveSafe Cover — Quote Calculation Engine
 * Pricing: $9.96/day (4k) → $16.79/day (8k)
 * RULE: Price is NEVER shown on the form — only on the result page
 *
 * NOTE: This file only handles the HOMEPAGE quote form UX (date validation, duration chip).
 * The actual quote submission (POST /api/quotes) and result page rendering
 * are handled by api.js which calls the real backend API.
 */

const CoveragePricing = {
  tiers: {
    4000: { price: 9.96,  increment: 0.00, label: '$4,000' },
    5000: { price: 11.27, increment: 1.31, label: '$5,000' },
    6000: { price: 14.42, increment: 3.15, label: '$6,000' },
    7000: { price: 15.48, increment: 1.06, label: '$7,000' },
    8000: { price: 16.79, increment: 1.31, label: '$8,000' },
  },
  defaultCoverage: 4000,

  getPrice(coverage) {
    return this.tiers[coverage]?.price ?? 9.96;
  },

  formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
  },

  incrementLabel(coverage) {
    const inc = this.tiers[coverage]?.increment ?? 0;
    return inc === 0 ? 'Base price' : `+${this.formatCurrency(inc)}/day`;
  },

  getAllTiers() {
    return Object.entries(this.tiers).map(([limit, data]) => ({
      limit: parseInt(limit),
      ...data,
    }));
  },
};
