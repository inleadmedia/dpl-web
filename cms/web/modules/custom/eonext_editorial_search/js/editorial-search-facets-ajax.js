/**
 * @file
 * Use Views AJAX instead of full page reload when editorial search facets change.
 */

(function ($, Drupal, once, drupalSettings) {
  'use strict';

  /**
   * Find the Views AJAX instance attached to the editorial search view element.
   */
  function getViewAjaxInstance($view) {
    const instances = Drupal.views.instances || {};
    return Object.values(instances).find(
      (instance) => instance.$view && instance.$view.length && instance.$view.is($view),
    ) || null;
  }

  /**
   * Build the Views AJAX request URL from the current browser query string.
   */
  function buildViewsAjaxUrl(baseUrl) {
    let queryString = window.location.search || '';
    if (queryString === '') {
      return baseUrl;
    }

    queryString = queryString
      .slice(1)
      .replace(/q=[^&]+&?|page=[^&]+&?|&?render=[^&]+/, '');

    if (queryString === '') {
      return baseUrl;
    }

    const separator = /\?/.test(baseUrl) ? '&' : '?';
    return baseUrl + separator + queryString;
  }

  /**
   * Update the request URL on a Views or Drupal Ajax object.
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

    if (typeof ajaxObject.url === 'string') {
      ajaxObject.url = updatedUrl;
    }

    if (ajaxObject.options) {
      ajaxObject.options.url = updatedUrl;
    }
  }

  /**
   * Trigger a Views AJAX refresh for the editorial search view.
   */
  function refreshEditorialSearchView($view) {
    const instance = getViewAjaxInstance($view);
    if (!instance) {
      return false;
    }

    const ajaxPath = drupalSettings.views.ajax_path;
    const basePath = Array.isArray(ajaxPath) ? ajaxPath[0] : ajaxPath;
    const updatedUrl = buildViewsAjaxUrl(basePath);

    updateAjaxUrl(instance, updatedUrl);
    updateAjaxUrl(instance.refreshViewAjax, updatedUrl);

    if (instance.refreshViewAjax && typeof instance.refreshViewAjax.execute === 'function') {
      instance.refreshViewAjax.execute();
    }
    else {
      instance.$view.trigger('RefreshView');
    }

    return true;
  }

  /**
   * Handle facet filter clicks via Views AJAX.
   */
  function handleFacetsFilter(event, url) {
    event.preventDefault();

    const $widget = $(event.currentTarget);
    const $view = $widget.closest('.editorial-search');

    if (!$view.length) {
      window.location = url;
      return;
    }

    $view.find('.js-facets-widget').trigger('facets_filtering');
    window.history.pushState({}, document.title, url);

    if (!refreshEditorialSearchView($view)) {
      window.location = url;
    }
  }

  /**
   * Override default facets navigation after the core facets behavior attaches.
   */
  Drupal.behaviors.facetsFilterEditorialOverride = {
    attach(context) {
      $(once('editorial-search-facets-ajax', '.editorial-search .js-facets-widget', context)).each(function () {
        $(this).off('facets_filter.facets').on('facets_filter.facets', handleFacetsFilter);
      });

      once('editorial-search-popstate', 'body', context).forEach(() => {
        window.addEventListener('popstate', () => {
          const $view = $('.editorial-search').first();
          if ($view.length) {
            refreshEditorialSearchView($view);
          }
        });
      });
    },
  };
})(jQuery, Drupal, once, drupalSettings);
