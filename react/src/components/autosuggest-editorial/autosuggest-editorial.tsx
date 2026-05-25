import React, { useRef, useState, useEffect } from "react";
import { getServiceBaseUrl, serviceUrlKeys } from "../../core/utils/reduxMiddleware/extractServiceBaseUrls";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";
import { formatCustomDateString } from "../../core/utils/helpers/date";
import { useText } from "../../core/utils/text";
import { omitBy } from "lodash";

interface AutosuggestEditorialProps {
  query?: string;
  limit?: number;
  template?: string;
  materialId?: string;
  filter?: string;
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
  };
  categories?: string[];
};

// See the same option at the `apps/search-header/search-header.tsx` file;
const minimalAutosuggestCharacters = 3;

const AutosuggestEditorialSearchResult: React.FC<EditorialSuggestion> = ({ title, url, created_at, image }) => {
  const t = useText();

  return <li className="autosuggest-editorial__search-item">
    <a className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link" href={ url }>
      {
        image && image.url
          ? <img className="autosuggest-editorial__search-item-image" src={image.url} alt={image.alt} />
          : <div className="autosuggest-editorial__search-item-image media-container media-container--placeholder"></div>
      }
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

const AutosuggestMaterial: React.FC<EditorialSuggestion> = ({ title, url, created_at, image, categories }) => {
  return (
    <li className="autosuggest-editorial__material">
      <a className="autosuggest-editorial__material-link" href={url}>
        <div className="autosuggest-editorial__material-wrapper">
          <div className="autosuggest-editorial__materia-image-wrapper">
            <img src={image.url} alt={image.alt} />
          </div>

          <div className="autosuggest-editorial__material-text">
            {categories && categories.length > 0 ? (
              <div className="autosuggest-editorial__categories">
                {categories.map((category: string) => {
                  return <span key={category}>{category}</span>;
                })}
              </div>
            ) : null}
            <h2 className="autosuggest-editorial__material-title">{title}</h2>
            <p className="autosuggest-editorial__material-date">
              {formatCustomDateString(created_at)}
            </p>
          </div>
        </div>
      </a>
    </li>
  );
};

const AutosuggestEditorial: React.FC<AutosuggestEditorialProps> = ({ query, materialId, filter, limit = 7, template = "suggestion", onFound }) => {
  const lastQuery = useRef<string | undefined>("");
  const [editorialSuggestions, setEditorialSuggestions] = useState([]);

  useEffect(() => {
    if (!materialId && (!query || query.length < minimalAutosuggestCharacters || lastQuery.current === query))
      return;

    lastQuery.current = query || materialId;
    const serviceUrl = getServiceUrlWithParams({
      baseUrl: getServiceBaseUrl(serviceUrlKeys.dplCms),
      url: "/api/v1/editorial-search",
      params: omitBy(
        {
          page_size: limit || 7,
          q: query,
          material: materialId,
          f: filter
        },
        (value) => value == null || value === ""
      )
    });

    (async function() {
      try {
        const response = await window.fetch(serviceUrl, { mode: "cors" });
        const editorialSuggestions = await response.json();

        /* Omit response for expired query */
        if (lastQuery.current !== query && lastQuery.current !== materialId)
          return;

        if (onFound)
          onFound(editorialSuggestions);

        setEditorialSuggestions(editorialSuggestions.results);
      } catch (error) {
        console.error("Cannot fetch editorial suggestions!", error);
      }
    })();
  }, [query]);

  if (Array.isArray(editorialSuggestions) === false || editorialSuggestions.length === 0)
    return null;

  return <ul className={`autosuggest-editorial autosuggest-editorial--${ template }`}>
    {
      editorialSuggestions.map((editorialSuggestion: EditorialSuggestion) => {
        if (template === "search-results")
          return <AutosuggestEditorialSearchResult key={ editorialSuggestion.uuid } { ...editorialSuggestion } />;

        if (template === "material-results")
          return <AutosuggestMaterial key={ editorialSuggestion.uuid } { ...editorialSuggestion }/>;

        return <AutosuggestEditorialSuggestion key={ editorialSuggestion.uuid } { ...editorialSuggestion } />;
      })
    }
  </ul>;
}

export default AutosuggestEditorial;
