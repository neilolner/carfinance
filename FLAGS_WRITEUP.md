# Scenario 4: Feature Flags

## The problem

Feature access was decided in two places, and they disagreed. `FeatureController::assign` checked the global `enabled` flag, with a comment claiming the middleware did the same. It didn't: the middleware only checked assignment, so a globally disabled feature was still reachable by any assigned user.

## Approach

I worked test-first in two stages as per my Claude.md, agreeing a plan before each one.

**Stage 1: tests.** I added unit tests covering all four combinations of enabled and assigned for a new `Feature::isAccessibleBy()` method. I also added feature tests for the reported bug, unknown features and users, repeated assignment, and assignment while disabled. Run in the container, 6 tests failed and 10 passed, as predicted. Each failure was either the bug or behaviour not yet built.

**Stage 2: fix.**

- `Feature::isAccessibleBy(User $user)` is now the only place that decides access: `enabled && assigned`. It is the one place to extend the rules later.
- `FeatureEnabled` middleware: looks up the feature (404 if unknown) and the user (403 if missing or unknown), then calls `isAccessibleBy()`. The separate pivot query and the BUG/TODO comments are gone.
- `FeatureController::assign`: no longer refuses disabled features. Assignment and access are separate concerns, so testers can be lined up before a feature is switched on.
- Small additions: `scopeEnabled()` and `User::hasFeature(string $key)`.

All 31 tests pass. Against the seeded MySQL data, the beta user gets 200 and the regular user 403.
