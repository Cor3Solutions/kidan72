/* KIDAN 72 — Home page */

Store.ready.then(() => {
  const bags = document.getElementById("bags");
  bags.innerHTML = KIDAN.bags.map(bagCard).join("");
  swipeDots(bags);
  setUpBrandFilm();
});

/* Hero video backup: the video starts by itself, but some phones
   (e.g. iPhone in Low Power Mode) hold it back until the first touch or scroll. */
(function () {
  const v = document.querySelector(".hero-video");
  if (!v) return;
  const nudge = () => { if (v.paused) { v.muted = true; const p = v.play(); if (p && p.catch) p.catch(() => {}); } };
  v.addEventListener("canplay", nudge, { once: true });
  ["touchstart", "scroll", "click"].forEach(ev => addEventListener(ev, nudge, { once: true, passive: true }));
})();

/* Brand film: plays on this page with only play/pause
   (clean player from js/site.js: no YouTube title, channel name or controls). */
function setUpBrandFilm() {
  const id = youtubeId(KIDAN.brandFilm);
  const box = document.getElementById("brand-film");
  if (!box) return;
  if (!id) { box.closest("section").hidden = true; return; }
  cleanPlayer(box, id, { label: "the KIDAN 72 film" });
}
