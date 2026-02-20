# OIDC Provider Catalog And Conventions

## 1) Supported provider keys
- `auth0`
- `google`
- `microsoft`

These keys are stable identifiers used in:
- env vars (`OIDC_<PROVIDER>_*`)
- login routes (`/ui/login/oidc/{provider}`)
- identity linking (`user_identities.provider`)

## 2) Provider config contract
Each provider follows the same config shape:

- `enabled` (bool)
- `label` (string)
- `issuer` (string, base issuer URL)
- `client_id` (string)
- `client_secret` (string)
- `scopes` (list<string>)

Current default scopes:
- `openid`
- `profile`
- `email`

## 3) Claims mapping conventions
Canonical mapped claims:
- `sub` -> external unique subject (required)
- `email` -> normalized lowercase email (optional in protocol, required by our linker for first link)
- `name` -> display name (optional)
- `picture` -> avatar URL (optional)

## 4) Minimal required claims and fallback rules
- Required for successful callback:
- `sub`
- Required for first-time link/create in current implementation:
- `email`

Fallback behavior:
- Existing (`provider`, `sub`) identity: login succeeds even if `email` is missing.
- No existing identity + missing `email`: login rejected with explicit failure.
- No existing identity + email exists:
- link to existing local user by email, otherwise create local user then link identity.

## 5) Provider-specific notes
- Auth0:
- issuer format usually `https://<tenant>.auth0.com`
- Google:
- issuer `https://accounts.google.com`
- callback must be declared exactly in Google OAuth credentials
- Microsoft:
- recommended issuer baseline `https://login.microsoftonline.com/common/v2.0`
- tenant-specific issuer can be used later if needed

## 6) Onboarding checklist for a new provider
1. Add provider entry in `config/services.yaml` (`app.oidc_providers`).
2. Add env vars in `.env` / deployment secrets:
- `OIDC_<PROVIDER>_ENABLED`
- `OIDC_<PROVIDER>_ISSUER`
- `OIDC_<PROVIDER>_CLIENT_ID`
- `OIDC_<PROVIDER>_CLIENT_SECRET`
3. Register callback URL at provider side:
- `http://localhost:<port>/ui/login/oidc/<provider>/callback` (dev)
4. Enable provider (`..._ENABLED=1`) in local or target environment.
5. Validate flow:
- login page shows provider button
- redirect to provider works
- callback logs in user
- row created in `user_identities`
6. Add/adjust tests if provider behavior differs from catalog assumptions.

## 7) Operational guardrails
- Never commit provider secrets in `.env` versioned files.
- Keep secrets in `.env.local` (dev) or secret manager (staging/prod).
- Rotate `client_secret` on leak suspicion.
- Keep provider key stable once identities exist (data integrity in `user_identities`).
