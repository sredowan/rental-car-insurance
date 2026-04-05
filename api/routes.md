# DriveSafe Cover — REST API Routes
Base URL: `https://api.yourdomain.com/api/v1`
Auth: `Authorization: Bearer {jwt_token}`
Response format: `{ "success": bool, "data": {}, "message": "string" }`

---
## AUTH
| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | /auth/customer/register | No | Register customer |
| POST | /auth/customer/login | No | Customer login → JWT |
| POST | /auth/admin/login | No | Admin login → JWT + OTP |
| POST | /auth/verify-otp | No | Verify OTP code |
| POST | /auth/forgot-password | No | Send reset email |
| POST | /auth/reset-password | No | Reset with token |
| POST | /auth/logout | Yes | Revoke JWT |
| GET | /auth/me | Yes | Get current user |

## PRICING (public)
| GET | /pricing | No | Get all coverage tiers & prices |

## QUOTES
| POST | /quotes | No | Create quote → returns quote_id |
| GET | /quotes/{id} | No | Get quote by ID |
| PUT | /quotes/{id}/coverage | No | Update coverage tier |
| GET | /customer/quotes | Yes(Customer) | List customer quotes |

## POLICIES
| POST | /policies | Yes(Customer) | Convert quote to policy |
| GET | /customer/policies | Yes(Customer) | List customer policies |
| GET | /customer/policies/{id} | Yes(Customer) | Policy detail |
| DELETE | /customer/policies/{id} | Yes(Customer) | Cancel policy |
| GET | /customer/policies/{id}/pdf | Yes(Customer) | Download PDF |

## CLAIMS
| POST | /claims | Yes(Customer) | Submit new claim |
| POST | /claims/{id}/documents | Yes(Customer) | Upload documents |
| GET | /customer/claims | Yes(Customer) | List customer claims |
| GET | /customer/claims/{id} | Yes(Customer) | Claim detail + docs |

## CUSTOMER PROFILE
| GET | /customer/profile | Yes(Customer) | Get profile |
| PUT | /customer/profile | Yes(Customer) | Update profile |
| PUT | /customer/change-password | Yes(Customer) | Change password |

## ADMIN — DASHBOARD
| GET | /admin/dashboard/stats | Yes(Admin) | KPI metrics |
| GET | /admin/dashboard/revenue-chart?range=30d | Yes(Admin) | Revenue chart data |
| GET | /admin/dashboard/claims-chart | Yes(Admin) | Claims by status |
| GET | /admin/dashboard/tier-sales | Yes(Admin) | Coverage tier sales |

## ADMIN — QUOTES
| GET | /admin/quotes | Yes(Admin) | List all quotes (filterable) |
| GET | /admin/quotes/{id} | Yes(Admin) | Quote detail |
| DELETE | /admin/quotes/{id} | Yes(Admin) | Delete quote |
| POST | /admin/quotes/{id}/convert | Yes(Admin) | Convert to policy |

## ADMIN — POLICIES
| GET | /admin/policies | Yes(Admin) | List all policies |
| GET | /admin/policies/{id} | Yes(Admin) | Policy detail |
| PUT | /admin/policies/{id}/status | Yes(Admin) | Cancel/reinstate |
| GET | /admin/policies/{id}/pdf | Yes(Admin) | Download PDF |

## ADMIN — CLAIMS
| GET | /admin/claims | Yes(Admin) | List all claims |
| GET | /admin/claims/{id} | Yes(Admin) | Claim detail + docs |
| PUT | /admin/claims/{id}/status | Yes(Admin) | Approve/Deny/Review |
| PUT | /admin/claims/{id}/payout | Yes(Admin) | Set payout amount |

## ADMIN — CUSTOMERS
| GET | /admin/customers | Yes(Admin) | List customers |
| GET | /admin/customers/{id} | Yes(Admin) | Customer detail |
| PUT | /admin/customers/{id}/status | Yes(Admin) | Block/activate |

## ADMIN — REVENUE
| GET | /admin/revenue/summary?range= | Yes(Admin) | Revenue summary |
| GET | /admin/revenue/by-state | Yes(Admin) | Revenue by state |
| GET | /admin/revenue/by-tier | Yes(Admin) | Revenue by coverage tier |
| GET | /admin/revenue/monthly | Yes(Admin) | Monthly breakdown |

## ADMIN — PRICING
| GET | /admin/pricing | Yes(super_admin) | Get pricing tiers |
| PUT | /admin/pricing/{id} | Yes(super_admin) | Update tier price |

## ADMIN — USERS
| GET | /admin/users | Yes(super_admin) | List admins |
| POST | /admin/users | Yes(super_admin) | Create admin |
| PUT | /admin/users/{id} | Yes(super_admin) | Update admin |
| DELETE | /admin/users/{id} | Yes(super_admin) | Remove admin |

## ADMIN — SETTINGS
| GET | /admin/settings | Yes(Admin) | Get settings |
| PUT | /admin/settings | Yes(super_admin) | Update settings |

## ADMIN — AUDIT
| GET | /admin/audit-log | Yes(Admin) | Audit log (paginated) |

---
## Query Parameters (list endpoints)
`?page=1&per_page=25&search=&status=&state=&from=&to=&sort=created_at&order=desc`

## HTTP Status Codes
- 200 OK | 201 Created | 400 Bad Request | 401 Unauthorized
- 403 Forbidden | 404 Not Found | 422 Validation Error | 500 Server Error
