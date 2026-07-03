import "../../hook-to-react.js";
import AutosuggestEditorial from "../../components/autosuggest-editorial/autosuggest-editorial";

if (window.InleadReactInjector) {
  // The editorial articles above the search results
  let searchQuery = "";
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
      return function() {
        return <AutosuggestEditorial query={ searchQuery } minQueryLimit={1} limit={5} template="search-results" filter="content_type:article" />;
      };
    }
  });
}
