// window.InleadReactInjector comes from hook-to-react.js, which the
// eonext_react/hook_to_react library loads before this bundle. Upstream imports
// that file here instead, but the injector is a global singleton and shipping a
// second copy per bundle is how versions drift apart.
import { QuickLoanTextConstructor } from "./QuickLoanTextConstructor.jsx";

if (window.InleadReactInjector) {
  window.InleadReactInjector.unwrapPackages((stringifiedMethod) => {
    if (stringifiedMethod.includes("The translation store is broken."))
      return { methodKey: "useText", packageKey: "core/utils/text.tsx" };

    if (
      stringifiedMethod.includes("stringToNumber")
      && stringifiedMethod.includes("stringToArray")
      && stringifiedMethod.includes("jsonParse")
    ) {
      return { methodKey: "useConfig", packageKey: "core/utils/config.tsx" };
    }

    if (
      stringifiedMethod.includes("availability")
      && stringifiedMethod.includes("pickup")
      && stringifiedMethod.includes("both")
    ) {
      return { methodKey: "getBlacklistedQueryArgs", packageKey: "material/helper.tsx" };
    }
  });

  const materialHelper = window.InleadReactInjector.getUnwrappedPackage("material/helper.tsx");
  window.InleadReactInjector.unwrapPackage(materialHelper, (stringifiedMethod) => {
    if (
      stringifiedMethod.includes("faustIds")
      && stringifiedMethod.includes("blacklist")
      && stringifiedMethod.includes("isError")
    ) {
      return { methodKey: "useGetHoldings", packageKey: "material/helper.tsx" };
    }
  });

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

  const { useConfig } = window.InleadReactInjector.getUnwrappedPackage("core/utils/config.tsx");
  const { useGetHoldings } = materialHelper;

  // Can't find the method at original package because there no string to match
  const getAllPids = (manifestations) => {
    return manifestations.map((manifestation) => manifestation.pid)
  };

  const convertPostIdsToFaustIds = (postIds) => {
    return postIds.map((pid) => {
      const matches = pid.match(/^[0-9]+-[a-z]+:([a-zA-Z0-9-_.]+)$/);
      if (matches && matches[1])
        return matches[1];

      return pid;
    });
  }

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
      const QuickLoanText = QuickLoanTextConstructor(React, {
        useText,
        useConfig,
        getAllPids,
        convertPostIdsToFaustIds,
        useGetHoldings
      });

      return function() {
        return <QuickLoanText manifestations={ pageManifestations } />;
      }
    }
  });
}
