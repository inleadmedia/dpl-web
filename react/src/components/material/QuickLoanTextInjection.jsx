// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
import { QuickLoanText } from "./QuickLoanText.tsx";

if (window.InleadReactInjector) {
  let pageManifestations = [];
  window.InleadReactInjector.add({
    options: {
      condition: {
        className: "material-header__availability-label"
      },
      injectionType: "append",
      onTargetValidation: (node) => {
        if (node.props == null)
          return;

        // Pick the props from other component (MaterialAvailabilityText.tsx)
        if ("manifestations" in node.props && node.tag instanceof Function && node.tag.toString().includes("reservableFromAnotherLibraryText"))
          pageManifestations = node.props.manifestations;
      }
    },
    handler: function(React) {
      return function() {
        return <QuickLoanText manifestations={ pageManifestations } />;
      }
    }
  });
}
