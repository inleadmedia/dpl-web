# EO NEXT Editorial Search

Drupal module for editorial (web) search in DPL CMS. It extends the shared
`content_events` Search API index, exposes a REST API for React autosuggest and
related content, and provides a themed editorial search page at `/search/web`.

## What this module does

### Editorial search page (`/search/web`)

Uses the DPL core View `editorial_search` (from `cms/config/sync`) with Facets
for categories, tags, and content type. The module supplies:

- Twig overrides for the view, facet blocks, and exposed form
- Facet AJAX behaviour and editorial-specific sorting / “show past events”
- SCSS/CSS for the page layout

### REST API

`GET /api/v1/editorial-search`

| Parameter | Description |
| --- | --- |
| `q` | Fulltext query (required unless `material` is set) |
| `material` | Work ID; finds articles referencing the work in `field_material` |
| `page` | Zero-based page number (default `0`) |
| `page_size` | Page size (default `10`, max `100`) |
| `f[]` | Facet filters, same format as `/search/web`. Repeat the parameter for multiple values, e.g. `f[]=content_type:article&f[]=content_type:page` |
| `entity_type` | Filter by entity type: `node` or `eventseries` |
| `show_past_events` | Set to `1` to include past event series |

Anonymous and authenticated users are granted REST access on install.

### React integration (standalone bundle)

When editorial search is enabled, the module attaches `js/editorial.bundle.js` to
DPL React apps via `hook_preprocess_dpl_react_app()`. This adds editorial
autosuggest and related-content UI without changes to the main DPL `react/` apps.

HTML attributes on DPL React app containers:

| Attribute | Purpose |
| --- | --- |
| `data-eonext-editorial-overlay="true"` | Header autosuggest overlay |
| `data-editorial-search="true"` | Search results + material related content |

Settings are passed through `drupalSettings.eonextEditorialSearch`. Editorial
suggestions use facet filters for **articles**, **pages**, and **events** by default
(`content_type:article`, `content_type:page`, `content_type:default`). Material
related content stays articles-only.

**Note:** Do not use a patched `dpl-react` build that injects editorial content
into `search-header.js` via `data-autosuggest-editorial` — that duplicates the
module overlay. Use upstream DPL React builds only.

### Search API changes

On the shared **`content_events`** index the module adds:

- Facet/sort fields: `content_type`, `categories`, `tags`, `sort_date`, `sort_title`
- Fulltext field: `editorial_content` (via custom processor)
- Event fields: `event_has_upcoming`, editorial event date processor
- Excludes `go_article` from the index datasource

Custom processors live under `src/Plugin/search_api/processor/`.

### Article `field_material`

Optional config adds `field_material` on articles. A queue worker syncs work IDs
from material grid paragraphs into that field (used by the `material` REST
parameter and material-page related content).

---

## Requirements

- DPL CMS with core config imported (`views.view.editorial_search`, Search API
  index `content_events`, etc.)
- Module dependencies listed in `eonext_editorial_search.info.yml`

Enable **after** DPL core configuration is present (run `drush deploy` or
`drush cim` on a fresh site before enabling the module).

---

## Installation

1. Place the module in `web/modules/custom/eonext_editorial_search/` (folder
   name must match the machine name used in the `.info.yml` file).

2. Ensure DPL core config is imported:

   ```bash
   drush deploy -y
   ```

   On local DDEV, if `drush cim` fails on `varnish_purger`, enable the module
   first, then import:

   ```bash
   drush en varnish_purger -y
   drush cim -y
   ```

3. Enable the module:

   ```bash
   drush en eonext_editorial_search -y
   drush cr
   ```

4. **Reindex Search API** (required — install only *marks* items for reindex):

   ```bash
   drush search-api:index content_events
   ```

5. **Process article material sync** (if `field_material` was installed):

   ```bash
   drush eonext-editorial-search:sync-article-materials --process
   # or
   drush queue:run eonext_editorial_search_article_material_sync
   ```

---

## Post-install checklist

| Step | Command / action | When |
| --- | --- | --- |
| Reindex editorial search | `drush search-api:index content_events` | After every install or index config change |
| Sync article materials | `drush eonext-editorial-search:sync-article-materials --process` | After install or bulk article imports |
| Run deploy hooks | `drush deploy -y` | After platform upgrades (runs `*.deploy.php`) |
| Clear caches | `drush cr` | After enabling or config changes |

Install and deploy hooks call `$index->reindex()`, which marks content in the
tracker but **does not** run indexing. Always run `search-api:index` yourself
(or rely on cron processing the Search API queue).

---

## Configuration

### Enable / disable

**Configuration → DPL Library → General settings** (or
`/admin/config/dpl-library-agency/general-settings`)

Checkbox: **Enable Editorial Search** (`eonext_editorial_search.settings`)

### Module-owned config (`config/sync/`)

