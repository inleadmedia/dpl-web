import clsx from "clsx";
import { CSSProperties, FC } from "react";
import { ReactComponent as ArrowLargeRight } from "../Arrows/icon-arrow-ui/icon-arrow-ui-large-right.svg";
import {
  getPreferredTextColor,
  parseColorToRgb,
} from "../colors/color-contrast";

type BannerType = {
  link: string;
  title: string;
  imageSrc?: string;
  description?: string;
  /** Any valid CSS color. Applies only when `imageSrc` is not provided. */
  backgroundColor?: string;
};

const BANNER_DARK_TEXT = "#235881";
const BANNER_LIGHT_TEXT = "#fefaf1";
const BANNER_DEFAULT_BACKGROUND = "#eee9e5";

const isLightBackgroundColor = (backgroundColor?: string): boolean => {
  if (!backgroundColor || !parseColorToRgb(backgroundColor)) {
    return false;
  }

  return (
    getPreferredTextColor(backgroundColor, {
      dark: BANNER_DARK_TEXT,
      light: BANNER_LIGHT_TEXT,
    }) === BANNER_DARK_TEXT
  );
};

const Banner: FC<BannerType> = ({
  link,
  imageSrc,
  title,
  description,
  backgroundColor,
}) => {
  const hasImage = Boolean(imageSrc);

  let style: CSSProperties | undefined;
  if (hasImage) {
    style = { backgroundImage: `url(${imageSrc})` };
  } else if (backgroundColor) {
    style = { backgroundColor };
  }

  const isLightBackground =
    !hasImage &&
    isLightBackgroundColor(backgroundColor || BANNER_DEFAULT_BACKGROUND);

  return (
    <a
      href={link}
      className={clsx("banner", "arrow__hover--right-large", {
        "banner--has-image": hasImage,
        "banner--light-background": isLightBackground,
      })}
      style={style}
    >
      <div className="banner__content-wrapper">
        <div
          className={clsx("banner__content", {
            "banner__content--has-image": hasImage,
          })}
        >
          {title && (
            <h2
              className="banner__title"
              // We need to be able to replicate our WYSIWYG field in Drupal that makes it possible to underline (<u>) words.
              dangerouslySetInnerHTML={{ __html: title }}
            />
          )}
          {description && <p className="banner__description">{description}</p>}
          <ArrowLargeRight />
        </div>
      </div>
    </a>
  );
};

export default Banner;
