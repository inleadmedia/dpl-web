/**
 * Kultur header state — centered nav must fit between logo and search/clock.
 */
function desktopLinksContainerCheckSize() {
  const container = document.querySelector(".header__menu-navigation");
  const header = document.querySelector(".header.ek-header");

  document.documentElement.classList.add("has-burger-menu");
  document.documentElement.classList.remove("has-desktop-menu");

  const isDesktop = window.innerWidth >= 768;
  if (!isDesktop || !container || !header) {
    return;
  }

  const headerStyles = getComputedStyle(header);
  const chromePadding = parseFloat(headerStyles.paddingLeft) || 0;
  const logoWidth = 380;
  const logoPaddingRight = 48;
  const clockWidth = 105;
  const searchSlot = document.querySelector(".ek-header-menu .header__menu-second");
  const searchWidth = searchSlot ? searchSlot.offsetWidth : 520;
  const navHalfWidth = container.scrollWidth / 2;
  const navLeft = window.innerWidth / 2 - navHalfWidth;
  const navRight = window.innerWidth / 2 + navHalfWidth;
  const logoRight = chromePadding + logoWidth + logoPaddingRight;
  const utilitiesLeft =
    window.innerWidth - chromePadding - clockWidth - searchWidth;

  const fits = navLeft >= logoRight && navRight <= utilitiesLeft;

  if (!fits) {
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
    desktopLinksContainerCheckSize();

    window.addEventListener("resize", desktopLinksContainerCheckSize, true);
    document.addEventListener("load", desktopLinksContainerCheckSize, true);

    desktopLinks.classList.add("is-header-initialized");
  }
}

initHeaderState();
