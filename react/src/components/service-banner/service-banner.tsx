import React, { useEffect, useState } from "react";
import { createPortal } from "react-dom";

export type ServiceBanner = {
  title: string;
  body: string | null;
  url: string | null;
  urlText: string | null;
};

type ServiceBannerWrapperProps = {
  href?: string;
  ariaLabel: string;
  children: React.ReactNode;
};

const SERVICE_BANNER_MOUNT_ID = "service-banner-mount";

const getServiceBannerMountTarget = (): HTMLElement | null => {
  const existingMount = document.getElementById(SERVICE_BANNER_MOUNT_ID);
  if (existingMount) {
    return existingMount;
  }

  const breadcrumb = document.querySelector("nav.breadcrumb");
  const anchor = breadcrumb ?? document.querySelector(".header");
  if (!anchor) {
    return null;
  }

  const mount = document.createElement("div");
  mount.id = SERVICE_BANNER_MOUNT_ID;
  mount.className = "service-banner-mount";
  anchor.insertAdjacentElement("afterend", mount);

  return mount;
};

const ServiceBannerWrapper = ({
  href,
  ariaLabel,
  children
}: ServiceBannerWrapperProps) => {
  const handleKeyDown: React.KeyboardEventHandler<HTMLElement> = (event) => {
    if (!href) return;
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    window.location.assign(href);
  };

  if (!href) {
    return (
      <div className="header__menu-banner-content" aria-label={ariaLabel}>
        {children}
      </div>
    );
  }

  return (
    <a
      href={href}
      className="header__menu-banner-content header__menu-banner-content--link"
      aria-label={ariaLabel}
      tabIndex={0}
      onKeyDown={
        handleKeyDown as unknown as React.KeyboardEventHandler<HTMLAnchorElement>
      }
    >
      {children}
    </a>
  );
};

const getServiceBannerFromBody = (): ServiceBanner | null => {
  const title =
    document
      .querySelector("[data-service-banner-title]")
      ?.getAttribute("data-service-banner-title") || "";

  if (!title) {
    return null;
  }

  return {
    title,
    body: document.body.getAttribute("data-service-banner-body"),
    url: document.body.getAttribute("data-service-banner-url"),
    urlText: document.body.getAttribute("data-service-banner-url-text")
  };
};

const serviceBannerData = getServiceBannerFromBody();
export default function ServiceBanner() {
  const [portalTarget, setPortalTarget] = useState<HTMLElement | null>(null);

  useEffect(() => {
    if (!serviceBannerData) return;

    const mount = getServiceBannerMountTarget();
    if (!mount) return;

    setPortalTarget(mount);

    return () => {
      mount.remove();
    };
  }, []);

  if (!serviceBannerData || !portalTarget) return null;

  const ariaLabel =
    serviceBannerData.urlText ||
    serviceBannerData.title.replace(/<[^>]*>/g, "").trim() ||
    "Service banner";

  return createPortal(
    <div className="header__menu-banner header__menu-banner--below-breadcrumb">
      <ServiceBannerWrapper
        href={serviceBannerData.url || undefined}
        ariaLabel={ariaLabel}
      >
        <svg
          className="header__menu-banner-fold"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
          aria-hidden="true"
        >
          <path d="M24 24V0L0 24H24Z" fill="#DBDBDB"></path>
          <path d="M0 0L0 24L24 0L0 0Z" fill="#F6F5F0"></path>
        </svg>
        <span className="header__menu-banner-icon" aria-hidden="true">
          <svg
            width="40"
            height="40"
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
          >
            <path
              fillRule="evenodd"
              clipRule="evenodd"
              d="M19.9995 5C25.5223 5 29.9995 9.47707 29.9995 14.9999C29.9995 18.1885 29.9995 21.3902 29.9995 23.3333C29.9995 28.3333 33.3328 30 33.3328 30L6.66615 30C6.66615 30 9.99949 28.3333 9.99949 23.3333C9.99949 21.3902 9.99949 18.1885 9.99949 14.9999C9.99949 9.47707 14.4766 5 19.9995 5Z"
              stroke="black"
              strokeWidth="2"
              strokeLinejoin="round"
            ></path>
            <path
              d="M16.6666 30C16.6666 31.8409 18.159 33.3333 20 33.3333C21.8409 33.3333 23.3333 31.8409 23.3333 30"
              stroke="black"
              strokeWidth="2"
            ></path>
          </svg>
        </span>

        <div className="header__menu-banner-text">
          <p
            className="header__menu-banner-title text-body-medium-medium"
            // eslint-disable-next-line react/no-danger
            dangerouslySetInnerHTML={{ __html: serviceBannerData.title }}
          />
          {serviceBannerData.body ||
          (serviceBannerData.url && serviceBannerData.urlText) ? (
            <p className="header__menu-banner-body text-body-medium-regular">
              {serviceBannerData.body}
              {serviceBannerData.url && serviceBannerData.urlText ? (
                <>
                  {serviceBannerData.body ? ". " : null}
                  <span className="header__menu-banner-linktext">
                    {serviceBannerData.urlText}
                  </span>
                </>
              ) : null}
            </p>
          ) : null}
        </div>

        <span className="header__menu-banner-arrow" aria-hidden="true">
          <svg
            width="61"
            height="9"
            viewBox="0 0 61 9"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
          >
            <path
              d="M60.3536 4.85355C60.5488 4.65829 60.5488 4.34171 60.3536 4.14645L57.1716 0.964466C56.9763 0.769204 56.6597 0.769204 56.4645 0.964466C56.2692 1.15973 56.2692 1.47631 56.4645 1.67157L59.2929 4.5L56.4645 7.32843C56.2692 7.52369 56.2692 7.84027 56.4645 8.03553C56.6597 8.2308 56.9763 8.2308 57.1716 8.03553L60.3536 4.85355ZM0 5H60V4H0V5Z"
              fill="black"
            ></path>
          </svg>
        </span>
      </ServiceBannerWrapper>
    </div>,
    portalTarget
  );
}
