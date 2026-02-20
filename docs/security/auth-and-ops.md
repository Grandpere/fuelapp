# Security Auth And Ops Runbook

## 1) Current authentication strategy
- UI (`/ui/*`): session authentication via local email/password (`LoginFormAuthenticator`).
- API (`/api/*`): stateless JWT Bearer authentication (`ApiTokenAuthenticator`).
- OIDC SSO (`/ui/login/oidc/*`): provider-agnostic OIDC code flow for UI login.
- Public endpoints:
- `POST /api/login`
- `/ui/login`
- `/ui/login/oidc/{provider}`
- `/ui/login/oidc/{provider}/callback`

## 2) Authorization perimeter
- Ownership model:
- Users own `receipts`.
- `stations` are shared entities, but visibility is restricted through linked owned receipts.
- Enforcement points:
- Repository filters (`ReceiptRepository`, `StationRepository`).
- Voters (`ReceiptVoter`, `StationVoter`) on read/delete paths.
- Access control (`config/packages/security.yaml`) protects `/ui/*` and `/api/*` with `ROLE_USER`.

## 3) Security-sensitive variables and rotation points
- Application/session:
- `APP_SECRET` (Symfony app secret).
- API JWT:
- `JWT_SECRET` (HS256 signing key, must be long/high entropy).
- `JWT_TTL_SECONDS` (token lifetime).
- OIDC clients:
- `OIDC_AUTH0_CLIENT_SECRET`
- `OIDC_GOOGLE_CLIENT_SECRET`
- `OIDC_MICROSOFT_CLIENT_SECRET`
- Mercure:
- `MERCURE_JWT_SECRET` (publisher/subscriber token signing key).
- Infrastructure credentials (also rotate):
- `POSTGRES_PASSWORD`
- `RABBITMQ_DEFAULT_PASS`
- `REDIS_PASSWORD` (if enabled in runtime)

## 4) Rotation procedure (minimum)
1. Generate new strong secrets for `APP_SECRET`, `JWT_SECRET`, `MERCURE_JWT_SECRET`.
2. Update secrets in environment management (not in committed files for prod).
3. Restart app/hub/services.
4. Invalidate old API tokens naturally (or force by changing `JWT_SECRET`).
5. Verify:
- UI login works.
- `POST /api/login` issues valid token.
- protected `/api/*` returns `401` without token and `200/404` with valid token.

## 5) Local checklist
- Docker stack up (`make up`).
- Local user exists (`make user-create EMAIL=... PASSWORD=...`).
- `.env` has non-empty `APP_SECRET`, `JWT_SECRET`.
- UI login reachable at `/ui/login`.
- JWT flow validated via `/api/login`.
- If OIDC is enabled for a provider:
- verify redirect to provider works from `/ui/login`.
- verify callback logs user in and links identity (`user_identities` row).

## 6) Dev/staging checklist
- Never use default placeholder secrets.
- Secrets provided by CI/CD or platform secret manager.
- `APP_ENV=prod`-like behavior for security tests where applicable.
- Ensure only required ports are exposed publicly.
- Run security regression suites:
- `make phpunit-integration`
- `make phpunit-functional`

## 7) Production checklist (baseline)
- Secrets managed in vault/secret manager, not `.env` in repo.
- `APP_DEBUG=0`.
- HTTPS enforced at ingress/reverse proxy.
- Strict CORS policy (no wildcard in production).
- JWT secret rotation policy defined (periodic + emergency).
- Operational runbook includes:
- account bootstrap/disable process
- incident token revocation procedure
- backup/restore tested for auth-related data (`users`, owned resources)
