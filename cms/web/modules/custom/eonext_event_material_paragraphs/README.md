# EO Next Event and Material Paragraphs

Customizations for event and material paragraph types, including configurable
"Show all" button behavior on list and grid paragraphs.

## Introduction

Editors can choose whether the "Show all" control expands a truncated list on
the page or links to an Explore subpage. This module adds the configuration
fields, form UX, and frontend wiring for supported paragraph bundles.

When **Link to Explore page** is selected and a URL is provided, the default
in-page expand button from the Novel theme is replaced with a link. All hidden
list items are revealed so the full list remains visible before navigation.

When **Expand list** is selected (the default), the standard show-more
behavior is unchanged.

## Supported paragraph types

| Bundle | Frontend integration |
| --- | --- |
| `filtered_event_list` | `dpl_related_content` list wrapper (`data-show-all-link-config`) |
| `manual_event_list` | Paragraph wrapper attribute |
| `material_grid_automatic` | React app `#data` key `show-all-link-config` |
| `material_grid_manual` | React app `#data` key `show-all-link-config` |

## Editor configuration

Each supported paragraph exposes two fields:

- **Show all button behavior** (`field_show_all_behavior`) — radio buttons:
  - *Expand list* — default show-more interaction.
  - *Link to Explore page* — replaces the button with the configured link.
- **Explore page link** (`field_show_all_link`) — link field, visible and
  required only when link behavior is selected.

Fields appear on standalone paragraph edit forms and in nested paragraph
widget subforms inside node edit forms.

## Frontend contract

Event list paragraphs expose the resolved URL on the list wrapper:

```html
<div data-show-more-list-wrapper data-show-all-link-config="/explore/events">
```

Material grid paragraphs pass the URL to the React mounter via render array
data (`show-all-link-config`, rendered as a `data-` attribute on the mount
point).

The `show_all_link` library (`js/show-all-link.js`) uses `Drupal.behaviors` and
`once()` to swap the expand button for an anchor when the data attribute is
present. It depends on `novel/show-more`.

## Architecture

| Component | Role |
| --- | --- |
| `ShowAllConfig` | Field names, URL resolution, form `#states` selectors |
| `ShowAllSetup` | Install-time form display and GraphQL Compose configuration |
| `FormHooks` | Conditional link field visibility on paragraph forms |
| `PreprocessHooks` | Injects link URL into render arrays / wrapper attributes |
| `EventMaterialParagraphBundles` | Supported bundle list |

Filtered event lists stash `#show_all_link` on the `dpl_related_content`
render array (theme variable declared via `hook_theme_registry_alter()`). The
`preprocess_dpl_related_content` hook turns it into the wrapper data attribute.

## Requirements

- Drupal 10 or 11
- `dpl_paragraphs`
- `drupal_typed`
- `link`
- `options`
- `paragraphs`
- `dpl_related_content` (for `filtered_event_list` paragraphs)
- Novel theme `show-more` library (for event list link mode)

## Installation

Enable the module. On install, `ShowAllSetup` configures form displays for all
supported bundles and enables the new fields in GraphQL Compose when that
module is present.

Field storage and bundle field config ship in `config/install/`.
