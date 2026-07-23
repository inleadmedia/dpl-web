// The `hook-to-react.js` is mandatory, it will be used at standalone mode when connected to page via a built file.
import "../../hook-to-react.js";
import { useText } from "../../core/utils/text";
import { useConfig } from "../../core/utils/config";
import {
  getAllPids,
  convertPostIdsToFaustIds
} from "../../core/utils/helpers/general";
import { useGetHoldings } from "./helper";

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

        // Pick the props other component (StockAndReservationInfo.tsx)
        if ("manifestations" in node.props && node.tag instanceof Function && node.tag.toString().includes("reservableFromAnotherLibraryText"))
          pageManifestations = node.props.manifestations;
      }
    },
    handler: function(React) {
      return function() {
        const t = useText();
        const config = useConfig();

        const pids = getAllPids(pageManifestations);
        const faustIds = convertPostIdsToFaustIds(pids);
        const { data, isLoading, isError } = useGetHoldings({
          faustIds,
          config,
          blacklist: "availability"
        });

        const holdings = data && data[0] && data[0].holdings;
        if (!holdings || holdings.length === 0)
          return null;

        const quickLoanLibrariesMap = {};
        holdings.forEach(holding => {
          if (holding?.lmsPlacement?.sublocation?.sublocationId === "kvik" && holding?.branch?.title)
            quickLoanLibrariesMap[holding?.branch?.title] = true;
        });

        const quickLoanLibraries = Object.keys(quickLoanLibrariesMap);
        if (quickLoanLibraries.length === 0)
          return null;

        return <div className="mt-4 text-small-caption">
          { t("Kviklån - 14 dages lån:") } { quickLoanLibraries.join(", ") }
        </div>
      }
    }
  });
}
