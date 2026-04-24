import * as React from "react";
import { useMemo, FC } from "react";
import { useText } from "../../core/utils/text";
import ContentListPage from "../../components/content-list/ContentListPage";

export interface SearchResultZeroHitsProps {
  dataCy?: string;
}

const SearchResultZeroHits: FC<SearchResultZeroHitsProps> = ({
  dataCy = "search-result-zero-hits"
}) => {
  const t = useText();

  const [noResultsHeading, noResultsText, noResultsEnabled] = useMemo(() => {
    return [
      document
        .querySelector("[data-no-results-heading]")
        ?.getAttribute("data-no-results-heading") || "",
      document
        .querySelector("[data-no-results-text]")
        ?.getAttribute("data-no-results-text") || "",
      document
        .querySelector("[data-no-results-enabled]")
        ?.getAttribute("data-no-results-enabled") === "true"
    ];
  }, []);

  return (
    <div className="content-list-page dpl-content-list-page" data-cy={dataCy}>
      <ContentListPage
        title={t("noSearchResultText")}
        headingClassName="my-112"
        headingDataCy="search-result-zero-hits"
        dataCy={dataCy}
      />
      {noResultsEnabled && (
        <div className="dpl-content-list-page__help">
          <h2 className="dpl-content-list-page__help-title">
            {noResultsHeading}
          </h2>
          <p className="dpl-content-list-page__help-description">
            {noResultsText}
          </p>
        </div>
      )}
    </div>
  );
};

export default SearchResultZeroHits;
