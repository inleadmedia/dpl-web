import clsx from "clsx";
import { FC, ReactNode } from "react";

import MediaContainer from "../media-container/MediaContainer";

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
  return (
    <article
      className={clsx("nav-spot", {
        "nav-spot--image-right": imageAlignment === "right",
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
