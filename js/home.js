/* KIDAN 72 — Home page */

document.getElementById("bags").innerHTML = KIDAN.bags.map(bagCard).join("");

/* Hero background video: make sure it starts right away (some phones need a nudge) */
(function () {
  var v = document.querySelector(".hero-video");
  if (!v) return;
  v.muted = true;
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  if (reduce) { v.removeAttribute("autoplay"); v.pause(); return; }
  function start() { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
  start();
  v.addEventListener("canplay", start, { once: true });
  // If the browser blocked autoplay (e.g. phone battery saver), start on the first touch or scroll
  ["touchstart", "click", "scroll"].forEach(function (e) {
    window.addEventListener(e, function () { if (v.paused) start(); }, { once: true, passive: true });
  });
})();
