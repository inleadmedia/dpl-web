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
      spaceBetween: 0,
      preventClicks: false,
      preventClicksPropagation: false,
      noSwipingClass: 'swiper-no-swiping',
      loop: canLoop,
      autoplay: canLoop ? {
        delay: 6000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      } : false,
      breakpoints: {
        768: {
          slidesPerView: itemsPerView,
          spaceBetween: 0,
        },
      },
      navigation: {
        nextEl: root.querySelector('.ek-hero-slider__nav--next'),
        prevEl: root.querySelector('.ek-hero-slider__nav--prev'),
      },
      a11y: {
        slideRole: 'listitem',
      },
      on: {
        click(swiper, event) {
          const target = event.target;
          if (!(target instanceof Element)) {
            return;
          }
          const directLink = target.closest('a.ek-hero-slide__link');
          if (directLink instanceof HTMLAnchorElement) {
            return;
          }
          const overlay = target.closest('.ek-hero-slide__overlay--linked');
          if (!overlay) {
            return;
          }
          const slideLink = overlay.querySelector('a.ek-hero-slide__link');
          if (slideLink instanceof HTMLAnchorElement) {
            event.preventDefault();
            slideLink.click();
          }
        },
      },
    });
  }

  function initAllHeroSliders() {
    document.querySelectorAll('.ek-hero-slider').forEach(initHeroSlider);
  }

  window.addEventListener('load', initAllHeroSliders);
})();
