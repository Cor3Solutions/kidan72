/* KIDAN 72 — Go-Bag shop page */
Store.ready.then(() => {

  // Photo slideshow at the top
  kitSlider(document.getElementById("kit-slider"), KIDAN.kitSlides || []);

  // The three bags
  const bags = document.getElementById("bags");
  bags.innerHTML = KIDAN.bags.map(bagCard).join("");
  swipeDots(bags);

  // Individual items, with a category filter
  const filtersEl = document.getElementById("filters");
  const catalogEl = document.getElementById("catalog");
  const countEl = document.getElementById("count");
  const usedCats = [...new Set(KIDAN.items.map(i => i.cat))];
  // Pouch buttons: show only the items packed in the KIDAN Black or the KIDAN Brown
  const pouches = KIDAN.bags.filter(b => b.contents && b.contents.length);
  const pouchOf = key => pouches.find(b => b.serial === key);
  let active = new URLSearchParams(location.search).get("cat") || "ALL";
  if (active !== "ALL" && !usedCats.includes(active) && !pouchOf(active)) active = "ALL";

  // Buttons are built once; render() only updates which one is pressed,
  // so the swipeable button row on phones keeps its position.
  filtersEl.innerHTML =
    `<button type="button" data-cat="ALL">All items</button>` +
    pouches.map(b => `<button type="button" class="pouch-btn pouch-${esc(b.serial)}" data-cat="${esc(b.serial)}"><i aria-hidden="true"></i>${esc(b.name)}</button>`).join("") +
    `<span class="filter-sep" aria-hidden="true"></span>` +
    usedCats.map(c => `<button type="button" data-cat="${esc(c)}">${esc(KIDAN.categories[c])}</button>`).join("");

  function render() {
    filtersEl.querySelectorAll("button").forEach(b => b.setAttribute("aria-pressed", b.dataset.cat === active));
    const pouch = pouchOf(active);
    const list = pouch
      ? pouch.contents.map(s => KIDAN.items.find(i => i.serial === s)).filter(Boolean)
      : KIDAN.items.filter(i => active === "ALL" || i.cat === active);
    catalogEl.innerHTML = list.map(itemCard).join("");
    countEl.textContent = pouch
      ? `${list.length} items packed in the ${pouch.name}`
      : `${list.length} of ${KIDAN.items.length} items`;
  }

  filtersEl.addEventListener("click", e => {
    const b = e.target.closest("button");
    if (!b) return;
    active = b.dataset.cat;
    const url = new URL(location.href);
    if (active === "ALL") url.searchParams.delete("cat"); else url.searchParams.set("cat", active);
    try { history.replaceState(null, "", url); } catch (err) { /* not available when opened as a local file */ }
    render();
    // Phones: bring the chosen button into view and show the list from its first item
    filtersEl.scrollTo({ left: b.offsetLeft - filtersEl.offsetLeft - 20, behavior: "smooth" });
    const bar = document.querySelector(".toolbar");
    const sticky = getComputedStyle(bar).position === "sticky";
    if (sticky) {
      const header = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-h")) || 0;
      const top = catalogEl.getBoundingClientRect().top + scrollY - header - bar.offsetHeight - 8;
      if (scrollY > top) scrollTo({ top, behavior: "smooth" });
    }
  });

  render();
  if (new URLSearchParams(location.search).get("cat")) document.getElementById("items").scrollIntoView();
});

/* Photo slideshow: swipe, tap the dots, or let it advance every 5 seconds.
   Slides whose photo is missing are left out. */
function kitSlider(box, slides) {
  if (!box) return;
  box.innerHTML = `<div class="kit-slides">${slides.map((sl, i) => `
    <figure class="kit-slide" data-fallback="${esc(sl.fallback || "")}">
      <img src="${esc(sl.src)}" alt="${esc(sl.caption || "")}" decoding="async">
      ${sl.caption ? `<figcaption>${esc(sl.caption)}</figcaption>` : ""}
    </figure>`).join("")}</div>`;
  const row = box.querySelector(".kit-slides");
  let timer = null, settled = 0;
  const done = () => {
    if (++settled < slides.length) return;
    if (!row.children.length) { box.remove(); return; }
    swipeDots(row);
    const dots = row.nextElementSibling;
    if (dots && dots.classList.contains("swipe-dots")) box.after(dots);
    if (row.children.length < 2) return;
    const next = () => {
      const max = row.scrollWidth - row.clientWidth;
      row.scrollTo({ left: row.scrollLeft >= max - 4 ? 0 : row.scrollLeft + row.clientWidth, behavior: "smooth" });
    };
    const start = () => { stop(); if (!matchMedia("(prefers-reduced-motion: reduce)").matches) timer = setInterval(next, 5000); };
    const stop = () => { clearInterval(timer); timer = null; };
    ["pointerdown", "mouseenter", "focusin"].forEach(ev => box.addEventListener(ev, stop));
    box.addEventListener("mouseleave", start);
    start();
  };
  row.querySelectorAll(".kit-slide").forEach(fig => {
    const img = fig.querySelector("img");
    if (img.complete && img.naturalWidth) { done(); return; }
    img.addEventListener("load", done, { once: true });
    img.addEventListener("error", () => {
      const fb = fig.dataset.fallback;
      if (fb && img.getAttribute("src") !== fb) { img.src = fb; return; }
      fig.remove(); done();
    });
  });
}
