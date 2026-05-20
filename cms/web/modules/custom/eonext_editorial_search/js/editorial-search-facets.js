/**
 * @file
 * Editorial search facet sidebar: collapsible groups and design-system checkboxes.
 */

(function (Drupal, once) {
  'use strict';

  const CHECKBOX_ICON_SVG =
    '<svg width="20" height="20" aria-hidden="true">' +
    '<polyline points="1.5 6 4.5 9 10.5 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></polyline>' +
    '</svg>';

  /**
   * Restyle a facets checkbox item to match the design system.
   */
  function restyleFacetCheckbox(item) {
    if (item.querySelector('.checkbox')) {
      return;
    }

    const input = item.querySelector('input.facets-checkbox');
    const label = item.querySelector('label.form-check-label');

    if (!input || !label) {
      return;
    }

    const countElement = label.querySelector('.facet-item__count');
    let count = '';

    if (countElement) {
      count = countElement.textContent.replace(/[()]/g, '').trim();
      countElement.remove();
    }

    const valueElement = label.querySelector('.facet-item__value');
    const labelText = valueElement ? valueElement.textContent.trim() : label.textContent.trim();

    if (valueElement) {
      valueElement.remove();
    }

    label.classList.remove('form-check-label');
    label.classList.add('checkbox__label');
    label.innerHTML =
      '<span class="checkbox__icon">' + CHECKBOX_ICON_SVG + '</span>' +
      '<span class="checkbox__text">' + labelText + '</span>';

    input.classList.remove('form-checkbox', 'form-check-input');
    input.classList.add('checkbox__input');

    const checkboxWrapper = document.createElement('div');
    checkboxWrapper.className = 'checkbox';
    item.insertBefore(checkboxWrapper, input);
    checkboxWrapper.appendChild(input);
    checkboxWrapper.appendChild(label);

    if (count) {
      const countSpan = document.createElement('span');
      countSpan.className = 'search-facet-group__item-count';
      countSpan.id = input.id + '-count';
      countSpan.textContent = count;
      input.setAttribute('aria-describedby', countSpan.id);
      item.appendChild(countSpan);
    }
  }

  /**
   * Apply design-system checkbox markup to all items in a facet list.
   */
  function restyleFacetList(facetList) {
    facetList.querySelectorAll('.search-facet-group__item').forEach(restyleFacetCheckbox);
  }

  /**
   * Toggle facet group visibility.
   */
  function toggleFacetGroup(button) {
    const contentId = button.getAttribute('aria-controls');
    const content = document.getElementById(contentId);
    const chevron = button.querySelector('.search-facet-group__chevron');
    const isExpanded = button.getAttribute('aria-expanded') === 'true';

    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

    if (content) {
      if (isExpanded) {
        content.setAttribute('hidden', 'hidden');
      }
      else {
        content.removeAttribute('hidden');
        restyleFacetList(content);
      }
    }

    if (chevron) {
      chevron.classList.toggle('search-facet-group__chevron--expanded', !isExpanded);
    }
  }

  Drupal.behaviors.editorialSearchFacets = {
    attach(context) {
      once('editorial-search-facet-header', '.editorial-search .search-facet-group__header', context).forEach((button) => {
        button.addEventListener('click', () => toggleFacetGroup(button));
      });

      once('editorial-search-facet-checkboxes', '.editorial-search .search-facet-group__content', context).forEach((facetList) => {
        // Facets checkbox widget runs in the same attach cycle.
        window.setTimeout(() => restyleFacetList(facetList), 0);
      });
    },
  };
})(Drupal, once);
