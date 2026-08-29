# Yii3 Voyti Lockout Changelog

## 1.0.2 - August 29, 2026

- Bug: `FailedAttemptsStore::getRetryAfterSeconds()` now returns the wait time actually remaining,
  counting down as time passes since the last failure, instead of returning the same full delay for as
  long as the attempt count stays cached.

## 1.0.1 August 20, 2026

- Chg: Replace the hard lockout on login and registration attempts with a progressive delay that
  doubles on each failure, capped at 1 hour for login and 10 minutes for registration - no attempt
  count locks an IP out permanently.
- Chg: The delay now starts at 1 second from the very first recorded failure, per OWASP's
  Authentication Cheat Sheet recommendation. There is no more "free attempts" grace threshold, so
  `loginMaxAttempts`/`registrationMaxAttempts` have been removed from `LockoutConfig`.
- Chg: Rename the `loginWindowSeconds`/`registrationWindowSeconds` config options to
  `loginMinRetentionSeconds`/`registrationMinRetentionSeconds` to reflect what they actually control:
  a floor on how long a failed-attempt count is remembered, not a fixed lockout window.

## 1.0.0 August 20, 2026

- Initial release.
