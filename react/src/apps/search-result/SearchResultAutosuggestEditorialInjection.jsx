// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
// `lodash`, `core/utils/helpers/date` and `core/fetchers/helpers` is allowed to import
// into package because it does not use the app context
import { omitBy } from "lodash";
import { formatCustomDateString } from "../../core/utils/helpers/date";
import { getServiceUrlWithParams } from "../../core/fetchers/helpers";

import AutosuggestEditorialCostructor from "../../components/autosuggest-editorial/autosuggest-editorial-constructor.jsx";

// Mandatory! Checkout that `window.InleadReactInjector` is presented and component might be injected.
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

  // The variables that required to get exposable component working.
  // In current case it's the `q` property from a main `SearchResut.tsx` component;
  let searchQuery = "";


  // The injection should be always started from following rules:
  /* // <- TIP: uncomment multiline comment to get highlight!

    window.InleadReactInjector.add({
      options: {
        // The search rules for a node that must be used to injection
        // there supported any properties, like: "label", "size", "variant" or any other attributes and props that will be passed to node it will be checked with equality!.

        // But the `className` will be validated like browser .includes("class-name") call.
        // The className of the target node will be splitted by " " and then the values will be matched from given array of classes
        condition: { className: "target-class" },

        // The way how the injection will be performed there available following options:
        // "append" - the injected node will be added to end of children list
        // "prepend" - the injected node will be added to start of children list
        // { beforeChild: { className: "target-class" } } - the node will be added before child with given class, or at the end in case child is not found
        // { afterChild: { className: "target-class" } } - the node will be added after child with given class, or at the end in case child is not found
        injectionType: "append",

        // The handler will be called for every node at the application that will be called with `React.createElement`
        // method might be used to extract data from parent nodes props. But make sure you have property trageted the node.
        // Do not use `node.tag.name` to validation because it will be removed at bundle file!
        onTargetValidation: (node) => {
          // Do any checks and extract any data from given node but DO NOT MODIFY IT!
        }
      },
      // Important! Do not include the react with import statement, because in this case the injection will not be working property.
      // Always use the React from function arguments!

      handler: function(React) {
        // Handler must return any react component, would be better to always use the empty function with exposable components inside
        // for a cases when it will require some internal state or custom hooks
        return function() {
          // Regular react code, but there will no external props or children
          const [counter, setCounter] = React.useState(0);

          return <div className="injected-module">
            <p>Injected component: { counter }</p>

            <button onClick={ setCounter(counter + 1) }>Increment counter</button>
          </div>;
        };
      }
    });

  */ // <- TIP: uncomment multiline comment to get highlight! 


  window.InleadReactInjector.add({
    options: {
      condition: {
        className: "search"
      },
      injectionType: {
        beforeChild: { className: "search__results" }
      },
      onTargetValidation: (node) => {
        if (node.props == null)
          return;

        // Pick the props form the parent component (SearchResut.tsx)
        if ("q" in node.props && node.tag instanceof Function && (/className\:\s*[\"\']search\_\_results[\"\']\s*/ig).test(node.tag.toString()))
          searchQuery = node.props.q;
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
        return <AutosuggestEditorial query={ searchQuery } minQueryLimit={1} limit={5} template="search-results" filter="content_type:article" />;
      };
    }
  });
}
