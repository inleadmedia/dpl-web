import * as React from "react";
import { withText } from "../../core/utils/text";
import { withUrls } from "../../core/utils/url";
import SearchHeader from "./search-header";
import GlobalUrlEntryPropsInterface from "../../core/utils/types/global-url-props";
import { GlobalEntryTextProps } from "../../core/storybook/globalTextArgs";
import { GlobalConfigProps } from "../../core/storybook/globalConfigArgs";

export interface SearchHeaderTextProps {
  searchHeaderIconAltText?: string;
  searchHeaderInputLabelText?: string;
  inputPlaceholderText?: string;
  stringSuggestionAuthorText?: string;
  stringSuggestionWorkText?: string;
  stringSuggestionTopicText?: string;
  searchHeaderDropdownText: string;
  etAlText: string;
  autosuggestBookCategoryText: string;
  autosuggestEbookCategoryText: string;
  autosuggestFilmCategoryText: string;
  autosuggestAudioBookCategoryText: string;
  autosuggestMusicCategoryText: string;
  autosuggestGameCategoryText: string;
  autosuggestAnimatedSeriesCategoryText: string;
  inText: string;
  loadingText: string;
  searchNoValidCharactersErrorText: string;
  headerDropdownItemAdvancedSearchText: string;
}

export interface SearchHeaderEntryProps
  extends
    SearchHeaderTextProps,
    GlobalEntryTextProps,
    GlobalConfigProps,
    GlobalUrlEntryPropsInterface {
      isInjectionExample?: boolean;
    }

const SearchHeaderEntry: React.FC<SearchHeaderEntryProps> = ({ isInjectionExample }) => {
  // Required to force React re-render when injection is mounted. Only for dev!
  const [currentDate, setCurrentDate] = React.useState(0);

  if (isInjectionExample) {
    React.useEffect(() => {
      // @ts-ignore-next-line
      if (window.InleadReactInjector) {
        // @ts-ignore-next-line
        window.InleadReactInjector.clearInjections();
      }

      // @ts-ignore-next-line
      import("./SearchHeaderAutosuggestEditorialInjection.jsx").then(() => {
        setCurrentDate(Date.now());
      });
    }, []);

    return <div data-current-date={ currentDate || "" }>
      <SearchHeader />
    </div>;
  }

  return <SearchHeader />;
};

export default withUrls(withText(SearchHeaderEntry));
