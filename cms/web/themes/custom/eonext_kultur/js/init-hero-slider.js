(function () {
  function parseItemsPerView(root) {
    const raw = parseInt(root.dataset.itemsPerView || '2', 10);
    if (Number.isNaN(raw)) {
      return 2;
    }
    return Math.max(1, Math.min(8, raw));
  }

  function initHeroSlider(root) {
    if (!window.Swiper || root.dataset.heroSliderInitialized === 'true') {
      return;
    }

    const slides = root.querySelectorAll('.ek-hero-slider__slide');
    if (slides.length === 0) {
      return;
    }

    root.dataset.heroSliderInitialized = 'true';

    const itemsPerView = parseItemsPerView(root);
    const canLoop = slides.length > itemsPerView;

    // eslint-disable-next-line no-new, no-undef
    new window.Swiper(root.querySelector('.ek-hero-slider__viewport'), {
      wrapperClass: 'ek-hero-slider__wrapper',
      slideClass: 'ek-hero-slider__slide',
      slidesPerView: 1,
      spaceBetween: 12,
      loop: canLoop,
      breakpoints: {
        768: {
          slidesPerView: itemsPerView,
          spaceBetween: 16,
        },
      },
      navigation: {
        nextEl: root.querySelector('.ek-hero-slider__nav--next'),
        prevEl: root.querySelector('.ek-hero-slider__nav--prev'),
      },
      a11y: {
        slideRole: 'listitem',
      },
    });
  }

  function initAllHeroSliders() {
    document.querySelectorAll('.ek-hero-slider').forEach(initHeroSlider);
  }

  window.addEventListener('load', initAllHeroSliders);
})();
