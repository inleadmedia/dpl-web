import React, { useRef, useState, useEffect } from "react";
import { getServiceBaseUrl, serviceUrlKeys } from "../../core/utils/reduxMiddleware/extractServiceBaseUrls";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";

interface AutosuggestEditorialProps {
  query?: string;
};

interface EditorialSuggestion {
  uuid: string;
  title: string;
  url: string;
};

// See the same option at the `apps/search-header/search-header.tsx` file;
const minimalAutosuggestCharacters = 3;

const AutosuggestEditorial: React.FC<AutosuggestEditorialProps> = ({ query }) => {
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
        page_size: 7,
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

        setEditorialSuggestions(editorialSuggestions.results);
      } catch (error) {
        console.error("Cannot fetch editorial suggestions!", error);
      }
    })();
  }, [query]);

  if (editorialSuggestions.length === 0)
    return null;

  return <ul className="autosuggest-editorial">
    {
      editorialSuggestions.map((editorialSuggestion: EditorialSuggestion) => {
        return <li key={ editorialSuggestion.uuid } className="autosuggest-editorial__item">
          <a className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link" href={ editorialSuggestion.url }>
            { editorialSuggestion.title }
          </a>
        </li>;
      })
    }
  </ul>;
}

export default AutosuggestEditorial;
