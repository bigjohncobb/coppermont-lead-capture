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

By default, uninstalling the plugin preserves plugin data (`keep_data_on_uninstall` defaults to enabled).

Admins can change this in **Settings > Lead Capture > Data & Privacy** by disabling **Keep plugin data when uninstalling** before deleting the plugin.

When data deletion is enabled, `uninstall.php` removes plugin-owned data classes:
- `cmlc_settings` option data.
- Known plugin custom tables (`{$wpdb->prefix}cmlc_analytics`, `{$wpdb->prefix}cmlc_leads`, `{$wpdb->prefix}cmlc_campaigns`) if they exist.
- Campaign CPT content for `cmlc_campaign` posts (including associated post meta via WordPress deletion routines).
- Plugin transients and scheduled hooks used by plugin background processing.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
