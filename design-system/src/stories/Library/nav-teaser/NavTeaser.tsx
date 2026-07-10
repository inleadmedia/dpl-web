import clsx from "clsx";
import { CSSProperties, FC } from "react";
import { ReactComponent as Arrow } from "../Arrows/icon-arrow-ui/icon-arrow-ui-nav.svg";

type NavTeaserProps = {
  title: string;
  subtitle?: string;
  teaserText?: string;
  backgroundImageSrc?: string;
};

const NavTeaser: FC<NavTeaserProps> = ({
  title,
  subtitle,
  teaserText,
  backgroundImageSrc,
}) => {
  const hasBackgroundImage = Boolean(backgroundImageSrc);
  const backgroundStyle: CSSProperties | undefined = hasBackgroundImage
    ? { backgroundImage: `url(${backgroundImageSrc})` }
    : undefined;

  return (
    <article
      className={clsx("nav-teaser", {
        "nav-teaser--has-background-image": hasBackgroundImage,
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
            <div className="nav-teaser__overlay">
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
