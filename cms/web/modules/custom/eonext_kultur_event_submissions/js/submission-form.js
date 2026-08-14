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

  /**
   * Toggles the required asterisk on the Danish description when Nuuk is selected.
   *
   * Drupal's #states required on a deeply nested widget element does not
   * reliably update the label asterisk. This behaviour handles it explicitly.
   */
  Drupal.behaviors.eonextKulturSubmissionRequiredDescription = {
    attach(context) {
      once('ek-submission-required-description', 'form.ek-submission-form', context).forEach(
        (form) => {
          const cityInputs = form.querySelectorAll('input[name="city"]');
          const textarea = form.querySelector('textarea[name="description_da[0][value]"]');

          if (!textarea) {
            return;
          }

          const updateRequired = () => {
            const checked = form.querySelector('input[name="city"]:checked');
            const isNuuk = checked && checked.value === 'nuuk';

            textarea.required = isNuuk;

            const formItem = textarea.closest('.form-item');
            if (formItem) {
              const label = formItem.querySelector('label');
              if (label) {
                label.classList.toggle('js-form-required', isNuuk);
                label.classList.toggle('form-required', isNuuk);
                label.classList.toggle('input-label--required', isNuuk);
              }
            }
          };

          cityInputs.forEach((input) => input.addEventListener('change', updateRequired));
          updateRequired();
        },
      );
    },
  };
})(Drupal, once);
