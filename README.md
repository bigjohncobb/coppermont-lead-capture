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
3. Configure options in the **Lead Capture** admin menu.

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


## Data Management (Admin)

The plugin includes a **Data Management** area on its admin screen (**Lead Capture** menu) for deliberate data operations:

- **Reset analytics only**: sets impression and submission counters back to zero while preserving all other settings.
- **Delete all plugin data (irreversible)**: removes saved plugin settings and analytics data permanently.

Safety controls:
- Both actions require `manage_options` capability.
- Both actions are nonce-protected.
- Full deletion additionally requires typing the exact phrase `DELETE ALL DATA`.

Use these tools with care, especially the full deletion action, because removed data cannot be recovered from within the plugin.

## Uninstall Behavior

The plugin includes `uninstall.php` and calls the shared data manager purge service to remove plugin data when uninstalled via WordPress.

## Extensibility

On successful lead submission, the plugin fires:

- `cmlc_lead_submitted( string $email, array $settings )`

Use this action to forward leads to CRM or email marketing tools.
