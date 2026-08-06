/**
 * Kultur header state — compact header below 1440px, inline desktop at 1440px+.
 */
function desktopLinksContainerCheckSize() {
  if (window.innerWidth < 1440) {
    document.documentElement.classList.add("has-burger-menu");
    document.documentElement.classList.remove("has-desktop-menu");
    return;
  }

  document.documentElement.classList.remove("has-burger-menu");
  document.documentElement.classList.add("has-desktop-menu");
}

function initHeaderState() {
  const desktopLinks = document.querySelector(
    ".header__menu-navigation:not(.is-header-initialized)",
  );

  desktopLinksContainerCheckSize();
  window.addEventListener("resize", desktopLinksContainerCheckSize, true);
  document.addEventListener("load", desktopLinksContainerCheckSize, true);

  if (desktopLinks) {
    desktopLinks.classList.add("is-header-initialized");
  }
}

initHeaderState();
