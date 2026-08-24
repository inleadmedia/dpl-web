// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
import TranslationsConstructor from "./translations/TranslationsConstructor.jsx";

if (window.InleadReactInjector) {
  window.InleadReactInjector.add({
    options: {
      condition: {
        hasChild: {
          "data-cy": "header-menu-profile-button"
        }
      },
      injectionType: {
        beforeChild: { "data-cy": "header-menu-profile-button" }
      },
      onFound(reactNodeData) {
        // Modify the parent node to apply required styles
        reactNodeData.tag = "div";
        reactNodeData.props = reactNodeData.props || {};
        reactNodeData.props.className = reactNodeData.props.className || "";
        if (reactNodeData.props.className.includes("header__profile-traslation-container") === false)
          reactNodeData.props.className += " header__profile-traslation-container";
      }
    },
    handler: function(React) {
      const Translations = TranslationsConstructor(React);

      return function() {
        return <Translations />;
      }
    }
  });
}
