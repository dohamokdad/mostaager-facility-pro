(function(){
  "use strict";

  function scrollInsideFrame(frame, target) {
    if (!frame || !target) return;

    // If the container is scrollable, use its scrollTo.
    if (typeof frame.scrollTo === 'function') {
      frame.scrollTo({ top: target.offsetTop, behavior: 'smooth' });
      return;
    }

    // Fallback: browser window scroll
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function init() {
    const frame = document.querySelector('[data-desktop-frame="true"]');
    const landing = document.querySelector('[data-landing-hover="true"]');

    // Allow multiple demos on the same page
    const demoTargets = document.querySelectorAll('[data-demo-target="true"]');
    if (!frame || !landing || !demoTargets || !demoTargets.length) return;

    let didScroll = false;

    landing.addEventListener('mouseenter', function(){
      if (didScroll) return; // prevent repeated scroll on every hover
      didScroll = true;
      scrollInsideFrame(frame, demoTargets[0]);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

