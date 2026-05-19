export type ServiceBanner = {
  title: string;
  body: string | null;
  url: string | null;
  urlText: string | null;
};

export const getServiceBannerFromBody = (): ServiceBanner | null => {
  const title = document.body.getAttribute("data-service-banner-title");

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
