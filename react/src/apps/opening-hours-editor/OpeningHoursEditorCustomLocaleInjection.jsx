// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";

if (window.InleadReactInjector && window.DPL_fullCalendarCustomLocale) {
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

        if (node.props.initialView === "timeGridWeek") {
          node.props = Object.assign({}, node.props);
          node.props.locale = window.DPL_fullCalendarCustomLocale;
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
