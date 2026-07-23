import clsx from "clsx";
import { CSSProperties, FC } from "react";
import {
  getPreferredTextColor,
  parseColorToRgb,
} from "../colors/color-contrast";

type NavTeaserProps = {
  title: string;
  subtitle?: string;
  teaserText?: string;
  backgroundImageSrc?: string;
  /** Overlay bar background. Applies only when `backgroundImageSrc` is set. */
  overlayBackgroundColor?: string;
};

const NAV_TEASER_DARK_TEXT = "#235881";
const NAV_TEASER_LIGHT_TEXT = "#fefaf1";
const OVERLAY_BACKGROUND_OPACITY = 0.3;

const isLightBackgroundColor = (backgroundColor?: string): boolean => {
  if (!backgroundColor || !parseColorToRgb(backgroundColor)) {
    return false;
  }

  return (
    getPreferredTextColor(backgroundColor, {
      dark: NAV_TEASER_DARK_TEXT,
      light: NAV_TEASER_LIGHT_TEXT,
    }) === NAV_TEASER_DARK_TEXT
  );
};

const toOverlayBackgroundColor = (color: string): string | undefined => {
  const rgb = parseColorToRgb(color);
  if (!rgb) {
    return undefined;
  }

  return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${OVERLAY_BACKGROUND_OPACITY})`;
};

const NavTeaser: FC<NavTeaserProps> = ({
  title,
  subtitle,
  teaserText,
  backgroundImageSrc,
  overlayBackgroundColor,
}) => {
  const hasBackgroundImage = Boolean(backgroundImageSrc);
  const isLightBackground = isLightBackgroundColor(overlayBackgroundColor);
  const backgroundStyle: CSSProperties | undefined = hasBackgroundImage
    ? { backgroundImage: `url(${backgroundImageSrc})` }
    : undefined;
  const overlayStyle: CSSProperties | undefined = overlayBackgroundColor
    ? { backgroundColor: toOverlayBackgroundColor(overlayBackgroundColor) }
    : undefined;

  return (
    <article
      className={clsx("nav-teaser", {
        "nav-teaser--has-background-image": hasBackgroundImage,
        "nav-teaser--light-background": isLightBackground,
      })}
    >
      <a href="#">
        {hasBackgroundImage ? (
          <>
            <div
              className="nav-teaser__background"
              style={backgroundStyle}
              aria-hidden="true"
            />
            <div className="nav-teaser__overlay" style={overlayStyle}>
              <div className="nav-teaser__overlay-content">
                <h3 className="nav-teaser__title">{title}</h3>
                {teaserText ? (
                  <p className="nav-teaser__teaser">{teaserText}</p>
                ) : (
                  ""
                )}
              </div>
            </div>
          </>
        ) : (
          <>
            <h3 className="nav-teaser__title">{title}</h3>
            {subtitle ? <p className="nav-teaser__subtitle">{subtitle}</p> : ""}
            {teaserText ? (
              <p className="nav-teaser__teaser">{teaserText}</p>
            ) : (
              ""
            )}
          </>
        )}
      </a>
    </article>
  );
};

export default NavTeaser;
