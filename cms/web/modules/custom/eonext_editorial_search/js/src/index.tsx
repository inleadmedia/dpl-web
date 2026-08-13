import React from "react";
import ReactDOM from "react-dom";
import AutosuggestEditorial from "./AutosuggestEditorial";
import AutosuggestOverlay from "./AutosuggestOverlay";
import MaterialEditorialDisclosure from "./MaterialEditorialDisclosure";

interface EditorialSettings {
  relatedPageSize?: number;
  autosuggestLimit?: number;
  searchResultLimit?: number;
  searchResultFilters?: string[];
  materialRelatedFilters?: string[];
  dateLocale?: string;
  icons?: { base?: string };
  translations?: Record<string, string>;
}

const DEFAULT_AUTOSUGGEST_FILTERS = [
  "content_type:article",
  "content_type:page",
  "content_type:default"
];

const DEFAULT_MATERIAL_FILTERS = ["content_type:article"];

const cfg = (): EditorialSettings =>
  (window as unknown as {
    drupalSettings?: { eonextEditorialSearch?: EditorialSettings };
  }).drupalSettings?.eonextEditorialSearch || {};

const marked = (el: Element): boolean => {
  if ((el as unknown as { __eoEditorial?: boolean }).__eoEditorial) {
    return true;
  }
  (el as unknown as { __eoEditorial?: boolean }).__eoEditorial = true;
  return false;
};

const isEnabled = (attr: string): boolean => {
  const el = document.querySelector(`[${attr}]`);
  return !!el && el.getAttribute(attr) === "true";
};

/**
 * Decode the search query from the URL.
 *
 * DPL search redirects use encodeURI() before URLSearchParams.set(), which
 * double-encodes non-ASCII characters (e.g. "læsning" becomes l%25C3%25A6sning).
 * URLSearchParams.get() only decodes once, leaving l%C3%A6sning — decode again.
 */
const decodeSearchQuery = (raw: string): string => {
  if (!raw) {
    return "";
  }

  try {
    let decoded = decodeURIComponent(raw);
    if (decoded.includes("%") && /%[0-9A-Fa-f]{2}/.test(decoded)) {
      decoded = decodeURIComponent(decoded);
    }
    return decoded;
  } catch {
    return raw;
  }
};

const getSearchQueryFromUrl = (): string => {
  const raw = new URLSearchParams(window.location.search).get("q") || "";
  return decodeSearchQuery(raw);
};

const mountMaterial = (): void => {
  document
    .querySelectorAll<HTMLElement>('[data-dpl-app="material"]')
    .forEach((app) => {
      const wid = app.getAttribute("data-wid");
      if (!wid) {
        return;
      }

      const disclosureSection = app.querySelector<HTMLElement>(".disclosure-section");
      if (!disclosureSection || disclosureSection.dataset.eoEditorialMounted === "true") {
        return;
      }

      const settings = cfg();
      const iconBase = settings.icons?.base || "";
      const mount = document.createElement("div");
      mount.className = "eonext-editorial-material-mount";
      disclosureSection.appendChild(mount);
      disclosureSection.dataset.eoEditorialMounted = "true";

      ReactDOM.render(
        <MaterialEditorialDisclosure
          materialId={wid}
          limit={settings.relatedPageSize || 20}
          filter={settings.materialRelatedFilters || DEFAULT_MATERIAL_FILTERS}
          title={settings.translations?.relatedContentText || "Relateret indhold"}
          receiptIcon={`${iconBase}/collection/Receipt.svg`}
          expandIcon={`${iconBase}/collection/ExpandMore.svg`}
        />,
        mount
      );
    });
};

const mountSearchResult = (): void => {
  document
    .querySelectorAll<HTMLElement>('[data-dpl-app="search-result"]')
    .forEach((app) => {
      const searchRoot = app.querySelector<HTMLElement>(".search");
      const resultsGrid = searchRoot?.querySelector(":scope > .search__results");
      if (!searchRoot || !resultsGrid) {
        return;
      }

      let mount = searchRoot.querySelector<HTMLElement>(
        ":scope > .eonext-editorial--search-results"
      );
      if (!mount) {
        mount = document.createElement("section");
        mount.className = "eonext-editorial eonext-editorial--search-results";
        searchRoot.insertBefore(mount, resultsGrid);
      }

      if (mount.dataset.eoRendered === "true") {
        return;
      }
      mount.dataset.eoRendered = "true";

      const render = () => {
        const q = getSearchQueryFromUrl();
        ReactDOM.render(
          <AutosuggestEditorial
            query={q}
            template="search-results"
            limit={cfg().searchResultLimit || 5}
            filter={cfg().searchResultFilters || DEFAULT_AUTOSUGGEST_FILTERS}
          />,
          mount
        );
      };

      render();
      if (searchRoot.dataset.eoPopstateBound !== "true") {
        searchRoot.dataset.eoPopstateBound = "true";
        window.addEventListener("popstate", render);
      }
    });
};

const mountAutosuggest = (): void => {
  document
    .querySelectorAll<HTMLInputElement>(".header__menu-search-input")
    .forEach((input) => {
      if (marked(input)) {
        return;
      }
      const mount = document.createElement("div");
      mount.className = "eonext-editorial-overlay-root";
      document.body.appendChild(mount);
      ReactDOM.render(
        <AutosuggestOverlay
          input={input}
          limit={cfg().autosuggestLimit || 7}
          filter={cfg().searchResultFilters || DEFAULT_AUTOSUGGEST_FILTERS}
        />,
        mount
      );
    });
};

const run = (): void => {
  if (isEnabled("data-eonext-editorial-overlay")) {
    mountAutosuggest();
  }
  if (isEnabled("data-editorial-search")) {
    mountSearchResult();
    mountMaterial();
  }
};

const start = (): void => {
  run();
  const observer = new MutationObserver(() => run());
  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", start);
} else {
  start();
}
