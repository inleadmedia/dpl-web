import { ComplexSuggestionTypeEnum } from "../../../core/dbc-gateway/generated/graphql";

export type SearchTermItem = {
  value: string;
  labelKey: string;
  type: ComplexSuggestionTypeEnum;
  placeholderKey: string;
  disableSuggest?: boolean;
};

let extendedComplexSearchOptions: any = document.querySelector("[data-eonext-ext-complex-search]")?.getAttribute("data-eonext-ext-complex-search") || ""
if (extendedComplexSearchOptions) {
  try {
    extendedComplexSearchOptions = JSON.parse(extendedComplexSearchOptions)
  } catch (error) {
    extendedComplexSearchOptions = {};
    console.warn("Cannot parse [data-eonext-ext-complex-search] options!", error);
  }

  extendedComplexSearchOptions = extendedComplexSearchOptions?.terms || [];
  extendedComplexSearchOptions = extendedComplexSearchOptions.map((customOption: any) => {
    return Object.assign({
      value: customOption.term,
      labelKey: customOption.label,
      type: ComplexSuggestionTypeEnum.Default,
      placeholderKey: customOption.placeholder
    }, customOption);
  });
}

export const SEARCH_TERM_OPTIONS: SearchTermItem[] = [
  {
    value: "term.default",
    labelKey: "advancedSearchLabelDefaultText",
    type: ComplexSuggestionTypeEnum.Default,
    placeholderKey: "advancedSearchPlaceholderDefaultText"
  },
  {
    value: "term.title",
    labelKey: "advancedSearchLabelTitleText",
    type: ComplexSuggestionTypeEnum.Title,
    placeholderKey: "advancedSearchPlaceholderTitleText"
  },
  {
    value: "term.series",
    labelKey: "advancedSearchLabelSeriesText",
    type: ComplexSuggestionTypeEnum.Series,
    placeholderKey: "advancedSearchPlaceholderSeriesText"
  },
  {
    value: "term.creatorcontributor",
    labelKey: "advancedSearchLabelCreatorText",
    type: ComplexSuggestionTypeEnum.Creatorcontributor,
    placeholderKey: "advancedSearchPlaceholderCreatorText"
  },
  {
    value: "term.subject",
    labelKey: "advancedSearchLabelSubjectText",
    type: ComplexSuggestionTypeEnum.Subject,
    placeholderKey: "advancedSearchPlaceholderSubjectText"
  },
  {
    value: "term.publisher",
    labelKey: "advancedSearchLabelPublisherText",
    type: ComplexSuggestionTypeEnum.Publisher,
    placeholderKey: "advancedSearchPlaceholderPublisherText"
  },
  {
    value: "dk5",
    labelKey: "advancedSearchLabelDk5Text",
    type: ComplexSuggestionTypeEnum.Default,
    placeholderKey: "advancedSearchPlaceholderDk5Text",
    disableSuggest: true
  },
  {
    value: "term.isbn",
    labelKey: "advancedSearchLabelIsbnText",
    type: ComplexSuggestionTypeEnum.Default,
    placeholderKey: "advancedSearchPlaceholderIsbnText",
    disableSuggest: true
  }
].concat(extendedComplexSearchOptions || []);
