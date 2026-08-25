/**
 * @file
 * Frontend part of event status module.
 */
(function (Drupal, once) {
  "use strict";
  Drupal.behaviors.event_status = {
    attach: function (context) {

      function ribbonMarkup(label, color, xPos, yPos) {
        xPos = xPos || 'right';
        yPos = yPos || 'top';
        return '<div class="ribbon ribbon-' + yPos + '-' + xPos + '"><span style="background-color: ' + color + '">' + label + '</span></div>';
      }

      /* Find elements with ribbon data attributes (works regardless of class) */
      once('event-status', '[data-ribbon-label]', context).forEach(function (display) {
        const label = display.dataset.ribbonLabel;
        const color = display.dataset.ribbonColor;
        const image = display.querySelector('.media-container');

        if (image && label) {
          const ribbonElement = document.createElement('div');
          ribbonElement.innerHTML = ribbonMarkup(label, color, 'right', 'top');
          image.appendChild(ribbonElement.firstChild);
        }
      });
    }
  };
})(Drupal, once);
