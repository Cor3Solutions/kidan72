/* KIDAN 72 — Home page */

document.getElementById("bags").innerHTML = KIDAN.bags.map(bagCard).join("");

/* Hero background video: loads after the photo, skipped for "reduce motion" and data-saver users */
(function () {
  var v = document.querySelector(".hero-video");
  if (!v) return;
  var reduce = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var saver = navigator.connection && navigator.connection.saveData;
  if (reduce || saver) { v.remove(); return; }
  v.addEventListener("playing", function () { v.classList.add("is-playing"); });
  v.addEventListener("error", function () { v.remove(); }, true);
  var small = window.matchMedia && window.matchMedia("(max-width: 760px)").matches;
  v.src = small ? v.dataset.srcMobile : v.dataset.src;
  v.muted = true;
  var p = v.play();
  if (p && p.catch) p.catch(function () { v.remove(); });
})();
