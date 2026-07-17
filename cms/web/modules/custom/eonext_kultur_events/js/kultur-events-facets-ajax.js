/**
 * @file
 * Use Views AJAX instead of full page reload when kultur events facets change.
 */
(function ($, Drupal, once, drupalSettings) {
  "use strict";

  const ROOT_SELECTOR = ".eonext-kultur-events";

  /**
   * Returns whether a query parameter key is a facet filter key.
   */
  function isFacetQueryKey(key) {
    return key === "f[]" || /^f(\[\d*\])?$/.test(key);
  }

  /**
   * Returns the kultur events view element.
   */
  function getEventsView() {
    return $(ROOT_SELECTOR).first();
  }

  /**
   * Finds the Views AJAX instance for the kultur events view.
   */
  function getViewAjaxInstance($view) {
    const instances = Drupal.views.instances || {};
    const $eventsView = $view && $view.length ? $view : getEventsView();

    if (!$eventsView.length) {
      return null;
    }

    let instance = Object.values(instances).find(
      (item) => item.$view && item.$view.length && item.$view.is($eventsView)
    );

    if (instance) {
      return instance;
    }

    const domIdClass = $eventsView
      .attr("class")
      ?.split(/\s+/)
      .find((className) => className.startsWith("js-view-dom-id-"));

    if (!domIdClass) {
      return null;
    }

    instance = Object.values(instances).find(
      (item) =>
        item.$view && item.$view.length && item.$view.hasClass(domIdClass)
    );

    return instance || null;
  }

  /**
   * Builds the Views AJAX request URL from the current browser query string.
   */
  function buildViewsAjaxUrl(baseUrl) {
    let queryString = window.location.search || "";
    if (queryString === "") {
      return baseUrl;
    }

    queryString = queryString
      .slice(1)
      .replace(/q=[^&]+&?|page=[^&]+&?|&?render=[^&]+/, "");

    if (queryString === "") {
      return baseUrl;
    }

    const separator = /\?/.test(baseUrl) ? "&" : "?";
    return baseUrl + separator + queryString;
  }

  /**
   * Updates the request URL on a Views or Drupal Ajax object.
   */
  function updateAjaxUrl(ajaxObject, updatedUrl) {
    if (!ajaxObject) {
      return;
    }

    if (ajaxObject.element_settings) {
      ajaxObject.element_settings.url = updatedUrl;
    }

    if (ajaxObject.elementSettings) {
      ajaxObject.elementSettings.url = updatedUrl;
    }

    if (typeof ajaxObject.url === "string") {
      ajaxObject.url = updatedUrl;
    }

    if (ajaxObject.options) {
      ajaxObject.options.url = updatedUrl;
    }
  }

  /**
   * Triggers a Views AJAX refresh for the kultur events view.
   */
  function refreshEventsView($view) {
    const instance = getViewAjaxInstance($view);
    if (!instance) {
      return false;
    }

    const ajaxPath = drupalSettings.views.ajax_path;
    const basePath = Array.isArray(ajaxPath) ? ajaxPath[0] : ajaxPath;
    const updatedUrl = buildViewsAjaxUrl(basePath);

    updateAjaxUrl(instance, updatedUrl);
    updateAjaxUrl(instance.refreshViewAjax, updatedUrl);

    if (
      instance.refreshViewAjax &&
      typeof instance.refreshViewAjax.execute === "function"
    ) {
      instance.refreshViewAjax.execute();
    } else {
      instance.$view.trigger("RefreshView");
    }

    return true;
  }

  /**
   * Handles facet filter clicks via Views AJAX.
   */
  function handleFacetsFilter(event, url) {
    event.preventDefault();
    event.stopImmediatePropagation();

    const $widget = $(event.currentTarget);
    const $view = $widget.closest(ROOT_SELECTOR);

    if (!$view.length) {
      window.location = url;
      return;
    }

    if (Drupal.facets && typeof Drupal.facets.disableFacet === "function") {
      Drupal.facets.disableFacet($widget);
    }

    $view.find(".js-facets-widget").trigger("facets_filtering");
    window.history.pushState({}, document.title, url);

    if (!refreshEventsView($view)) {
      window.location = url;
    }
  }

  /**
   * Syncs the free-events toggle with the active facet query param.
   */
  function syncFreeEventsToggle(context) {
    const root = context.querySelector
      ? context.querySelector(ROOT_SELECTOR)
      : document.querySelector(ROOT_SELECTOR);

    if (!root) {
      return;
    }

    const url = new URL(window.location.href);
    let isActive = false;

    url.searchParams.forEach((value, key) => {
      if (isFacetQueryKey(key) && value === "free:1") {
        isActive = true;
      }
    });

    root
      .querySelectorAll(
        ".eonext-kultur-events__facet-option--toggle a[data-drupal-facet-item-id]"
      )
      .forEach((link) => {
        link.classList.toggle("is-active", isActive);
        link.setAttribute("aria-checked", isActive ? "true" : "false");

        const toggle = link.querySelector(".eonext-kultur-events__free-toggle");
        if (toggle) {
          toggle.classList.toggle("is-active", isActive);
        }
      });
  }

  Drupal.behaviors.kulturEventsFacetsAjax = {
    weight: 100,

    attach(context) {
      $(ROOT_SELECTOR + " .js-facets-widget", context).each(function () {
        $(this)
          .off("facets_filter.facets")
          .on("facets_filter.facets", handleFacetsFilter);
      });

      once("kultur-events-popstate", "html").forEach((root) => {
        root.addEventListener("popstate", () => {
          const $view = getEventsView();
          if ($view.length) {
            refreshEventsView($view);
          }
        });
      });

      once("kultur-events-ajax-complete", "html").forEach((root) => {
        $(root).on("ajaxComplete.kulturEventsFacets", (event, xhr, settings) => {
          if (!(settings.url || "").includes("views/ajax")) {
            return;
          }

          syncFreeEventsToggle(document);
        });
      });

      syncFreeEventsToggle(context);
    },
  };
})(jQuery, Drupal, once, drupalSettings);
