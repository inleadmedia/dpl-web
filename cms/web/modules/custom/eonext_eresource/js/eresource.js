(function (Drupal, once) {
  Drupal.behaviors.convertEcategoryToSelect = {
    attach(context) {
      const wrapper = once('convert-select', '.e-resource-category-filter', context);
      if (!wrapper.length) return;

      const container = wrapper[0];
      const originalUl = container.querySelector('ul');
      if (!originalUl) return;

      // Store original HTML for restoration
      const originalHTML = originalUl.outerHTML;
      let select = null;
      let resizeTimeout = null;

      function throttle(func, delay) {
        return function(...args) {
          if (resizeTimeout) clearTimeout(resizeTimeout);
          resizeTimeout = setTimeout(() => func.apply(this, args), delay);
        };
      }

      function createSelect() {
        const ul = container.querySelector('ul');
        if (!ul || select) return;

        // Create dropdown wrapper
        const dropdownWrapper = document.createElement('div');
        dropdownWrapper.className = 'dropdown';

        select = document.createElement('select');
        select.className = 'form-select dropdown__select';
        select.onchange = function () {
          if (this.value) window.location.href = this.value;
        };

        ul.querySelectorAll('li a').forEach(link => {
          const option = document.createElement('option');
          option.className = 'dropdown__option';
          option.value = link.getAttribute('href');
          option.textContent = link.textContent;
          if (link.classList.contains('active')) {
            option.selected = true;
          }
          select.appendChild(option);
        });

        // Create arrows div
        const arrowsDiv = document.createElement('div');
        arrowsDiv.className = 'dropdown__arrows';

        const arrowImg = document.createElement('img');
        arrowImg.className = 'dropdown__arrow';
        arrowImg.src = '/themes/custom/novel/assets/dpl-design-system/icons/collection/ExpandMore.svg';
        arrowImg.alt = '';

        arrowsDiv.appendChild(arrowImg);

        // Assemble the dropdown
        dropdownWrapper.appendChild(select);
        dropdownWrapper.appendChild(arrowsDiv);

        ul.replaceWith(dropdownWrapper);
      }

      function restoreList() {
        if (!select) return;

        select.replaceWith(new DOMParser().parseFromString(originalHTML, 'text/html').body.firstChild);
        select = null;
      }

      function handleResize() {
        if (window.innerWidth <= 950 && !select) {
          createSelect();
        } else if (window.innerWidth > 950 && select) {
          restoreList();
        }
      }

      // Initial setup
      handleResize();

      // Add throttled resize listener
      window.addEventListener('resize', throttle(handleResize, 250));
    }
  };
})(Drupal, once);
