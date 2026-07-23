import clsx from "clsx";
import { CSSProperties, FC } from "react";
import { ReactComponent as ArrowLargeRight } from "../Arrows/icon-arrow-ui/icon-arrow-ui-large-right.svg";

type BannerType = {
  link: string;
  title: string;
  imageSrc?: string;
  description?: string;
  /** Any valid CSS color. Applies only when `imageSrc` is not provided. */
  backgroundColor?: string;
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

  return (
    <a
      href={link}
      className={clsx("banner", "arrow__hover--right-large", {
        "banner--has-image": hasImage,
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
