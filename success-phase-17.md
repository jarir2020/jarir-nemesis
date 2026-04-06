# Phase 17 — Social Authentication ✓

**Completed:** 2026-04-06  
**Tests:** 852 total / 852 passed (46 new in Phase 17)  
**Branch:** main

---

## What Was Built

### OAuth2 Provider System
Full OAuth2 provider abstraction with driver factory pattern.

| File | Purpose |
|---|---|
| `src/Auth/Social/SocialProviderInterface.php` | Contract: `getAuthorizationUrl()`, `getState()`, `getAccessToken()`, `getUser()` |
| `src/Auth/Social/AbstractSocialProvider.php` | Base class — curl GET/POST helpers, state generation, scope merging |
| `src/Auth/Social/SocialUser.php` | Readonly DTO — `id, name, email, avatar, token, provider, raw` + `fromArray()` factory |

### Providers
| Provider | File | Notes |
|---|---|---|
| Google | `Providers/GoogleProvider.php` | OpenID Connect scopes: openid, email, profile |
| GitHub | `Providers/GitHubProvider.php` | Email fallback via `/user/emails` for hidden emails |
| X (Twitter) | `Providers/XProvider.php` | OAuth2 PKCE — code_verifier + S256 challenge |
| Facebook | `Providers/FacebookProvider.php` | Token passed as URL param (not Bearer header) |
| LinkedIn | `Providers/LinkedInProvider.php` | OpenID Connect userinfo endpoint |

### SocialAuth Manager
`src/Auth/Social/SocialAuth.php` — static facade:
- `driver(string $name): SocialProviderInterface` — cached driver factory
- `handleCallback(driver, code, state, expectedState)` — state validation + token exchange
- `findOrCreateUser(SocialUser): array` — find by social link → email → create new; returns JWT pair
- `reset()` — clears driver cache (for tests)
- Auto-creates `social_accounts` table (driver-aware DDL: SQLite / MySQL)

### Socialable Trait
`src/Auth/Traits/Socialable.php` — mix into User models:
- `socialAccounts()` — list linked providers
- `hasSocial(string $provider): bool`
- `linkSocial(SocialUser)` — upsert link
- `unlinkSocial(string $provider)`
- `static loginWithSocial(driver, code, state, expectedState): array` — full flow
- `static socialRedirect(driver, scopes): [url, state]` — generate redirect

### Config & CLI
- `config/social.php` — per-driver `client_id / client_secret / redirect` (env-backed)
- `bin/nemesis make:social-auth {Driver}` — scaffolds controller + appends route hints to `routes/api.php`

### TestRunner Enhancement
- Added `expectExceptionMessageContains()` support to `TestRunner.php` + `TestCase.php`
- Added `assertStringContains()` alias

---

## Database Schema

```sql
-- social_accounts (auto-created on first use)
CREATE TABLE social_accounts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    provider    TEXT NOT NULL,          -- google, github, x, facebook, linkedin
    provider_id TEXT NOT NULL,          -- provider's user ID
    avatar      TEXT NOT NULL DEFAULT '',
    token       TEXT NOT NULL DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(provider, provider_id)
)
```

---

## Usage

```php
// 1. Redirect user to provider
$provider = SocialAuth::driver('google');
$_SESSION['oauth_state'] = $provider->getState();
header('Location: ' . $provider->getAuthorizationUrl());

// 2. Handle callback
$tokens = SocialAuth::handleCallback('google', $_GET['code'], $_GET['state'], $_SESSION['oauth_state']);
// $tokens = ['access' => '...', 'refresh' => '...', 'expires_in' => 900, 'token_type' => 'Bearer', 'user_id' => 5]

// 3. Via Socialable trait on User model
class User extends Model { use Socialable; }
[$url, $state] = User::socialRedirect('github');
$tokens = User::loginWithSocial('github', $code, $state, $expectedState);

// 4. CLI scaffold
php nemesis make:social-auth google
```
