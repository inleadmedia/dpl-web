// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";

if (window.InleadReactInjector) {
  let expandedLibraries = (document.querySelector("[data-opening-hours-sidebar-expanded]")
    ?.getAttribute("data-opening-hours-sidebar-expanded") || "").split(",").filter(Boolean).map(value => value.trim());

  // Current injection just modifies the existing React element.
  window.InleadReactInjector.add({
    options: {
      condition: {
        // The class will not exists, but it's ok.
        className: "inlead-at-hoc-injection"
      },
      injectionType: "append",
      onTargetValidation: (node) => {
        if (node.props == null)
          return;

        if (
          node.props.detailsClassName === "opening-hours-sidebar-details"
          && node.props.summaryClassName == "opening-hours-sidebar-summary"
        ) {
          node.props = Object.assign({}, node.props);
          if (expandedLibraries.includes(node.props.id))
            node.props.showContent = true;
        }
      }
    },
    handler: function() {
      return function() {
        return null;
      }
    }
  });
}
