/* KIDAN 72 — Home page */

document.getElementById("bags").innerHTML = KIDAN.bags.map(bagCard).join("");

/* Hero background video: make sure it starts right away (some phones need a nudge) */
(function () {
  var v = document.querySelector(".hero-video");
  if (!v) return;
  v.muted = true;
  v.setAttribute("muted", "");
  // Older browsers ignore the phone/desktop choice in the HTML, so pick the right file here too
  var phone = window.matchMedia("(max-width: 760px)").matches;
  var want = phone ? "kidan72-hero-phone.mp4" : "kidan72-hero.mp4";
  if (v.currentSrc && v.currentSrc.indexOf(want) === -1) { v.src = "assets/video/" + want; v.load(); }
  function start() { var p = v.play(); if (p && p.catch) p.catch(function () {}); }
  start();
  v.addEventListener("canplay", start, { once: true });
  // If the browser blocked autoplay (e.g. phone battery saver), start on the first touch or scroll
  ["touchstart", "click", "scroll"].forEach(function (e) {
    window.addEventListener(e, function () { if (v.paused) start(); }, { once: true, passive: true });
  });
})();
