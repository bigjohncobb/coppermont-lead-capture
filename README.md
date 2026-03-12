# Coppermont Lead Capture

Coppermont Lead Capture is a lightweight WordPress plugin for lead generation using a bottom slide-in infobar and shortcode embeds.

## Features

- Bottom infobar email capture module with slide-in animation.
- Trigger support:
  - Scroll percentage trigger.
  - Time-delay trigger.
  - Exit-intent trigger.
- Targeting support:
  - Page-level include/exclude targeting.
  - Referral detection (allow-list domains).
  - Time scheduler window (start/end).
- Repetition control:
  - Max views per visitor.
  - Cooldown after dismiss.
- Analytics:
  - Impression count (times shown).
  - Submission count.
- Shortcode support:
  - `[coppermont_infobar]`

## Installation

1. Upload the plugin folder to `wp-content/plugins/coppermont-lead-capture`.
2. Activate **Coppermont Lead Capture** from **Plugins**.
3. Configure options in **Lead Capture** (top-level admin menu).

## Shortcode

```text
[coppermont_infobar]
[coppermont_infobar headline="Get updates" body="Join our list" button="Subscribe"]
```


## Data Management (Admin)

Under **Lead Capture > Data Management**, administrators with `manage_options` can run two nonce-protected cleanup actions:

- **Reset analytics only**: sets impressions and submissions back to zero while preserving all configuration.
- **Delete all plugin data (irreversible)**: permanently removes all plugin settings and analytics.

The full purge requires typing the confirmation phrase `DELETE ALL DATA` and should only be used for deliberate data removal.

## Security Notes

- Direct file access is blocked in PHP entry files with `ABSPATH` checks.
- AJAX handlers enforce nonce verification.
- User input is sanitized server-side before persistence.
- Admin settings rendering is protected by capability checks (`manage_options`).

## Uninstall Behavior

The plugin includes `uninstall.php` and uses the same centralized data manager service as the admin purge tool to remove all plugin data.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
