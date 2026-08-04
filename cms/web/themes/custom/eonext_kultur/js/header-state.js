/**
 * Kultur header state — accounts for inline search taking menu column space.
 */
function desktopLinksContainerCheckSize() {
  const container = document.querySelector(".header__menu-navigation");

  document.documentElement.classList.add("has-burger-menu");
  document.documentElement.classList.remove("has-desktop-menu");

  const isDesktop = window.innerWidth >= 768;
  const logoWidth = isDesktop ? 280 : 0;
  const clockWidth = isDesktop ? 105 : 0;
  const searchSlot = document.querySelector(".ek-header-menu .header__menu-second");
  const searchWidth = isDesktop && searchSlot ? searchSlot.offsetWidth : 56;
  const mobileButtons = isDesktop ? 0 : 56;
  const reservedWidth = logoWidth + searchWidth + clockWidth + mobileButtons;
  const availableSpace = window.innerWidth - reservedWidth;

  if (!container || container.scrollWidth > availableSpace) {
    document.documentElement.classList.add("has-burger-menu");
    document.documentElement.classList.remove("has-desktop-menu");
  } else {
    document.documentElement.classList.remove("has-burger-menu");
    document.documentElement.classList.add("has-desktop-menu");
  }
}

function initHeaderState() {
  const desktopLinks = document.querySelector(
    ".header__menu-navigation:not(.is-header-initialized)",
  );

  if (desktopLinks) {
    desktopLinksContainerCheckSize(desktopLinks);

    window.addEventListener("resize", desktopLinksContainerCheckSize, true);
    document.addEventListener("load", desktopLinksContainerCheckSize, true);

    desktopLinks.classList.add("is-header-initialized");
  }
}

initHeaderState();
