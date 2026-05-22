import React, { useEffect, useState } from "react";
import { createPortal } from "react-dom";

export type ServiceBanner = {
  title: string;
  body: string | null;
  url: string | null;
  urlText: string | null;
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
  const [headerRef, setHeaderRef] = useState<any>();

  useEffect(() => {
    if (!serviceBannerData) return;

    const header = document.querySelector(".header");
    header?.classList?.add("header__with-banner");

    setHeaderRef(header);

    return () => {
      header?.classList?.remove("header__with-banner");
    };
  }, []);

  if (!serviceBannerData || !headerRef) return null;

  return createPortal(
    <div className="header__menu-banner">
      <div className="header__menu-banner-content">
        <p
          className="header__menu-banner-title text-body-medium-medium"
          // eslint-disable-next-line react/no-danger
          dangerouslySetInnerHTML={{ __html: serviceBannerData.title }}
        />
        { serviceBannerData.body ||
        (serviceBannerData.url && serviceBannerData.urlText) ? (
          <p className="header__menu-banner-body text-body-medium-regular">
            { serviceBannerData.body }
            { serviceBannerData.url && serviceBannerData.urlText ? (
              <>
                { serviceBannerData.body ? " " : null }
                <a
                  href={ serviceBannerData.url }
                  className="link-tag color-secondary-gray"
                >
                Body:  { serviceBannerData.urlText }
                </a>
              </>
            ) : null }
          </p>
        ) : null }
      </div>
    </div>,
    headerRef
  );
}
