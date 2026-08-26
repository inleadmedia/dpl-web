// SwiperJS has a hard time understanding how to deal with keyboard focus and
// setting the slider positioning.
// Because we have items with very different widths, and we use "auto" for
// the actual movement, the focus goes out of sync with the visual visibility.
// This function is called when the swiper is initialized.
// We listen on focus within the item wrapper, and when we detect it, we
// calculate our own transform value, overwriting SwiperJS.
function swiperWrapperEventInit(swiper) {
  const swiperWrapper = swiper.el.querySelector(".swiper-wrapper");

  swiperWrapper.addEventListener("focusin", () => {
    // SwiperJS really wants to remove the tranisition duraton when not in use,
    // but we still want it active when using keyboard tabbing.
    swiperWrapper.style.transitionDuration = "300ms";

    const activeSlide = swiperWrapper.querySelector(".swiper-slide-active");

    if (!activeSlide) {
      return;
    }

    let currentSibling = activeSlide;
    let translateWidth = 0;
    let sideMargins = 0;

    // Finding all previous siblings.
    while (currentSibling.previousElementSibling) {
      currentSibling = currentSibling.previousElementSibling;

      // Offset width only gives us the 'inner' width. We also need to get
      // the side margins.
      const style = getComputedStyle(currentSibling);
      sideMargins =
        parseInt(style.marginLeft, 10) + parseInt(style.marginRight, 10);

      // Set a new translate width, used in transform.
      translateWidth -= currentSibling.offsetWidth + sideMargins;
    }

    if (translateWidth === 0) {
      return;
    }

    // Calculate a new translate value, for pulling the slider.
    // We add half of the latest known sidemargin, so the active slide doesn't
    // go flush against the screen.
    translateWidth = Math.floor(translateWidth + sideMargins / 2);

    swiper.setTranslate(translateWidth);
  });
}

// We want to make the controls not controllable by keyboard, as it quickly
// becomes a confusing experience, when the active-slide is not what you get to
// when you tab along.
function disableKeyboardNavigation(swiper) {
  const controls = swiper.el.querySelectorAll(".swiper-next, .swiper-prev");

  controls.forEach((control) => {
    // eslint-disable-next-line no-param-reassign
    control.tabIndex = -1;
  });
}

let swiperInitialized = false;
function initSwiper() {
  if (swiperInitialized) {
    return;
  }

  swiperInitialized = true;
  // eslint-disable-next-line no-undef, @typescript-eslint/no-unused-vars
  const elementsForCarousel = document.querySelectorAll(".swiper");
  [].forEach.call(elementsForCarousel, (elementForCarousel) => {
    const isCentered = elementForCarousel.getAttribute("data-swiper-centered");
    if (isCentered) {
      elementForCarousel.classList.add("swiper--centered");
    }

    new window.Swiper(elementForCarousel, {
      slidesPerView: "auto",
      freeMode: true,
      centerInsufficientSlides: isCentered != null,
      on: {
        afterInit: (swiper) => {
          swiperWrapperEventInit(swiper);
          disableKeyboardNavigation(swiper);
        },
        transitionEnd: (swiper) => {
          disableKeyboardNavigation(swiper);
        },
      },
      a11y: {
        slideRole: "listitem",
      },
      navigation: {
        nextEl: ".swiper-next",
        prevEl: ".swiper-prev",
      },
    });
  });
}

// Method to init Swiper carousel at storybook
window.DPL_designSystem_initSwiper = function () {
  if (window.Swiper) {
    initSwiper();
  } else {
    document.querySelector("#swiper-script").onload = function () {
      initSwiper();
    };
  }
};

// Initialize the Swiper library, when the page is ready.
window.addEventListener("load", () => {
  initSwiper();
});
