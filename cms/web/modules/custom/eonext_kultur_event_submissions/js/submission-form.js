/**
 * @file
 * Keeps the image upload submittable on the public submission form.
 */

((Drupal, once) => {
  /**
   * Re-enables file inputs disabled by core when a submit button is pressed.
   *
   * Core's file.js disables every file input on mousedown of any submit button,
   * because it assumes files are already stored through an Ajax upload. This
   * form uploads the file with the final submit instead, so the input has to be
   * enabled again before the browser serialises the form, otherwise the file is
   * never sent and the image is reported as missing.
   *
   * @see Drupal.file.disableFields
   */
  Drupal.behaviors.eonextKulturSubmissionFileInput = {
    attach(context) {
      once('ek-submission-file-input', 'form.ek-submission-form', context).forEach(
        (form) => {
          form.addEventListener(
            'submit',
            () => {
              form
                .querySelectorAll('input[type="file"]')
                .forEach((input) => {
                  input.disabled = false;
                });
            },
            true,
          );
        },
      );
    },
  };

  const NUUK_CITY = 'nuuk';

  /**
   * Toggles required asterisks on labels.
   */
  const toggleRequiredLabel = (control, isRequired) => {
    if (!control) {
      return;
    }
    control.removeAttribute('required');
    const formItem = control.closest('.form-item');
    if (!formItem) {
      return;
    }
    const label = formItem.querySelector('label');
    if (label) {
      label.classList.toggle('js-form-required', isRequired);
      label.classList.toggle('form-required', isRequired);
      label.classList.toggle('input-label--required', isRequired);
    }
  };

  /**
   * Shows or hides an address subfield wrapper.
   */
  const setAddressFieldVisible = (form, fieldName, visible) => {
    const control = form.querySelector(`[name="address[0][address][${fieldName}]"]`);
    if (!control) {
      return;
    }
    const formItem = control.closest('.form-item');
    if (formItem) {
      formItem.style.display = visible ? '' : 'none';
    }
    if (!visible) {
      control.value = '';
    }
  };

  /**
   * Toggles required asterisks on Danish fields when Nuuk is selected.
   *
   * Drupal's #states required on a deeply nested widget element does not
   * reliably update the label asterisk. This behaviour handles it explicitly.
   */
  Drupal.behaviors.eonextKulturSubmissionNuukRequiredFields = {
    attach(context) {
      once('ek-submission-nuuk-required', 'form.ek-submission-form', context).forEach(
        (form) => {
          const cityInputs = form.querySelectorAll('input[name="city"]');
          const nuukFieldSelectors = [
            'textarea[name="description_da[0][value]"]',
            'input[name="title_da[0][value]"]',
          ];
          const alwaysRequiredSelectors = [
            'input[name="address[0][address][organization]"]',
            'input[name="contact_phone[0][value]"]',
          ];

          const updateRequired = () => {
            const checked = form.querySelector('input[name="city"]:checked');
            const isNuuk = checked && checked.value === NUUK_CITY;

            nuukFieldSelectors.forEach((selector) => {
              toggleRequiredLabel(form.querySelector(selector), isNuuk);
            });

            alwaysRequiredSelectors.forEach((selector) => {
              toggleRequiredLabel(form.querySelector(selector), true);
            });

            setAddressFieldVisible(form, 'postal_code', !isNuuk);
            setAddressFieldVisible(form, 'locality', !isNuuk);
            setAddressFieldVisible(form, 'address_line2', !isNuuk);
          };

          cityInputs.forEach((input) => input.addEventListener('change', updateRequired));
          updateRequired();
        },
      );
    },
  };
})(Drupal, once);
