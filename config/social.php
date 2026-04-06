<?php
// Nemesis 6.0 | Phase 17 — Social Auth: provider config | 2026-04-06

/**
 * Social OAuth provider configuration.
 *
 * Each driver needs: client_id, client_secret, redirect.
 * Values fall back to environment variables automatically if this file
 * doesn't define them (e.g. GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT).
 *
 * Set credentials via .env or directly here (never commit real secrets).
 */
return [

    'google' => [
        'client_id'     => (string) (getenv('GOOGLE_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('GOOGLE_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('GOOGLE_REDIRECT')      ?: ''),
    ],

    'github' => [
        'client_id'     => (string) (getenv('GITHUB_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('GITHUB_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('GITHUB_REDIRECT')      ?: ''),
    ],

    'x' => [
        'client_id'     => (string) (getenv('X_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('X_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('X_REDIRECT')      ?: ''),
    ],

    // Alias 'twitter' → same credentials as 'x'
    'twitter' => [
        'client_id'     => (string) (getenv('X_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('X_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('X_REDIRECT')      ?: ''),
    ],

    'facebook' => [
        'client_id'     => (string) (getenv('FACEBOOK_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('FACEBOOK_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('FACEBOOK_REDIRECT')      ?: ''),
    ],

    'linkedin' => [
        'client_id'     => (string) (getenv('LINKEDIN_CLIENT_ID')     ?: ''),
        'client_secret' => (string) (getenv('LINKEDIN_CLIENT_SECRET') ?: ''),
        'redirect'      => (string) (getenv('LINKEDIN_REDIRECT')      ?: ''),
    ],

];
