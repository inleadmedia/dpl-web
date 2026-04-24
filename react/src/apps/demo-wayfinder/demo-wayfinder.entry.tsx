import React, { useEffect, useState } from "react";
import { NuqsAdapter } from "nuqs/adapters/react";

import { withUrls } from "../../core/utils/url";
import { withText } from "../../core/utils/text";
import { withConfig } from "../../core/utils/config";
import { MappArgs } from "../../core/storybook/mappArgs";

import getWayfinder from "../../components/find-on-shelf/getWayfinder";
import Wayfinder from "../../components/wayfinder/wayfinder";
import {
  HoldingDataInterface,
  WayfinderReaponse
} from "../../components/wayfinder/wayfinder-types";
import GlobalUrlEntryPropsInterface from "../../core/utils/types/global-url-props";

const mockBranchIds = {
  branchId: "DK-733000",
  departmentId: "vok",
  locationId: "udlån",
  subLocationId: "kær"
};

export interface WayfinderEntryProps extends GlobalUrlEntryPropsInterface {
  branchId: string;
  departmentId: string;
  locationId: string;
  subLocationId: string;
  shelfmark: string;
}

const WayfinderEntry: React.FC<WayfinderEntryProps> = (props) => {
  const [wayfinderLink, setWayfinderLink] = useState<WayfinderReaponse>();
  const processWayfinderRequests = async (
    holdingsIds: HoldingDataInterface
  ) => {
    try {
      const wayfinderView = await getWayfinder(holdingsIds);

      if (wayfinderView) {
        setWayfinderLink(wayfinderView);
      }
    } catch (error) {
      // eslint-disable-next-line
      console.error("Error fetching Wayfinder data:", error);
    }
  };

  useEffect(() => {
    if (mockBranchIds) {
      processWayfinderRequests(mockBranchIds);
    }
  });

  return (
    <NuqsAdapter>
      <div className="dpl-demo-wayfinder">
        {wayfinderLink ? (
          <Wayfinder viewId={wayfinderLink.viewId} link={wayfinderLink.link} />
        ) : null}
      </div>
    </NuqsAdapter>
  );
};

export default withConfig(
  withUrls(withText(WayfinderEntry))
);
