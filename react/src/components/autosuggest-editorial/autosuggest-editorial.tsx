import React, { useRef, useState, useEffect } from "react";
import { getServiceBaseUrl, serviceUrlKeys } from "../../core/utils/reduxMiddleware/extractServiceBaseUrls";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";
import { formatCustomDateString } from "../../core/utils/helpers/date";
import { useText } from "../../core/utils/text";

interface AutosuggestEditorialProps {
  query?: string;
  limit?: number;
  template?: string;
  onFound?(searchResult: EditorialSearchResult): void;
};

interface EditorialSearchResult {
  total: number;
  page: number;
  page_size: number;
  results: EditorialSuggestion;
}

interface EditorialSuggestion {
  uuid: string;
  bundle: string;
  title: string;
  url: string;
  created_at: string;
  image: {
    url: string;
    alt: string;
  }
};

// See the same option at the `apps/search-header/search-header.tsx` file;
const minimalAutosuggestCharacters = 3;

const AutosuggestEditorialSearchResult: React.FC<EditorialSuggestion> = ({ title, url, created_at, image }) => {
  const t = useText();

  return <li className="autosuggest-editorial__search-item">
    <a className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link" href={ url }>
      <img className="autosuggest-editorial__search-item-image" src={image.url} alt={image.alt} />
      <span className="autosuggest-editorial__search-item-meta text-small-caption">
        <span className="autosuggest-editorial__search-item-type">
          { t("Artikel") }
        </span>
        <span className="autosuggest-editorial__search-item-meta-delimiter">|</span>
        <span className="autosuggest-editorial__search-item-date">{ formatCustomDateString(created_at) }</span>
      </span>

      <span className="autosuggest-editorial__search-item-title card-list-item__title text-header-h4 mb-4">
        { title }
      </span>
    </a>
  </li>;
}

const editorialBundleMapping = {
  default: "Event",
  e_resource: "E-Resource"
};

const AutosuggestEditorialSuggestion: React.FC<EditorialSuggestion> = ({ url, title, bundle }) => {
  const t = useText();
  let mappedBundle = editorialBundleMapping[bundle as keyof typeof editorialBundleMapping] || bundle;

  return <li className="autosuggest-editorial__item">
    <a className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link" href={ url }>
      { title }
      <span className="autosuggest-editorial__item-link-type">
        &nbsp;
        { mappedBundle ? "(" + t(mappedBundle) + ")" : null }
      </span>
    </a>
  </li>;
}

const AutosuggestEditorial: React.FC<AutosuggestEditorialProps> = ({ query, limit = 7, template = "suggestion", onFound }) => {
  const lastQuery = useRef("");
  const [editorialSuggestions, setEditorialSuggestions] = useState([]);

  useEffect(() => {
    if (!query || query.length < minimalAutosuggestCharacters || lastQuery.current === query)
      return;

    lastQuery.current = query;
    const serviceUrl = getServiceUrlWithParams({
      baseUrl: getServiceBaseUrl(serviceUrlKeys.dplCms),
      url: "/api/v1/editorial-search",
      params: {
        page_size: limit || 7,
        q: query
      }
    });

    (async function() {
      try {
        const response = await window.fetch(serviceUrl, { mode: "cors" });
        const editorialSuggestions = await response.json();

        /* Omit response for expired query */
        if (lastQuery.current !== query)
          return;

        if (onFound)
          onFound(editorialSuggestions);

        setEditorialSuggestions(editorialSuggestions.results);
      } catch (error) {
        console.error("Cannot fetch editorial suggestions!", error);
      }
    })();
  }, [query]);

  if (editorialSuggestions.length === 0)
    return null;

  return <ul className={`autosuggest-editorial autosuggest-editorial--${ template }`}>
    {
      editorialSuggestions.map((editorialSuggestion: EditorialSuggestion) => {
        if (template === "search-results")
          return <AutosuggestEditorialSearchResult key={ editorialSuggestion.uuid } { ...editorialSuggestion } />;

        return <AutosuggestEditorialSuggestion key={ editorialSuggestion.uuid } { ...editorialSuggestion } />;
      })
    }
  </ul>;
}

export default AutosuggestEditorial;
