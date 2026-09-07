/**
 * @file
 * Frontend part of event status module.
 */
(function (Drupal, once, drupalSettings) {
  "use strict";
  Drupal.behaviors.event_status = {
    attach: function (context, settings) {

      function ribbonMarkup(label, color, xPos, yPos) {
        xPos = xPos || 'right';
        yPos = yPos || 'top';
        return '<div class="ribbon ribbon-' + yPos + '-' + xPos + '"><span style="background-color: ' + color + '">' + label + '</span></div>';
      }

      function insertRibbon(container, label, color) {
        if (!container || !label) {
          return;
        }

        const ribbonElement = document.createElement('div');
        ribbonElement.innerHTML = ribbonMarkup(label, color, 'right', 'top');
        container.appendChild(ribbonElement.firstChild);
      }

      function getRibbonContainer(context) {
        return context.querySelector('.content-list-item__image-container')
          || context.querySelector('.status-parent--media-container')
          || context.querySelector('.card__media')
          || context.querySelector('.hero__image .status-parent--media-container')
          || context.querySelector('.media-container');
      }

      function readFullRibbon(settings) {
        if (settings.eonextEventStatus && settings.eonextEventStatus.text) {
          return settings.eonextEventStatus;
        }
        if (typeof drupalSettings !== 'undefined' && drupalSettings.eonextEventStatus && drupalSettings.eonextEventStatus.text) {
          return drupalSettings.eonextEventStatus;
        }
        const dataElement = document.getElementById('eonext-event-status-ribbon-data');
        if (!dataElement || !dataElement.textContent) {
          return null;
        }
        try {
          return JSON.parse(dataElement.textContent);
        }
        catch (error) {
          return null;
        }
      }

      /* Full event pages: ribbon data comes from drupalSettings or .event-page. */
      once('event-status-full', '.event-page, body', context).forEach(function (element) {
        let label;
        let color;

        if (element.classList && element.classList.contains('event-page')) {
          label = element.dataset.ribbonLabel;
          color = element.dataset.ribbonColor;
        }
        else {
          const fullRibbon = readFullRibbon(settings);
          if (!fullRibbon || !fullRibbon.text) {
            return;
          }
          label = fullRibbon.text;
          color = fullRibbon.color;
        }

        if (!label) {
          return;
        }

        const hero = document.querySelector('.hero');
        if (!hero) {
          return;
        }

        insertRibbon(getRibbonContainer(hero), label, color);
      });

      /* List/teaser/card views: ribbon data is on the rendered entity element. */
      once('event-status', '[data-ribbon-label]', context).forEach(function (display) {
        const label = display.dataset.ribbonLabel;
        const color = display.dataset.ribbonColor;
        insertRibbon(getRibbonContainer(display), label, color);
      });
    }
  };
})(Drupal, once, drupalSettings);
