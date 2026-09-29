---
description: "Use when designing or changing API endpoints, JSON responses, webhooks, or external client contracts in this Laravel application."
---
# API Guidelines

- This application has no general REST API today. The back office uses Laravel routes and Inertia pages; do not add a parallel JSON API for it. Confirm the required client and contract before introducing one.
- Public guest flows are web routes scoped by the tenant subdomain, not an API. Preserve their opaque-token URLs and tenant isolation; never trust a client-supplied tenant identifier.
- `bootstrap/app.php` enables JSON exception rendering for `api/*` paths and requests that expect JSON. This does not register an API route file; routes are currently registered through `routes/web.php`.
- Keep response behavior explicit: Inertia pages and redirects for browser workflows, JSON only for a defined machine-client contract. For rate-limited browser flows, preserve `RateLimitedResponse`; JSON callers should receive Laravel's JSON throttling response.
- Validate input with Form Requests and authorize in `authorize()` before validation. Use Policies/Gates for resource access, and keep business logic in Actions or Services rather than controllers.
- For tenant-owned data, resolve membership and initialize tenancy before model binding. Cross-tenant resources must remain indistinguishable from missing resources (404). See [CLAUDE.md](../../CLAUDE.md) and [SECURITY.md](../../SECURITY.md).
- Define named throttles for abuse-prone endpoints. For webhooks, use the provider's signature verification, exempt only the exact webhook path from CSRF when required, and test valid and invalid signatures. See `routes/web.php` and `tests/Feature/Billing/StripeWebhookTest.php`.
- Add feature tests for authentication/authorization, validation, status and response shape, throttling, and tenant isolation as relevant. Run focused tests with `php artisan test <test-file>`; project-wide checks are listed in `composer.json`.