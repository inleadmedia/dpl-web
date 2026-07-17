(function (Drupal, once) {
  Drupal.behaviors.eonextKulturEventRelatedSlider = {
    attach(context) {
      once(
        "eonext-kultur-event-related-slider",
        ".eonext-kultur-event-related .swiper",
        context
      ).forEach((element) => {
        if (element.swiper || typeof window.Swiper !== "function") {
          return;
        }

        // eslint-disable-next-line no-new
        new window.Swiper(element, {
          slidesPerView: "auto",
          freeMode: true,
          navigation: {
            nextEl: element.querySelector(".swiper-next"),
            prevEl: element.querySelector(".swiper-prev"),
          },
        });
      });
    },
  };
})(Drupal, once);
