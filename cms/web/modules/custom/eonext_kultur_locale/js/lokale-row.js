((Drupal, once) => {
  Drupal.behaviors.eonextKulturLokaleRow = {
    attach(context) {
      once('lokale-carousel', '[data-lokale-carousel]', context).forEach((carousel) => {
        const track = carousel.querySelector('[data-lokale-track]');
        const prev = carousel.querySelector('[data-lokale-prev]');
        const next = carousel.querySelector('[data-lokale-next]');
        const slides = track ? track.querySelectorAll('.ek-lokale-carousel__slide') : [];

        if (!track || slides.length <= 1) {
          return;
        }

        let index = 0;
        let touchStartX = 0;

        const update = () => {
          track.style.transform = `translateX(-${index * 100}%)`;
        };

        const goPrev = () => {
          index = (index - 1 + slides.length) % slides.length;
          update();
        };

        const goNext = () => {
          index = (index + 1) % slides.length;
          update();
        };

        prev?.addEventListener('click', goPrev);
        next?.addEventListener('click', goNext);

        carousel.addEventListener('touchstart', (event) => {
          touchStartX = event.changedTouches[0].screenX;
        }, { passive: true });

        carousel.addEventListener('touchend', (event) => {
          const delta = event.changedTouches[0].screenX - touchStartX;
          if (Math.abs(delta) < 40) {
            return;
          }
          if (delta < 0) {
            goNext();
          }
          else {
            goPrev();
          }
        }, { passive: true });

        update();
      });
    },
  };
})(Drupal, once);
