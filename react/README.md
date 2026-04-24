# React components, presented within Drupal (formerly 'dpl-react')

![https://raw.githubusercontent.com/danskernesdigitalebibliotek/dpl-web/main/react/logo.png](https://raw.githubusercontent.com/danskernesdigitalebibliotek/dpl-web/main/react/logo.png)

A set of React components and applications providing self-service features for
Danish public libraries implemented in TypeScript.

Read more about DPL React by visiting our
[Documentation site](https://danskernesdigitalebibliotek.github.io/dpl-docs/DPL-React)

# Inlead installation Guide

1. Clone the dpl-web project `git clone git@github.com:inleadmedia/dpl-web.git`
2. Make sure you have using NodeJS 22 or above
3. Install dependencies for `design-system` folder: `cd ./design-system && yarn install`;
4. Build the `design-system`: `sh ./bindle.sh`;
5. Remove the `node_modules` folder for much effort installation of the dpl-react: `rm -rf ./node_modules`;
6. Go to `dpl-react` folder and install dependencies: `cd ../react && yarn install`;
7. Run `yarn start:storybook:dev` to start development or `yarn build` to build library for `DPL-CMS`;

# Inlead extended fields

Extended fields must be defined at the JSON format with following schema.
Do not paste comments to the configuration, because it's not supported by JSON!

You can define any custom field name or override an existing one. To override the field open the material page
and copy it's name, then use copied name as section property. All custom fields will be added to the end of given section.

```javascript
{
  // Override the shelfmark from holdings placements at the material page.
  "shelfmarkOverride": {
    // Pointer to some marc field of the given manifistation.
    "data": "652.m[0]"
  },
  // The section with a custom text like the original description. Will be displayed above original one
  "additionalDescription": {
    // Title of the description section (visible for users, might be translated with the generic translation feature)
    "label": "Additional description",
    // The pointer of the text content, working similar to `data` pointers of other fields.
    // The pickup of additional description by lang does not prevent the duplication with original description in some cases, so you still need to use filterBodyBy: ["graphql:abstract"]
    "body": ["manifestationMarc(systemAgency):504.byLang.kal || manifestationMarc(systemAgency):504.a"],
    // Use any selector like for the "data" or "body" fields to filter out any found content that
    // should not be displayed as additional description body.
    "filterBodyBy": ["graphql:abstract"],
    // List of the tags that displayed under the text.
    // Support the same options as tags from `detail` config below.
    "tags": [{
      // Label of the tags list (visible for users, might be translated with the generic translation feature)
      "label": "Custom tags",
      "data": ["marc:001.a", "marc:001.c"],
      "url":"/search?q=${tag}"
    }]
  },
  // Define the aliases for `detail` and `description` sections to override the existing
  // tags when it's translated by Drupal localization feature.
  "aliases": {
    "Subject": ["Tags"],
    "Spog": ["Language"]
  },
  // Settings for the detail section of the material page
  "detail": {
    "Field name": {
      // Data for the custom field, can be taken from the different sources.
      // "marc" - Data from the parsed `work.marc.content` GraphQL response.
      // where number (e.g.: 001) it's datafield tag value `<datafield ind1='0' ind2='0' tag='001'>...</datafield >`
      // letter (e.g.: a) it's a code of the found datafield `<subfield code='a'>131285299</subfield>`
      // In some cases there might be provided multiple datafields with the same tag, in this case all found values
      // will be used for a custom field.
      //
      // "graphql" - any field from the `work` GraphQL response. In case the pointer will reffer to a list\array of
      // values then all it's values will be used for a custom field. But make sure that list is a list of strings or numbers,
      // in other case data serialization will return unexpected results, like "[object Object],[object Object]"
      //
      // || - might used at the any pointer like logical-or at the programming languages.
      "data": ["marc:001.a", "graphql:titles.full[0]", "extraMarc:001.a[1] || extraMarc:001.a[0]"],

      // Optional
      // The way to insert the custom field, by default "append", required only for extend the existing fields.
      // Add string data will be joined with " ," delimiter.
      // Supports following options:
      // "append" - Add the field data to the end of existing field data.
      // "prepend" - Add the field data will be added before of the existing field data.
      // "replace" - New data will replace existing data, even if there no found data for a given rules (field will be hided)
      // "fallback" - The new data will be visible only in case the original data is empty.
      "insert": "prepend",

      // Optional
      // Boolean flag to hide an field when it's true.
      "hidden": true,

      // Detail section specific fields!
      //
      // Optional
      // Way to display the data, by default "text". Supports following values:
      // "text" - the data will be displayed as the single string splitted by " ," delimiter.
      // "list" - the text data will be splitted by " ," delimiter and then displayed as the ul>li elements.
      "type": "list"
    },
    "description": {
      // Override the default description filed with an custom data
      "body": ["manifestationMarc(systemAgency):504.byLang.dan || graphql:abstract"],
      "Emnetal": {
        // The same options as for the detail section above.
        "data": ["marc:001.c"]
        "insert": "replace",
        "hidden": false,

        // Description section specific fields!
        //
        // Optional.
        // The link of the tag, by default "#".
        // Any relative or absolute link that will be used for the tags. The `${tag}` from the url template will be
        // replaced to the tag value. E.g. `marc:001.c` will return two values: "2021" and "10210".
        // Then there will be displayed two following tags: `<a href="/search?q=2021">2021</a>`
        // and `<a href="/search?q=10210">10210</a>`.
        "url": "/search?q=${tag}"
      }
    }
  }
}
```
