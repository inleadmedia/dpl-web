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

  const clearHeaderTop = () => {
    header.style.removeProperty("top");
  };

  const updateStickyHeader = () => {
    /* CSS handles sticky; never offset top (it overlaps status messages). */
    clearHeaderTop();
  };

  compactViewport.addEventListener("change", updateStickyHeader);
  window.addEventListener("scroll", updateStickyHeader, { passive: true });
  updateStickyHeader();
});
