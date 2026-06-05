/**
 * @file
 * Editorial search facet sidebar: collapsible groups and design-system checkboxes.
 */

(function (Drupal, once) {
  'use strict';

  const FACET_VISIBLE_LIMIT = 5;

  const CHECKBOX_ICON_SVG =
    '<svg width="20" height="20" aria-hidden="true">' +
    '<polyline points="1.5 6 4.5 9 10.5 1" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"></polyline>' +
    '</svg>';

  /**
   * Restyle a facets checkbox item to match the design system.
   */
  function restyleFacetCheckbox(item) {
    if (item.querySelector('.eonext-checkbox')) {
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
    label.classList.add('eonext-checkbox__label');
    label.innerHTML =
      '<span class="eonext-checkbox__icon">' + CHECKBOX_ICON_SVG + '</span>' +
      '<span class="eonext-checkbox__text">' + labelText + '</span>';

    input.classList.remove('form-checkbox', 'form-check-input');
    input.classList.add('eonext-checkbox__input');

    const checkboxWrapper = document.createElement('div');
    checkboxWrapper.className = 'eonext-checkbox';
    item.insertBefore(checkboxWrapper, input);
    checkboxWrapper.appendChild(input);
    checkboxWrapper.appendChild(label);

    if (count) {
      const countSpan = document.createElement('span');
      countSpan.className = 'eonext-search-facet-group__item-count';
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
    facetList.querySelectorAll('.eonext-search-facet-group__item').forEach(restyleFacetCheckbox);
  }

  /**
   * Remove the "View all" toggle from a facet group.
   */
  function removeViewAllToggle(facetGroup) {
    facetGroup.querySelector('.eonext-search-facet-group__view-all')?.remove();
  }

  /**
   * Create or update the "View all" / "Show less" toggle for a facet group.
   */
  function updateViewAllToggle(facetGroup, isExpanded) {
    let toggle = facetGroup.querySelector('.eonext-search-facet-group__view-all');

    if (!toggle) {
      toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'eonext-search-facet-group__view-all';
      const facetList = facetGroup.querySelector('.eonext-search-facet-group__content.facet-checkboxes');
      facetList?.after(toggle);
      toggle.addEventListener('click', () => {
        facetGroup.classList.toggle('is-facet-items-expanded');
        applyFacetItemLimit(facetGroup);
      });
    }

    toggle.textContent = isExpanded ? Drupal.t('Show less') : Drupal.t('View all');
    toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
  }

  /**
   * Show only the first few facet items, with active selections always visible.
   */
  function applyFacetItemLimit(facetGroup) {
    const facetList = facetGroup.querySelector('.eonext-search-facet-group__content.facet-checkboxes');

    if (!facetList || facetList.hasAttribute('hidden')) {
      return;
    }

    const items = Array.from(
      facetList.querySelectorAll(':scope > .eonext-search-facet-group__item'),
    );

    if (items.length <= FACET_VISIBLE_LIMIT) {
      facetGroup.classList.remove('is-facet-items-expanded');
      items.forEach((item) => item.classList.remove('is-facet-item-hidden'));
      removeViewAllToggle(facetGroup);
      return;
    }

    const isExpanded = facetGroup.classList.contains('is-facet-items-expanded');

    if (isExpanded) {
      items.forEach((item) => item.classList.remove('is-facet-item-hidden'));
      updateViewAllToggle(facetGroup, true);
      return;
    }

    let hasHidden = false;

    items.forEach((item, index) => {
      const isChecked = item.querySelector('input.facets-checkbox:checked');
      const shouldShow = index < FACET_VISIBLE_LIMIT || isChecked;
      item.classList.toggle('is-facet-item-hidden', !shouldShow);

      if (!shouldShow) {
        hasHidden = true;
      }
    });

    if (hasHidden) {
      updateViewAllToggle(facetGroup, false);
    }
    else {
      removeViewAllToggle(facetGroup);
    }
  }

  /**
   * Restyle facet checkboxes and apply the visible item limit.
   */
  function initFacetList(facetList) {
    restyleFacetList(facetList);
    const facetGroup = facetList.closest('.eonext-search-facet-group');

    if (facetGroup) {
      applyFacetItemLimit(facetGroup);
    }
  }

  /**
   * Toggle facet group visibility.
   */
  function toggleFacetGroup(button) {
    const contentId = button.getAttribute('aria-controls');
    const content = document.getElementById(contentId);
    const facetGroup = button.closest('.eonext-search-facet-group');
    const viewAllToggle = facetGroup?.querySelector('.eonext-search-facet-group__view-all');
    const chevron = button.querySelector('.eonext-search-facet-group__chevron');
    const isExpanded = button.getAttribute('aria-expanded') === 'true';

    button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');

    if (content) {
      if (isExpanded) {
        content.setAttribute('hidden', 'hidden');
        viewAllToggle?.setAttribute('hidden', 'hidden');
      }
      else {
        content.removeAttribute('hidden');
        viewAllToggle?.removeAttribute('hidden');
        initFacetList(content);
      }
    }

    if (chevron) {
      chevron.classList.toggle('eonext-search-facet-group__chevron--expanded', !isExpanded);
    }
  }

  /**
   * Returns the editorial search root element for a given context node.
   */
  function getEditorialSearchRoot(context) {
    if (context instanceof Element && context.classList.contains('eonext-editorial-search')) {
      return context;
    }

    if (context instanceof Element) {
      const nested = context.querySelector('.eonext-editorial-search');
      if (nested) {
        return nested;
      }
    }

    return document.querySelector('.eonext-editorial-search');
  }

  /**
   * Moves facet sidebar into the mobile filters dialog.
   */
  function openFacetsDialog(root) {
    const dialog = root.querySelector('[data-editorial-search-filters-dialog]');
    const facets = root.querySelector('.eonext-editorial-search__facets');
    const mount = dialog?.querySelector('[data-editorial-search-facets-dialog-mount]');

    if (!dialog || !facets || !mount || typeof dialog.showModal !== 'function') {
      return;
    }

    mount.appendChild(facets);
    facets.classList.add('eonext-editorial-search__facets--mobile-dialog');
    dialog.showModal();
  }

  /**
   * Returns facet sidebar to the results grid and closes the dialog.
   */
  function closeFacetsDialog(root) {
    const dialog = root.querySelector('[data-editorial-search-filters-dialog]');
    const facets = root.querySelector('.eonext-editorial-search__facets--mobile-dialog');
    const grid = root.querySelector('.eonext-editorial-search__grid');
    const results = root.querySelector('.eonext-editorial-search__results');

    if (dialog?.open) {
      dialog.close();
    }

    if (!facets || !grid || !results) {
      return;
    }

    facets.classList.remove('eonext-editorial-search__facets--mobile-dialog');
    grid.insertBefore(facets, results);
  }

  Drupal.behaviors.editorialSearchFacets = {
    attach(context) {
      once('editorial-search-facet-header', '.eonext-editorial-search .eonext-search-facet-group__header', context).forEach((button) => {
        button.addEventListener('click', () => toggleFacetGroup(button));
      });

      once('editorial-search-facet-checkboxes', '.eonext-editorial-search .eonext-search-facet-group__content.facet-checkboxes', context).forEach((facetList) => {
        // Facets checkbox widget runs in the same attach cycle.
        window.setTimeout(() => initFacetList(facetList), 0);
      });

      once('editorial-search-facets-dialog', '.eonext-editorial-search [data-editorial-search-filters-open]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const root = getEditorialSearchRoot(button.closest('.eonext-editorial-search') || context);
          if (root) {
            openFacetsDialog(root);
          }
        });
      });

      once('editorial-search-facets-dialog-close', '.eonext-editorial-search [data-editorial-search-filters-close]', context).forEach((button) => {
        button.addEventListener('click', () => {
          const root = getEditorialSearchRoot(button.closest('.eonext-editorial-search') || context);
          if (root) {
            closeFacetsDialog(root);
          }
        });
      });

      once('editorial-search-facets-dialog-cancel', '.eonext-editorial-search [data-editorial-search-filters-dialog]', context).forEach((dialog) => {
        dialog.addEventListener('cancel', (event) => {
          event.preventDefault();
          const root = getEditorialSearchRoot(dialog.closest('.eonext-editorial-search') || context);
          if (root) {
            closeFacetsDialog(root);
          }
        });

        dialog.addEventListener('close', () => {
          const root = getEditorialSearchRoot(dialog.closest('.eonext-editorial-search') || context);
          if (root) {
            closeFacetsDialog(root);
          }
        });
      });
    },
  };
})(Drupal, once);
