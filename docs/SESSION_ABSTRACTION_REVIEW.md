# Session abstraction review

Phase 6 reviewed Veldora's `SessionDriverInterface`, `ArrayDriver`, and
`FileDriver` against Nemesis's current session implementation. No session
driver abstraction was imported because there is no concrete non-PHP backend
requirement in the current Nemesis scope.

Nemesis already provides:

- `SessionConfig` for driver, lifetime, cookie, same-site, and project-local
  save-path settings.
- `StartSession` middleware and explicit termination via `session_write_close()`.
- flash data, old input, pull/reflash/keep behavior, CSRF token management, and
  session-path regression coverage.

Before adding a driver contract, Nemesis must specify the backend and its
operational semantics. The minimum contract should cover session ID
regeneration and destruction, serialization, locking/concurrent writes, flash
aging, garbage collection, cookie flags, failure behavior, and migration from
PHP's native session storage. A future implementation should use disposable
array/file fixtures and rerun the existing session path, configuration, flash,
and security tests before release.

Decision: retain the current native-session implementation and defer this
phase until a concrete backend such as Redis or a database session store is
required.
