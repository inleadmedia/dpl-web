((Drupal, once) => {
  Drupal.behaviors.eonextKulturLokaleTermsDialog = {
    attach(context) {
      once('lokale-terms-open', '[data-lokale-terms-open]', context).forEach((trigger) => {
        const dialogId = trigger.getAttribute('data-dialog-id');
        const dialog = dialogId ? document.getElementById(dialogId) : null;
        if (!dialog || typeof dialog.showModal !== 'function') {
          return;
        }

        trigger.addEventListener('click', () => {
          dialog.showModal();
        });
      });

      once('lokale-terms-dialog', '[data-lokale-terms-dialog]', context).forEach((dialog) => {
        const close = () => {
          if (dialog.open) {
            dialog.close();
          }
        };

        dialog.querySelectorAll('[data-lokale-terms-close]').forEach((button) => {
          button.addEventListener('click', close);
        });

        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            close();
          }
        });

        dialog.addEventListener('cancel', (event) => {
          event.preventDefault();
          close();
        });
      });
    },
  };
})(Drupal, once);
