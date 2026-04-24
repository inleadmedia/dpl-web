import { useMemo } from "react";
import { useConfig } from "./config";
import { getAllFaustIds } from "./helpers/general";
import { useGetHoldings } from "../../apps/material/helper";
import { Manifestation } from "./types/entities";
import { Pid } from "./types/ids";

const useReservableFromAnotherLibrary = (
  manifestations: Manifestation[]
): {
  reservablePidsFromAnotherLibrary: Pid[];
  materialIsReservableFromAnotherLibrary: boolean;
} => {
  const blacklistedGroup = useMemo(() => {
    // @ts-ignore-next-line
    return (document.querySelector("[data-blacklisted-reservation-groups]")?.getAttribute("data-blacklisted-reservation-groups") || "")
      .split(",")
      .filter(Boolean);
  }, []);

  const config = useConfig();
  const { data: holdingsData } = useGetHoldings({
    faustIds: getAllFaustIds(manifestations),
    blacklist: "availability",
    config
  });

  const blacklistedBranches = config("blacklistedAvailabilityBranchesConfig", { transformer: "stringToArray" });
  const availableForReservation = !holdingsData || holdingsData.length === 0 || (holdingsData || []).filter(group => {
    if (group.reservable !== true)
      return false;

    if ((group.holdings || []).length === 0)
      return true;

    return group.holdings.filter(holding => {
      return blacklistedBranches.includes(holding.branch.branchId) === false && holding.materials.filter((material) => {
        return blacklistedGroup.includes(material?.materialGroup?.name) === false;
      }).length !== 0;
    }).length !== 0;
  }).length !== 0;

  // If there is no holdings data or if there are holdings that are reservable, we return an empty array.
  // Because we use the array length to determine if we should show the button or not.
  if (holdingsData?.some(({ reservable }) => reservable === true) || availableForReservation !== true) {
    return {
      reservablePidsFromAnotherLibrary: [],
      materialIsReservableFromAnotherLibrary: false
    };
  }

  const reservablePidsFromAnotherLibrary = manifestations
    .filter(({ catalogueCodes }) =>
      catalogueCodes?.otherCatalogues.some((code) => code.startsWith("OVE"))
    )
    .map(({ pid }) => pid);

  const materialIsReservableFromAnotherLibrary = Boolean(
    reservablePidsFromAnotherLibrary.length
  );

  return {
    reservablePidsFromAnotherLibrary,
    materialIsReservableFromAnotherLibrary
  };
};

export default useReservableFromAnotherLibrary;
