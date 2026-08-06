/**
 * Kultur header scroll behavior.
 * Novel's script uses header.offsetTop when revealing the header, which breaks
 * the compact two-row layout after scrolling. Keep the header pinned on mobile.
 */
window.addEventListener("DOMContentLoaded", () => {
  const header = document.querySelector(".header.ek-header");
  if (!header) {
    return;
  }

  const compactViewport = window.matchMedia("(max-width: 1439px)");
  let scrollDirection = "up";
  let lastScrollY = window.scrollY;

  const clearHeaderTop = () => {
    header.style.removeProperty("top");
  };

  const updateStickyHeader = () => {
    if (compactViewport.matches) {
      clearHeaderTop();
      return;
    }

    const { scrollY } = window;
    const direction = scrollY > lastScrollY ? "down" : "up";

    if (direction !== scrollDirection && Math.abs(scrollY - lastScrollY) > 5) {
      scrollDirection = direction;
    }

    lastScrollY = scrollY > 0 ? scrollY : 0;

    if (scrollDirection === "down" && scrollY > header.offsetHeight) {
      const hideOffset = header.offsetHeight + 5;
      header.style.top = `-${hideOffset}px`;
      return;
    }

    header.style.top = "0px";
  };

  compactViewport.addEventListener("change", () => {
    clearHeaderTop();
    updateStickyHeader();
  });

  window.addEventListener("scroll", updateStickyHeader, { passive: true });
  updateStickyHeader();
});