This module follows the [DPL webmaster module configuration
pattern](https://github.com/danskernesdigitalebibliotek/dpl-web/blob/develop/docs/cms/webmaster-modules.md).
Its configuration lives in `config/sync/` and `OverlayConfigEventSubscriber`
adds it to the storage DPL CMS imports on every release, so the platform sees
it as expected configuration instead of deleting it:

- Facets: `editorial_categories`, `editorial_content_type`, `editorial_tags`
- Facet source for the editorial search view page
- REST resource config
- `field_material` field storage and instance on articles
- `search_api.index.content_events` (see below)

The import itself is run by `eonext_editorial_search_update_config()`, called
from `hook_modules_installed()` on a fresh install and from
`hook_post_update_NAME()` on an existing site. Update hooks only release
configuration back to DPL CMS; they must not import. Drupal runs every module's
update hooks before any post update hook, so deferring the import to the post
update phase guarantees all three modules have finished releasing their
configuration before the importer takes its snapshot. Importing from an update
hook would snapshot the site while a later module still holds objects pinned,
and write those stale objects straight back.

### Site-owned config (`config/install/`)

`eonext_editorial_search.settings` holds the library's own choices, so it is
installed once and then excluded from configuration import through
`config_ignore_auto`. A platform release will never overwrite it.

### Shared DPL config

The View `editorial_search`, path `/search/web`, and the base Search API setup
belong to DPL CMS. Editorial search needs extra fields and processors on the
shared `content_events` index, so `config/sync/` carries a **copy** of
`search_api.index.content_events`.

> **Maintenance:** because it is a copy, it silently reverts upstream changes.
> Diff it against `cms/config/sync/search_api.index.content_events.yml` after
> each DPL release and re-apply their changes. DPL changed that file three
> times in the 18 months to August 2026, so this is roughly a twice-a-year job.

Two things the module deliberately does *not* change:

- **Article form display** - `field_material` is placed by
  `hook_entity_form_display_alter()` at render time. DPL reworks its event and
  article displays every few releases, so editing them would lose those reworks
  and pin the display in `config_ignore_auto`.
- **User roles** - the REST endpoint drops its generated permission
  requirement in `RouteSubscriber` instead of granting
  `restful get eonext_editorial_search:editorial_search` on DPL's anonymous and
  authenticated roles.

GO articles are filtered out per query rather than excluded from the shared
index, so DPL's own search keeps returning what DPL expects.

---

## How configuration is applied

| Mechanism | Purpose |
| --- | --- |
| `config/sync/` + overlay subscriber | Module-owned facets, REST, fields, and the shared index copy |
| `config/install/` | The library's own settings, excluded from import |
| `hook_modules_installed()` / `hook_post_update_NAME()` | Trigger the configuration import |
| `hook_update_N()` | Release configuration held from earlier module versions |
| `*.deploy.php` | Content migrations after platform `drush deploy` |
| PHP in `.module` | View query alters, form display injection, theming, React attachment |

---

## Deploy hooks

Run automatically as part of `drush deploy` (`deploy:hook`):

| Hook | Purpose |
| --- | --- |
| `eonext_editorial_search_deploy_sync_article_materials` | Queue `field_material` sync for all articles |

The hooks that used to rebuild the `content_events` index on every deployment
are gone. The index is shipped in `config/sync/` and applied by the platform's
own configuration import.

After deploy, run:

```bash
drush search-api:index content_events
```

---

## Drush commands

```bash
# Queue field_material sync for all articles
drush eonext-editorial-search:sync-article-materials

# Process the queue
drush eonext-editorial-search:sync-article-materials --process

# Sync one article immediately
drush eonext-editorial-search:sync-article-materials --nid=123
```

---

## Frontend development

Built assets are committed (`js/editorial.bundle.js`, CSS). To rebuild:

```bash
cd web/modules/custom/eonext_editorial_search
npm install
npm run build
```

Scripts:

- `npm run build` — bundle React integration JS + editorial page CSS
- `npm run scss` — compile facet/page styles only

The React bundle uses Preact compat and esbuild; it injects into DPL React apps
via DOM hooks (autosuggest overlay, material related content).

---

## Troubleshooting

### Facets or REST missing after enable

Core View/index config was probably not present when the module was enabled.
Import core config, then reinstall optional config:

```bash
drush deploy -y
drush pmu eonext_editorial_search -y
drush en eonext_editorial_search -y
```

### Editorial search returns no / stale results

```bash
drush search-api:status content_events
drush search-api:index content_events
```

### Related content on material pages empty

Check articles have `field_material` populated:

```bash
drush eonext-editorial-search:sync-article-materials --process
```

### `drush cim` fails on `varnish_purger` (local DDEV)

Local resets often uninstall Varnish Purger while sync still contains its
config. Enable the module before import:

```bash
drush en varnish_purger -y
drush cim -y
```

Or use `drupal:upgrade` from `cms/Taskfile.yaml`, which handles this sequence.

---

## Uninstall

`hook_uninstall()` removes editorial facets, facet source, REST permissions, and
module settings. It does **not** revert Search API index changes made on the
shared `content_events` index; those may need manual cleanup if the module is
removed permanently.
