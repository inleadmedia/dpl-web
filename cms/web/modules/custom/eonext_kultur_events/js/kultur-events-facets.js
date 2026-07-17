/**
 * @file
 * Kultur events top-bar facet checkboxes.
 */
(function (Drupal, once) {
  "use strict";

  const FACET_VISIBLE_LIMIT = 8;
  const ROOT_SELECTOR = ".eonext-kultur-events";
  const FILTERS_STORAGE_KEY = "eonext-kultur-events-filters-expanded";
  const CHECKBOX_ICON_SVG =
    '<svg width="20" height="20" aria-hidden="true">' +
    '<polyline points="1.5 6 4.5 9 10.5 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></polyline>' +
    "</svg>";

  function restyleFacetCheckbox(item) {
    if (item.querySelector(".eonext-checkbox")) {
      return;
    }

    const input = item.querySelector("input.facets-checkbox");
    const label = item.querySelector("label.form-check-label");

    if (input && label) {
      restyleFacetInputItem(item, input, label);
      return;
    }

    const link = item.querySelector("a[data-drupal-facet-item-id]");
    if (!link) {
      return;
    }

    restyleFacetLinkItem(item, link);
  }

  function restyleFacetInputItem(item, input, label) {
    const countElement = label.querySelector(".facet-item__count");
    if (countElement) {
      countElement.remove();
    }

    const valueElement = label.querySelector(".facet-item__value");
    const labelText = valueElement
      ? valueElement.textContent.trim()
      : label.textContent.trim();
    if (valueElement) {
      valueElement.remove();
    }

    label.classList.remove("form-check-label");
    label.classList.add("eonext-checkbox__label");
    label.innerHTML =
      '<span class="eonext-checkbox__icon">' +
      CHECKBOX_ICON_SVG +
      '</span><span class="eonext-checkbox__text">' +
      labelText +
      "</span>";

    input.classList.remove("form-checkbox", "form-check-input");
    input.classList.add("eonext-checkbox__input");

    const checkboxWrapper = document.createElement("div");
    checkboxWrapper.className = "eonext-checkbox";
    item.insertBefore(checkboxWrapper, input);
    checkboxWrapper.appendChild(input);
    checkboxWrapper.appendChild(label);
  }

  function restyleFacetLinkItem(item, link) {
    const isActive = link.classList.contains("is-active");
    const valueElement = link.querySelector(".facet-item__value");
    const countElement = link.querySelector(".facet-item__count");
    if (countElement) {
      countElement.remove();
    }

    const labelText = valueElement
      ? valueElement.textContent.trim()
      : link.textContent.trim();

    if (item.classList.contains("eonext-kultur-events__facet-option--toggle")) {
      if (link.querySelector(".eonext-kultur-events__free-toggle")) {
        return;
      }

      link.innerHTML =
        '<span class="eonext-kultur-events__free-toggle' +
        (isActive ? " is-active" : "") +
        '">' +
        '<span class="eonext-kultur-events__free-toggle__text">' +
        labelText +
        "</span>" +
        '<span class="eonext-kultur-events__free-toggle__switch" aria-hidden="true"></span>' +
        "</span>";
      link.setAttribute("role", "switch");
      link.setAttribute("aria-checked", isActive ? "true" : "false");
      link.setAttribute("aria-label", labelText);
      return;
    }

    link.innerHTML =
      '<span class="eonext-checkbox">' +
      '<span class="eonext-checkbox__icon' +
      (isActive ? " is-checked" : "") +
      '">' +
      CHECKBOX_ICON_SVG +
      "</span>" +
      '<span class="eonext-checkbox__text">' +
      labelText +
      "</span>" +
      "</span>";
  }

  function restyleFacetList(facetList) {
    facetList
      .querySelectorAll(".eonext-search-facet-group__item")
      .forEach(restyleFacetCheckbox);
  }

  function removeViewAllToggle(facetGroup) {
    facetGroup.querySelector(".eonext-search-facet-group__view-all")?.remove();
  }

  function updateViewAllToggle(facetGroup, isExpanded) {
    let toggle = facetGroup.querySelector(".eonext-search-facet-group__view-all");
    const facetList = facetGroup.querySelector(".eonext-kultur-events__facet-options");

    if (!toggle) {
      toggle = document.createElement("button");
      toggle.type = "button";
      toggle.className = "eonext-search-facet-group__view-all";
      facetList?.after(toggle);
      toggle.addEventListener("click", () => {
        facetGroup.classList.toggle("is-facet-items-expanded");
        applyFacetItemLimit(facetGroup);
      });
    }

    toggle.textContent = isExpanded
      ? Drupal.t("Show less")
      : Drupal.t("View all");
    toggle.setAttribute("aria-expanded", isExpanded ? "true" : "false");
  }

  function applyFacetItemLimit(facetGroup) {
    const facetList = facetGroup.querySelector(".eonext-kultur-events__facet-options");
    if (!facetList) {
      return;
    }

    const items = Array.from(
      facetList.querySelectorAll(":scope > .eonext-search-facet-group__item")
    );

    if (items.length <= FACET_VISIBLE_LIMIT) {
      facetGroup.classList.remove("is-facet-items-expanded");
      items.forEach((item) => item.classList.remove("is-facet-item-hidden"));
      removeViewAllToggle(facetGroup);
      return;
    }

    const isExpanded = facetGroup.classList.contains("is-facet-items-expanded");
    if (isExpanded) {
      items.forEach((item) => item.classList.remove("is-facet-item-hidden"));
      updateViewAllToggle(facetGroup, true);
      return;
    }

    let hasHidden = false;
    items.forEach((item, index) => {
      const isChecked =
        item.querySelector("input.facets-checkbox:checked") ||
        item.querySelector("a.is-active");
      const shouldShow = index < FACET_VISIBLE_LIMIT || isChecked;
      item.classList.toggle("is-facet-item-hidden", !shouldShow);
      if (!shouldShow) {
        hasHidden = true;
      }
    });

    if (hasHidden) {
      updateViewAllToggle(facetGroup, false);
    } else {
      removeViewAllToggle(facetGroup);
    }
  }

  function initFreeToggle(facetGroup) {
    const link = facetGroup.querySelector(
      ".eonext-kultur-events__facet-option--toggle a[data-drupal-facet-item-id]"
    );
    if (!link || link.dataset.kulturFreeToggleInit) {
      return;
    }

    link.dataset.kulturFreeToggleInit = "true";
    restyleFacetCheckbox(link.closest(".eonext-kultur-events__facet-option--toggle"));

    link.addEventListener("click", () => {
      const willBeActive = !link.classList.contains("is-active");
      const toggle = link.querySelector(".eonext-kultur-events__free-toggle");
      if (toggle) {
        toggle.classList.toggle("is-active", willBeActive);
      }
      link.setAttribute("aria-checked", willBeActive ? "true" : "false");
    });
  }

  function initFacetGroup(facetGroup) {
    if (facetGroup.classList.contains("eonext-kultur-events__facet-group--free-toggle")) {
      initFreeToggle(facetGroup);
      return;
    }

    const facetList = facetGroup.querySelector(".eonext-kultur-events__facet-options");
    if (!facetList) {
      return;
    }

    restyleFacetList(facetList);
    applyFacetItemLimit(facetGroup);
  }

  function isFiltersExpanded() {
    const stored = sessionStorage.getItem(FILTERS_STORAGE_KEY);
    if (stored === null) {
      return window.matchMedia("(min-width: 768px)").matches;
    }

    return stored === "1";
  }

  function updateFiltersPanelState(panel, toggle, expanded) {
    panel.classList.toggle("is-expanded", expanded);
    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    toggle.setAttribute(
      "aria-label",
      expanded
        ? Drupal.t("Hide filters", {}, { context: "eonext_kultur_events" })
        : Drupal.t("Show filters", {}, { context: "eonext_kultur_events" })
    );
    sessionStorage.setItem(FILTERS_STORAGE_KEY, expanded ? "1" : "0");
  }

  function initFiltersPanel(root) {
    const panel = root.querySelector(".eonext-kultur-events__filters-panel");
    const toggle = root.querySelector(".eonext-kultur-events__filters-toggle");

    if (!panel || !toggle || toggle.dataset.kulturFiltersToggleInit) {
      return;
    }

    toggle.dataset.kulturFiltersToggleInit = "true";
    updateFiltersPanelState(panel, toggle, isFiltersExpanded());

    toggle.addEventListener("click", () => {
      updateFiltersPanelState(
        panel,
        toggle,
        !panel.classList.contains("is-expanded")
      );
    });
  }

  Drupal.behaviors.kulturEventsFacets = {
    attach(context) {
      once("kultur-events-root", ROOT_SELECTOR, context).forEach((root) => {
        initFiltersPanel(root);
      });

      once(
        "kultur-events-facet-groups",
        ROOT_SELECTOR + " .eonext-kultur-events__facet-group",
        context
      ).forEach((facetGroup) => {
        window.setTimeout(() => initFacetGroup(facetGroup), 0);
      });
    },
  };
})(Drupal, once);
