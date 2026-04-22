/**
 * Rental Shield — Coverage Plans & Pricing Engine
 * 3 Plans: Essential ($22.99) | Premium ($44.99) | Ultimate ($66.99)
 * All plans: $100,000 max coverage
 * Vehicle surcharges: Car +$0, Campervan +$3, Motorhome +$4, Bus +$5, 4x4 +$6
 *
 * NOTE: This file only handles client-side plan data & pricing display.
 * The actual server-side price calculation happens in the PHP backend.
 */

const CoveragePricing = {
  plans: {
    essential: {
      price: 22.99,
      coverage: 100000,
      label: 'Essential',
      badge: 'Starter',
      features: [
        'Collision damage up to your coverage limit',
        'Theft of vehicle',
        'Windscreen & glass',
        'Tyres, undercarriage, keys',
        'Admin & processing fees',
        'Towing costs',
        'All authorized drivers',
        'Zero excess — you pay nothing on a claim',
      ],
    },
    premium: {
      price: 44.99,
      coverage: 100000,
      label: 'Premium',
      badge: 'Best Value',
      features: [
        'Collision damage up to your coverage limit',
        'Theft of vehicle',
        'Windscreen & glass',
        'Tyres, undercarriage, keys',
        'Admin & processing fees',
        'Towing costs',
        'All authorized drivers',
        'Zero excess — you pay nothing on a claim',
        'Headlights, mirrors & exterior lights',
        'Roof & hail damage',
        'Door dents & panel damage',
        'Misfuelling damage',
        'Overseas rentals covered',
        'Priority claim processing',
      ],
    },
    ultimate: {
      price: 66.99,
      coverage: 100000,
      label: 'Ultimate',
      badge: 'Maximum Protection',
      features: [
        'Collision damage up to your coverage limit',
        'Theft of vehicle',
        'Windscreen & glass',
        'Tyres, undercarriage, keys',
        'Admin & processing fees',
        'Towing costs',
        'All authorized drivers',
        'Zero excess — you pay nothing on a claim',
        'Headlights, mirrors & exterior lights',
        'Roof & hail damage',
        'Door dents & panel damage',
        'Misfuelling damage',
        'Overseas rentals covered',
        'Priority claim processing',
        'Premium & luxury vehicle coverage',
        'Full interior damage (seats, dashboard, upholstery)',
        'Scratch & cosmetic paint damage',
        'Single vehicle rollover',
        'Water & flood damage',
        '24/7 priority claims support',
        'Dedicated claims manager',
      ],
    },
  },

  vehicleSurcharges: {
    car: {
      surcharge: 0, label: 'Car', icon: '🚗',
      covered: 'Standard sedan, hatchback, wagon',
      notCovered: 'Motorhomes, campervans, 4x4s on unsealed roads',
      exclusionBrief: 'Motorhomes, campervans, off-road & commercial vehicles',
      exclusions: [
        'Motorhome/RVs (vehicles with a built-in toilet and shower)',
        'Campervans (vehicles with sleeping berths)',
        '4x4s that are used on unsealed roads',
        'Minibuses that require a non-standard drivers licence',
        'Light trucks and commercial freight vehicles',
        'Damages from a breach of the rental agreement or local laws',
      ],
    },
    campervan: {
      surcharge: 3, label: 'Campervan', icon: '🚐',
      covered: 'Self-contained campervans, combo vans',
      notCovered: 'Only listed vehicle types are covered',
      exclusionBrief: 'Breach of rental agreement & unlisted vehicle types',
      exclusions: [
        'Only the vehicle types shown on your policy are covered',
        'Damages that resulted from a breach of the rental agreement',
        'Vehicles used for commercial purposes',
        'Damages from use on prohibited roads or terrain',
      ],
    },
    motorhome: {
      surcharge: 4, label: 'Motorhome / RV', icon: '🏠',
      covered: 'All licensed motorhomes, RVs',
      notCovered: 'Only listed vehicle types are covered',
      exclusionBrief: 'Breach of rental agreement & unlisted vehicle types',
      exclusions: [
        'Only the vehicle types shown on your policy are covered',
        'Damages that resulted from a breach of the rental agreement',
        'Vehicles used for commercial purposes',
        'Damages from use on prohibited roads or terrain',
      ],
    },
    bus: {
      surcharge: 5, label: 'Bus / Small Coach', icon: '🚌',
      covered: 'Minibuses, 12–25 seat coaches',
      notCovered: 'Only listed vehicle types are covered',
      exclusionBrief: 'Breach of rental agreement & unlisted vehicle types',
      exclusions: [
        'Only the vehicle types shown on your policy are covered',
        'Damages that resulted from a breach of the rental agreement',
        'Public transport and commercial passenger buses',
        'Damages from use on prohibited roads or terrain',
      ],
    },
    '4x4': {
      surcharge: 6, label: '4x4', icon: '🚙',
      covered: '4WD SUVs, utes, off-road capable vehicles',
      notCovered: 'Only listed vehicle types are covered',
      exclusionBrief: 'Breach of rental agreement & unlisted vehicle types',
      exclusions: [
        'Only the vehicle types shown on your policy are covered',
        'Damages that resulted from a breach of the rental agreement',
        'Military or heavily modified vehicles',
        'Damages from use on prohibited roads or terrain',
      ],
    },
  },

  defaultPlan: 'essential',
  defaultVehicle: 'car',

  // Base features shared by ALL plans (first 8)
  baseFeatureCount: 8,

  getPrice(plan, vehicleType = 'car') {
    const planData = this.plans[plan];
    const surcharge = this.vehicleSurcharges[vehicleType]?.surcharge ?? 0;
    return planData ? round2(planData.price + surcharge) : 22.99;
  },

  formatCurrency(amount) {
    return '$' + parseFloat(amount).toFixed(2);
  },

  getVehicleLabel(vehicleType) {
    return this.vehicleSurcharges[vehicleType]?.label ?? 'Car';
  },

  getVehicleIcon(vehicleType) {
    return this.vehicleSurcharges[vehicleType]?.icon ?? '🚗';
  },

  getAllPlans(vehicleType = 'car') {
    const surcharge = this.vehicleSurcharges[vehicleType]?.surcharge ?? 0;
    return Object.entries(this.plans).map(([key, data]) => ({
      key,
      price: round2(data.price + surcharge),
      ...data,
    }));
  },
};

function round2(n) { return Math.round(n * 100) / 100; }
