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
})(Drupal, once);
