import clsx from "clsx";
import {FC, ReactNode, useEffect, useState} from "react";

import MediaContainer from "../media-container/MediaContainer";
import {
  getPreferredTextColor,
  parseColorToRgb,
} from "../colors/color-contrast";

type NavSpotImageAlignment = "left" | "right";

type NavSpotProps = {
  variant?: string;
  title: string;
  subtitle?: string;
  media?: ReactNode;
  placeholderText?: string;
  url?: string;
  buttonLabel?: string;
  imageAlignment?: NavSpotImageAlignment;
  backgroundColor?: string;
};

const NAV_SPOT_DARK_TEXT = "#235881";
const NAV_SPOT_LIGHT_TEXT = "#fefaf1";

const isLightBackgroundColor = (backgroundColor?: string): boolean => {
  if (!backgroundColor || !parseColorToRgb(backgroundColor)) {
    return false;
  }

  return (
    getPreferredTextColor(backgroundColor, {
      dark: NAV_SPOT_DARK_TEXT,
      light: NAV_SPOT_LIGHT_TEXT,
    }) === NAV_SPOT_DARK_TEXT
  );
};

const NavSpot: FC<NavSpotProps> = ({
  variant,
  title,
  subtitle,
  media,
  placeholderText,
  url = "#",
  buttonLabel = "Læs mere",
  imageAlignment = "left",
  backgroundColor,
}) => {
  const [isLightBackground, setIsLightBackground] = useState(false);

  useEffect(() => {
    setIsLightBackground(isLightBackgroundColor(backgroundColor))
  }, [backgroundColor]);

  return (
    <article
      className={clsx("nav-spot", {
        "nav-spot--image-right": imageAlignment === "right",
        "nav-spot--light-background": isLightBackground,
      })}
      data-variant={variant}
      style={backgroundColor ? { backgroundColor } : undefined}
    >
      <div className="nav-spot__content">
        <div className="nav-spot__media">
          <MediaContainer placeholderText={placeholderText} media={media} />
        </div>

        <div className="nav-spot__text">
          <h2 className="nav-spot__title">{title}</h2>

          {subtitle ? <p className="nav-spot__subtitle">{subtitle}</p> : ""}

          <div className="nav-spot__button">
            <a href={url} className="nav-spot__button-link">
              {buttonLabel}
            </a>
          </div>
        </div>
      </div>
    </article>
  );
};

export default NavSpot;
