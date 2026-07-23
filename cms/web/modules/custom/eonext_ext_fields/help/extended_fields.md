## Extended Fields Configuration

Extended fields must be defined in JSON format with the following schema.
**Do not paste comments into the configuration**, as JSON does not support them!

You can define any custom field name or override an existing one.
To override a field, open the material page and copy its name, then use the copied name as the section property. All custom fields will be added to the end of the given section.

### Example JSON Schema

```json
{
  "detail": {
    "Field name": {
      // Data for the custom field, can be taken from the different sources.
      // "marc" - Data from the parsed `work.marc.content` GraphQL response.
      // where number (e.g.: 001) it's datafield tag value `<datafield ind1='0' ind2='0' tag='001'>...</datafield >`
      // letter (e.g.: a) it's a code of the found datafield `<subfield code='a'>131285299</subfield>`
      // In some cases there might be provided multiple datafields with the same tag, in this case all found values
      // will be used for a custom field.
      //
      // "graphql" - any field from the `work` GraphQL response. In case the pointer will refer to a list\array of
      // values then all it's values will be used for a custom field. But make sure that list is a list of strings or numbers,
      // in other case data serialization will return unexpected results, like "[object Object],[object Object]"
      "data": ["marc:001.a", "graphql:titles.full[0]"],

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
