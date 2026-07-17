(function () {
  function initArticlesSlider(root) {
    if (!window.Swiper || root.dataset.articlesSliderInitialized === 'true') {
      return;
    }

    const slides = root.querySelectorAll('.ek-articles-slider__slide');
    if (slides.length === 0) {
      return;
    }

    root.dataset.articlesSliderInitialized = 'true';

    // Fixed-width slides (CSS); viewport max-width caps how many are visible.
    // eslint-disable-next-line no-new, no-undef
    new window.Swiper(root.querySelector('.ek-articles-slider__viewport'), {
      wrapperClass: 'ek-articles-slider__wrapper',
      slideClass: 'ek-articles-slider__slide',
      slidesPerView: 'auto',
      spaceBetween: 16,
      grabCursor: true,
      breakpoints: {
        768: {
          spaceBetween: 24,
        },
      },
      a11y: {
        slideRole: 'listitem',
      },
    });
  }

  function initAllArticlesSliders() {
    document.querySelectorAll('.ek-articles-slider').forEach(initArticlesSlider);
  }

  window.addEventListener('load', initAllArticlesSliders);
})();
