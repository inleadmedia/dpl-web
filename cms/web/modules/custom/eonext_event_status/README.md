# EO Next Event Status

Custom event ribbons (badges) for DPL CMS webmaster libraries.

## What it does

- Ribbon text and colour on **event series** and **event instances**
- Ribbons on event pages and list teasers (JS + CSS library)
- **`ribbon`** object on existing **`GET /api/v1/events`** responses

Requires DPL CMS with `dpl_event`, `recurring_events`, and `color_field`.

## Installation

1. Upload to `web/modules/custom/eonext_event_status/`
2. Enable at **Extend**, or:

   ```bash
   drush en eonext_event_status -y
   drush cr
   ```

3. On upgrade from an older copy, run **`/update.php`**

Enable installs module-owned config only (ribbon field storages and instances). It does **not** run a full site configuration import.

**If the module was already enabled** before upgrading the zip, run **`/update.php`** once, or disable and re-enable the module at **Extend**, then clear caches.

Library sites do not have **Manage fields** (`field_ui` is disabled) — verify on an event edit form instead.

## Post-install checklist

| Step | Action |
| --- | --- |
| Clear caches | **Configuration → Development → Performance**, or `drush cr` |
| Install fields (no Drush) | Open any event series edit form — fields install automatically. Optional: **Configuration → System → Event status** after cache clear |
| Verify fields | Same page shows how many field configs are installed |
| Test form | Edit an event series — **Ribbon** section in the right sidebar (below Tagging) with HTML5 colour picker |
| Test frontend | Open a public event page — ribbon appears on the hero image (top-right corner) |
| Test REST | `GET /api/v1/events` — events with a ribbon include `"ribbon": { "text", "color" }` |

Ribbon widgets are added by `hook_entity_form_display_alter()` in a sidebar **Ribbon** group — they do **not** appear under **Manage form display**. Do not add them there by hand (pins DPL displays in `config_ignore_auto`).

If **Ribbon** is missing, upload the latest module zip, clear caches once, then open an event edit form again (fields install on that request). Optional admin page: `/admin/config/system/eonext-event-status` (requires cache clear after upload). Check **Reports → Recent log messages** if it still fails.

If ribbon values **disappear after Save**, upload the latest module zip and clear caches — older copies did not sync runtime form widgets back to the form display used on submit.

If the admin **Ribbon** fields are filled in but the public event page shows no ribbon, clear caches and reload the event page. Confirm the event is **published** — unpublished events redirect visitors to `/arrangementer` (the event list), not the event page. Also confirm **Ribbon text** is saved and, for recurring events, **Apply to all event instances** is checked (or set ribbon text on the individual instance). Reinstalling/uninstalling the module removes ribbon field data; you must enter ribbon text again after a full reinstall.

## Changes made by the module

### Fields

On **event series** and **event instances**:

- **`field_ribbon_text`** — label on the ribbon
- **`field_ribbon_color`** — hex colour (colour picker)

On **event series** only:

- **`field_ribbon_instances_apply`** — use series ribbon on all instances unless an instance overrides

### Forms

Ribbon fields are placed at the bottom of event edit forms at render time (not stored in DPL form displays).

- **Event series** — Ribbon Text, Ribbon Color, Apply to all event instances
- **Event instance** — Ribbon Text, Ribbon Color (overrides series when set)

### REST

Decorates DPL’s event REST mapper. When configured:

```json
"ribbon": {
  "text": "Udsolgt",
  "color": "#c0392b"
}
```

Events without a ribbon omit the `ribbon` key.

### Configuration management

Follows the [DPL webmaster module pattern](https://github.com/danskernesdigitalebibliotek/dpl-web/blob/develop/docs/cms/webmaster-modules.md): owned config in `config/sync/`, merged on platform deploy via `OverlayConfigEventSubscriber`.

On enable and post-update, `_eonext_event_status_install_owned_config()` writes only this module's field config into active storage.
