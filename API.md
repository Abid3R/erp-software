# API

> **Stub.** The ERP's primary interface is the Filament admin panel (server-
> rendered, Livewire). A programmatic API surface is defined here **as modules
> are built**, not pre-invented. This file is a placeholder to be filled in from
> Phase 6 onward. No fake/placeholder endpoints are documented as if real (spec #50).

## Planned surface (subject to design when implemented)

- **Health** (Phase 3): `GET /health`, `GET /health/database` — liveness + DB check.
- **Auth**: Laravel session auth for the panel; token auth (Sanctum) only if a
  headless client is actually required. Not added speculatively.
- **Domain endpoints**: introduced per module with the same server-side
  authorization, company scoping, and validation as the UI — the UI and any API
  share the Actions/Domain layer, so business rules can never diverge.

## Conventions (when added)

- Auth required; permissions enforced by the same Policies as the panel.
- Company context derived server-side; never accepted from the client.
- Stable error codes (see [ARCHITECTURE.md](ARCHITECTURE.md#error-taxonomy)).
- Server-side pagination; no unbounded collections.
