// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
// `lodash`, `core/utils/helpers/date` and `core/fetchers/helpers` is allowed to import
// into package because it does not use the app context
import { omitBy } from "lodash";
import { formatCustomDateString } from "../../core/utils/helpers/date";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";

import AutosuggestEditorialCostructor from "../../components/autosuggest-editorial/autosuggest-editorial-constructor.jsx";

if (window.InleadReactInjector) {
  window.InleadReactInjector.unwrapPackages((stringifiedMethod) => {
    if (stringifiedMethod.includes("The translation store is broken."))
      return { methodKey: "useText", packageKey: "core/utils/text.tsx" };

    if (stringifiedMethod.includes("Service base url for ")) {
      return {
        methodKey: "getServiceBaseUrl",
        packageKey: "core/utils/reduxMiddleware/extractServiceBaseUrls.tsx"
      };
    }
  }, (objectMethod) => {
    const isServiceUrlKeys = Object.keys(objectMethod).some(key => {
      return [
        "fbsBaseUrl",
        "publizonBaseUrl",
        "dplCmsBaseUrl",
        "coverBaseUrl"
      ].includes(objectMethod[key]);
    });

    if (isServiceUrlKeys) {
      return {
        methodKey: "serviceUrlKeys",
        packageKey: "core/utils/reduxMiddleware/extractServiceBaseUrls.tsx"
      };
    }
  });

  const { getServiceBaseUrl, serviceUrlKeys } = window.InleadReactInjector.getUnwrappedPackage("core/utils/reduxMiddleware/extractServiceBaseUrls.tsx");

  const _useText = window.InleadReactInjector.getUnwrappedPackage("core/utils/text.tsx").useText;
  // useText with optional translate;
  const useText = function useText() {
    const t = _useText();

    return function(string, options) {
      try {
        return t(string, options);
      } catch (_) {
        return string;
      }
    }
  };


  /* Module info: The editorial articles above the search results */
  let query = "";

  window.InleadReactInjector.add({
    options: {
      condition: {
        className: "autosuggest__main-suggestions"
      },
      injectionType: "append",
      onTargetValidation: (node) => {
        if (node.props == null)
          return;

        if (node.props.className && (node.props.className === "autosuggest" || node.props.className.includes("autosuggest "))) {
          node.props.className += " autosuggest--with-editorial-suggestions";
        }
        // Pick the props form the parent component (SearchResut.tsx)
        if ("query" in node.props && node.tag instanceof Function && (/className\:\s*[\"\']autosuggest\_\_main\-suggestions[\"\']\s*/ig).test(node.tag.toString()))
          query = node.props.query;
      }
    },
    handler: function(React) {
      const AutosuggestEditorial = AutosuggestEditorialCostructor(React, {
        useRef: React.useRef,
        useState: React.useState,
        useEffect: React.useEffect,
        getServiceBaseUrl,
        serviceUrlKeys,
        getServiceUrlWithParams,
        formatCustomDateString,
        useText,
        omitBy
      });

      return function() {
        const [editorialSuggestionsHits, setEditorialSuggestionsHits] = React.useState(0);
        const t = useText();

        return <div className={`autosuggest__editorial-suggestions ${ editorialSuggestionsHits > 0 ? "autosuggest--found-editorial-suggestions" : "" }`}>
          {
            editorialSuggestionsHits > 0
              ? <h3 className="card-list-item__title text-header-h4 mt-8 mb-8 px-24">
                { t("Redaktionelt Indhold") }
              </h3>
              : null
          }
          <AutosuggestEditorial query={ query } onFound={ (editorialSuggestions) => setEditorialSuggestionsHits(editorialSuggestions.total) } />
        </div>;
      };
    }
  });

  window.InleadReactInjector.add({
    options: {
      condition: {
        className: "autosuggest__text-suggestions"
      },
      injectionType: "prepend"
    },
    handler: function(React) {
      return function() {
        const t = useText();

        return <h3 className="card-list-item__title text-header-h4 mt-8 mb-8 px-24">
          {t("Materialer")}
        </h3>;
      };
    }
  });
}
