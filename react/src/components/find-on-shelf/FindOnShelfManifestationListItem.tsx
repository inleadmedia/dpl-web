import * as React from "react";
import { FC, useEffect, useState } from "react";
import { useText } from "../../core/utils/text";
import { Manifestation } from "../../core/utils/types/entities";
import getWayfinder from "./getWayfinder";
import Wayfinder from "../wayfinder/wayfinder";
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
      // eslint-disable-next-line
      console.error("Error fetching Wayfinder data:", error);
    }
  };

  useEffect(() => {
    if (holdingData && numberAvailable) {
      processWayfinderRequests(holdingData);
    }
  }, [holdingData, numberAvailable]);

  let placementText: any = locationArray.filter(Boolean).map((locationText, index, arr) => {
    return <>
      <b className="find-on-shelf__item-location-text">{locationText}</b>
      { index !== arr.length - 1 ? <span className="find-on-shelf__item-location-delimiter"> · </span> : null }
    </>;
  });

  if (shelfmark) {
    placementText.push(<>
      { placementText.length !== 0 ? <span className="find-on-shelf__item-location-delimiter"> · </span> : null }
      <span className="find-on-shelf__item-location-shefmark">
        {shelfmark.shelfmark} {shelfmark.postfix}
      </span>
    </>);
  }

  return (
    <li className="find-on-shelf__row text-body-medium-regular" role="row">
      <p className="dpl-find-on-shelf-title find-on-shelf__material-text">
        <Wayfinder viewId={wayfinderLink.viewId} link={wayfinderLink.link} />
        <span role="cell">
          {title}
          {publicationYear && ` (${publicationYear})`}
        </span>
      </p>
      <span className="find-on-shelf__item-location" role="cell">
        {placementText ? placementText : t("findOnShelfModalNoLocationSpecifiedText")}
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
