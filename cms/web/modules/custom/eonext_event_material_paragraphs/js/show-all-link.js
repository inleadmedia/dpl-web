/**
 * @file
 * Replaces the event list "Show all" expand button with an Explore page link.
 *
 * Runs after novel/show-more so link mode overrides the default expand behavior
 * when the wrapper carries data-show-all-link-config.
 */
document.addEventListener('DOMContentLoaded', () => {
  document
    .querySelectorAll('[data-show-more-list-wrapper][data-show-all-link-config]')
    .forEach((wrapper) => {
      const url = wrapper.getAttribute('data-show-all-link-config');
      if (!url) {
        return;
      }

      const button = wrapper.querySelector('[data-show-more-button]');
      let label = 'Show all';
      if (button) {
        const showMoreText = button.getAttribute('data-show-more-text');
        label = (showMoreText && showMoreText.trim()) || button.textContent.trim() || label;
      }

      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.className = button
        ? button.className
        : 'filtered-event-list__button btn-primary btn-outline btn-medium';
      anchor.textContent = label;

      if (button) {
        button.replaceWith(anchor);
      } else {
        wrapper.appendChild(anchor);
      }

      // Link mode navigates away; reveal all items instead of truncating.
      wrapper
        .querySelectorAll('[data-show-more-item].show-more__hidden')
        .forEach((item) => {
          item.classList.remove('show-more__hidden');
        });
    });
});
