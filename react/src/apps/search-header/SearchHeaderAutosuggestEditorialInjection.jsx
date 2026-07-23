// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
import AutosuggestEditorial from "../../components/autosuggest-editorial/autosuggest-editorial";
import { useText } from "../../core/utils/text";

if (window.InleadReactInjector) {
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
