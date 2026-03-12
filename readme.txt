=== Coppermont Lead Capture ===
Contributors: coppermont
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 0.1.1

Lead generation infobar and popup-style trigger engine with scheduling, targeting, repetition control, and analytics.

== Description ==
Coppermont Lead Capture provides a mobile-responsive lead capture infobar with conversion-oriented trigger controls and analytics.

Key capabilities:
- Slide-in bottom infobar with email capture.
- Scroll, time delay, and exit-intent triggers.
- Referral detection and page-level targeting.
- Scheduler windows and repetition control.
- Analytics counters for impressions (times shown) and submissions.

== Shortcodes ==
Use `[coppermont_infobar]` to embed a lead capture form inline.

Optional attributes:
- `headline`
- `body`
- `button`

== Security ==
- AJAX endpoints require a nonce.
- User inputs are sanitized before saving.
- Admin settings are protected by `manage_options` capability checks.


== Data Management ==
The plugin admin page includes a **Data Management** section with two deliberate maintenance actions:

- **Reset analytics only**
- **Delete all plugin data (irreversible)**

Safety protections:
- Requires `manage_options` capability.
- Actions use nonce verification.
- Full deletion requires typing `DELETE ALL DATA` to confirm.

These tools are intentionally destructive. Use full deletion only when you are certain all plugin data should be permanently removed.

== Uninstall ==
On uninstall, plugin data is deleted via `uninstall.php` using the shared data manager service.
