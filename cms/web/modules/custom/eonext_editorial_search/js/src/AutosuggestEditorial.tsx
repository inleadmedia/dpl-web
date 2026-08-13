import React, { useRef, useState, useEffect } from "react";

export interface EditorialSuggestion {
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
  teaser_text?: string;
}

export interface EditorialSearchResult {
  total: number;
  page: number;
  page_size: number;
  results: EditorialSuggestion[];
}

interface AutosuggestEditorialProps {
  query?: string;
  limit?: number;
  template?: "suggestion" | "search-results" | "material-results";
  materialId?: string;
  filter?: string | string[];
  onFound?(searchResult: EditorialSearchResult): void;
}

const ENDPOINT = "/api/v1/editorial-search";

const minimalAutosuggestCharacters = 1;

interface EditorialSettings {
  dateLocale?: string;
  translations?: Record<string, string>;
}

const settings = (): EditorialSettings =>
  (typeof window !== "undefined" &&
    (window as unknown as {
      drupalSettings?: { eonextEditorialSearch?: EditorialSettings };
    }).drupalSettings?.eonextEditorialSearch) ||
  {};

const t = (key: string): string => settings().translations?.[key] ?? key;

const getDateLocale = (): string => settings().dateLocale || "da-DK";

const formatCustomDateString = (isoString: string): string => {
  if (!isoString) {
    return "";
  }
  const date = new Date(isoString);
  if (Number.isNaN(date.getTime())) {
    return "";
  }
  try {
    return date.toLocaleDateString(getDateLocale(), {
      day: "numeric",
      month: "long",
      year: "numeric"
    });
  } catch {
    return date.toISOString().slice(0, 10);
  }
};

const buildUrl = (
  params: Record<string, string | number | string[] | undefined>
): string => {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") {
      return;
    }
    if (Array.isArray(value)) {
      const paramKey = key === "f" ? "f[]" : key;
      value.forEach((item) => {
        if (item !== "") {
          search.append(paramKey, String(item));
        }
      });
      return;
    }
    search.append(key, String(value));
  });
  return `${ENDPOINT}?${search.toString()}`;
};

const AutosuggestEditorialSearchResult: React.FC<EditorialSuggestion> = ({
  title,
  url,
  created_at,
  image,
  teaser_text
}) => (
  <li className="autosuggest-editorial__search-item">
    <a
      className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link"
      href={url}
    >
      {image && image.url ? (
        <img
          className="autosuggest-editorial__search-item-image"
          src={image.url}
          alt={image.alt}
        />
      ) : (
        <div className="autosuggest-editorial__search-item-image media-container media-container--placeholder" />
      )}

      <span>
        <span className="autosuggest-editorial__search-item-meta text-small-caption">
          <span className="autosuggest-editorial__search-item-type">
            {t("Artikel")}
          </span>
          <span className="autosuggest-editorial__search-item-meta-delimiter">
            |
          </span>
          <span className="autosuggest-editorial__search-item-date">
            {formatCustomDateString(created_at)}
          </span>
        </span>

        <span className="autosuggest-editorial__search-item-title card-list-item__title text-header-h4 mb-4">
          {title}
        </span>
        <span className="autosuggest-editorial__search-item-teaser text-small-caption">
          {teaser_text}
        </span>
      </span>
    </a>
  </li>
);

const editorialBundleMapping: Record<string, string> = {
  article: "Artikel",
  page: "Page",
  default: "Event",
  e_resource: "E-Resource"
};

const AutosuggestEditorialSuggestion: React.FC<EditorialSuggestion> = ({
  url,
  title,
  bundle
}) => {
  const mappedBundle =
    editorialBundleMapping[bundle as keyof typeof editorialBundleMapping] ||
    bundle;

  return (
    <li className="autosuggest-editorial__item">
      <a
        className="autosuggest__text-item text-body-medium-regular px-24 autosuggest-editorial__item-link"
        href={url}
      >
        <span>
          {title}
          <span className="autosuggest-editorial__item-link-type">
            &nbsp;
            {mappedBundle ? "(" + t(mappedBundle) + ")" : null}
          </span>
        </span>
      </a>
    </li>
  );
};

const AutosuggestMaterial: React.FC<EditorialSuggestion> = ({
  title,
  url,
  created_at,
  image,
  categories
}) => (
  <li className="autosuggest-editorial__material">
    <a className="autosuggest-editorial__material-link" href={url}>
      <div className="autosuggest-editorial__material-wrapper">
        <div className="autosuggest-editorial__materia-image-wrapper">
          {image && image.url ? <img src={image.url} alt={image.alt} /> : null}
        </div>

        <div className="autosuggest-editorial__material-text">
          {categories && categories.length > 0 ? (
            <div className="autosuggest-editorial__categories">
              {categories.map((category: string) => (
                <span key={category}>{category}</span>
              ))}
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

const AutosuggestEditorial: React.FC<AutosuggestEditorialProps> = ({
  query,
  materialId,
  filter,
  limit = 7,
  template = "suggestion",
  onFound
}) => {
  const lastQuery = useRef<string | undefined>("");
  const [editorialSuggestions, setEditorialSuggestions] = useState<
    EditorialSuggestion[]
  >([]);

  useEffect(() => {
    if (
      !materialId &&
      (!query ||
        query.length < minimalAutosuggestCharacters ||
        lastQuery.current === query)
    ) {
      return;
    }

    lastQuery.current = query || materialId;
    const serviceUrl = buildUrl({
      page_size: limit || 7,
      q: query,
      material: materialId,
      f: filter
    });

    (async function fetchSuggestions() {
      try {
        const response = await window.fetch(serviceUrl, { mode: "cors" });
        const result: EditorialSearchResult = await response.json();

        if (lastQuery.current !== query && lastQuery.current !== materialId) {
          return;
        }

        if (onFound) {
          onFound(result);
        }

        setEditorialSuggestions(Array.isArray(result.results) ? result.results : []);
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error("Cannot fetch editorial suggestions!", error);
      }
    })();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [query, materialId, filter]);

  if (
    Array.isArray(editorialSuggestions) === false ||
    editorialSuggestions.length === 0
  ) {
    return null;
  }

  return (
    <ul className={`autosuggest-editorial autosuggest-editorial--${template}`}>
      {editorialSuggestions.map((editorialSuggestion: EditorialSuggestion) => {
        if (template === "search-results") {
          return (
            <AutosuggestEditorialSearchResult
              key={editorialSuggestion.uuid}
              {...editorialSuggestion}
            />
          );
        }

        if (template === "material-results") {
          return (
            <AutosuggestMaterial
              key={editorialSuggestion.uuid}
              {...editorialSuggestion}
            />
          );
        }

        return (
          <AutosuggestEditorialSuggestion
            key={editorialSuggestion.uuid}
            {...editorialSuggestion}
          />
        );
      })}
    </ul>
  );
};

export default AutosuggestEditorial;
