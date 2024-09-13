import * as React from "react";
import { FC, useEffect, useState } from "react";
import { useText } from "../../core/utils/text";
import { Manifestation } from "../../core/utils/types/entities";
import { getFindOnShelfLocationText } from "./helper";
import getWayfinder from "./getWayfinder";
import Wayfinder from "../wayfinder/wayfinder";
import "./FindOnShelfManifestationListItem.scss";
import {
  HoldingDataInterface,
  WayfinderReaponse
} from "../wayfinder/wayfinder-types";

export interface FindOnShelfManifestationListItemProps {
  shelfmark: Manifestation["shelfmark"];
  locationArray: string[];
  title: string;
  publicationYear: string | null;
  numberAvailable: number;
  holdingData?: HoldingDataInterface;
}

const FindOnShelfManifestationListItem: FC<
  FindOnShelfManifestationListItemProps
> = ({ shelfmark, locationArray, title, publicationYear, numberAvailable, holdingData }) => {
  const t = useText();
  const [wayfinderLink, setWayfinderLink] = useState<
    WayfinderReaponse | Record<string, never>
  >({});
  const processWayfinderRequests = async (
    holdingsIds: HoldingDataInterface
  ) => {
    try {
      const wayfinderView = await getWayfinder(holdingsIds);
      if (wayfinderView) {
        setWayfinderLink(wayfinderView);
      }
    } catch (error) {
      console.error("Error fetching Wayfinder data:", error);
    }
  };
  useEffect(() => {
    if (holdingData && numberAvailable) {
      processWayfinderRequests(holdingData);
    }
  }, [holdingData, numberAvailable]);

  const shelfmarkFullText = shelfmark
    ? [shelfmark.shelfmark, shelfmark.postfix].filter(Boolean).join(" · ")
    : undefined;

  const locationArrayWithShelfmark = [
    ...locationArray,
    shelfmarkFullText
  ].filter((el) => el);

  return (
    <li className="find-on-shelf__row text-body-medium-regular" role="row">
      <p className="dpl-find-on-shelf-title">
        <Wayfinder viewId={wayfinderLink.viewId} link={wayfinderLink.link} />
        <span className="find-on-shelf__material-text" role="cell">
          {title}
          {publicationYear && ` (${publicationYear})`}
        </span>
      </p>
      <span role="cell">
        {locationArrayWithShelfmark.length
          ? getFindOnShelfLocationText(locationArrayWithShelfmark)
          : t("findOnShelfModalNoLocationSpecifiedText")}
      </span>
      <span className="find-on-shelf__item-count-text" role="cell">
        {numberAvailable}{" "}
        <span className="hide-on-desktop">
          {t("findOnShelfModalListItemCountText")}
        </span>
      </span>
    </li>
  );
};

export default FindOnShelfManifestationListItem;
