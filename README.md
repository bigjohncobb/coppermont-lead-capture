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
3. Configure options in **Settings > Lead Capture**.

## Shortcode

```text
[coppermont_infobar]
[coppermont_infobar headline="Get updates" body="Join our list" button="Subscribe"]
```

## Security Notes

- Direct file access is blocked in PHP entry files with `ABSPATH` checks.
- AJAX handlers enforce nonce verification.
- User input is sanitized server-side before persistence.
- Admin settings rendering is protected by capability checks (`manage_options`).

## Uninstall Behavior

A new admin setting, `keep_data_on_uninstall` (enabled by default), controls uninstall data lifecycle behavior:

- **Enabled (`true`, default):** Uninstall keeps all plugin-owned data in place.
- **Disabled (`false`):** Uninstall permanently purges all plugin-owned data through a centralized data manager, including plugin options/settings, analytics values, known transients, and any custom tables/entities declared by the plugin.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
