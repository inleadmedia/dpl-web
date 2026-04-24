import React, { useMemo } from "react";
import {
  getAllFaustIds,
  getAllPids,
  getMaterialType
} from "../../../../core/utils/helpers/general";
import { isBlocked } from "../../../../core/utils/helpers/user";
import { ButtonSize } from "../../../../core/utils/types/button";
import { Manifestation } from "../../../../core/utils/types/entities";
import UseReservableManifestations from "../../../../core/utils/UseReservableManifestations";
import MaterialButtonUserBlocked from "../generic/MaterialButtonUserBlocked";
import MaterialButtonReservePhysical from "./MaterialButtonPhysical";
import MaterialButtonLoading from "../generic/MaterialButtonLoading";
import MaterialButtonDisabled from "../generic/MaterialButtonDisabled";
import { useText } from "../../../../core/utils/text";
import { usePatronData } from "../../../../core/utils/helpers/usePatronData";
import useGetAvailability from "../../../../core/utils/useGetAvailability";
import { useConfig } from "../../../../core/utils/config";
import { useGetHoldings } from "../../../../apps/material/helper";

export interface MaterialButtonsPhysicalProps {
  isSpecificManifestation?: boolean;
  manifestations: Manifestation[];
  size?: ButtonSize;
  dataCy?: string;
  isEditionPicker?: boolean;
}

const MaterialButtonsPhysical: React.FC<MaterialButtonsPhysicalProps> = ({
  isSpecificManifestation,
  manifestations,
  size,
  dataCy = "material-buttons-physical",
  isEditionPicker = false
}) => {
  const t = useText();
  const config = useConfig();
  const faustIds = getAllFaustIds(manifestations);
  const pids = getAllPids(manifestations);
  let { data: holdings, isLoading: isLoadingHoldings } = useGetHoldings({
    faustIds,
    blacklist: "availability",
    config
  });

  // We extract loading of Availability here, as it isn't possible within
  // UseReservableManifestations. React query uses cached version of the data
  // so we can determine if the request inside UseReservableManifestations is
  // loading this way.
  const { isLoading: isLoadingAvailability } = useGetAvailability({
    faustIds,
    config
  });
  const { reservableManifestations } = UseReservableManifestations({
    manifestations
  });
  const { data: userData, isLoading } = usePatronData();
  const isUserBlocked = !!(userData?.patron && isBlocked(userData?.patron));

  const blacklistedGroup = useMemo(() => {
    // @ts-ignore-next-line
    return (document.querySelector("[data-blacklisted-reservation-groups]")?.getAttribute("data-blacklisted-reservation-groups") || "")
      .split(",")
      .filter(Boolean);
  }, []);

  if (isLoading || isLoadingAvailability) {
    return <MaterialButtonLoading classNames="reserve-button" />;
  }

  const blacklistedBranches = config("blacklistedAvailabilityBranchesConfig", { transformer: "stringToArray" });
  const availableForReservation = !holdings || holdings.length === 0 || (holdings || []).filter(group => {
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

  if (availableForReservation !== true) {
    return <MaterialButtonDisabled size={size} label={t("cantReserveText")} />;
  }

  if (!reservableManifestations || reservableManifestations.length < 1) {
    return (
      <MaterialButtonDisabled
        size={size}
        label={t("cantReserveText")}
        classNames="reserve-button"
      />
    );
  }

  if (isUserBlocked) {
    return <MaterialButtonUserBlocked size={size} dataCy={dataCy} />;
  }

  // We show the reservation button if the user isn't logged in or isn't blocked.
  // In the former case there there's no way to see if they're blocked, so we
  // redirect anonymous user to the login page.

  if (!userData || !isUserBlocked) {
    return (
      <MaterialButtonReservePhysical
        dataCy={dataCy}
        manifestationMaterialType={getMaterialType(manifestations)}
        size={size}
        isSpecificManifestation={isSpecificManifestation}
        pids={pids}
        isEditionPicker={isEditionPicker}
      />
    );
  }

  return null;
};

export default MaterialButtonsPhysical;
