// start the Stimulus application
import "./bootstrap";
import "./js/e30v.js";

import Idiomorph from "idiomorph";
import {
  shouldPerformTransition,
  performTransition,
} from "turbo-view-transitions";

let prevPath = window.location.pathname;

const morphRender = (prevEl, newEl) => {
  return Idiomorph.morph(prevEl, newEl, {
    callbacks: {
      beforeNodeMorphed: (fromEl, toEl) => {
        if (typeof fromEl !== "object" || !fromEl.hasAttribute) return true;
        if (fromEl.isEqualNode(toEl)) return false;

        if (
          fromEl.hasAttribute("data-morph-permanent") &&
          toEl.hasAttribute("data-morph-permanent")
        ) {
          return false;
        }

        return true;
      },
    },
  });
};
document.addEventListener("turbo:before-render", (event) => {
  Turbo.navigator.currentVisit.scrolled = prevPath === window.location.pathname;
  prevPath = window.location.pathname;

  event.detail.render = async (prevEl, newEl) => {
    await new Promise((resolve) => setTimeout(() => resolve(), 0));
    await morphRender(prevEl, newEl);
  };

  if (shouldPerformTransition()) {
    // Make sure rendering is synchronous in this case
    event.detail.render = (prevEl, newEl) => {
      morphRender(prevEl, newEl);
    };

    event.preventDefault();

    performTransition(document.body, event.detail.newBody, async () => {
      await event.detail.resume();
    });
  }
});
