/* KIDAN 72 — Go-Bag shop page */
Store.ready.then(() => {

  // Photo slideshow at the top
  kitSlider(document.getElementById("kit-slider"), KIDAN.kitSlides || []);

  // The bag(s)
  const bags = document.getElementById("bags");
  bags.innerHTML = KIDAN.bags.map(bagCard).join("");
  bags.classList.toggle("single", KIDAN.bags.length === 1);
  swipeDots(bags);

  // Individual items, with a category filter
  const filtersEl = document.getElementById("filters");
  const catalogEl = document.getElementById("catalog");
  const countEl = document.getElementById("count");
  const usedCats = [...new Set(KIDAN.items.map(i => i.cat))];
  // Pouch buttons (items packed in one pouch): only when there are several
  // packed pouches. Since the Oct 2026 revision the bag holds everything.
  const packed = KIDAN.bags.filter(b => b.contents && b.contents.length);
  const pouches = packed.length > 1 ? packed : [];
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

  // Search box: matches the name, serial number (with or without dashes,
  // e.g. "WTR-001" or "wtr001"), description, kit parts or category.
  const searchEl = document.getElementById("kit-search");
  const clearEl = document.getElementById("kit-search-clear");
  const bagHits = document.getElementById("search-bags");
  const fold = s => String(s || "").toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
  const squash = s => fold(s).replace(/[^a-z0-9]/g, "");
  let query = (new URLSearchParams(location.search).get("q") || "").trim();
  searchEl.value = query;

  function matches(thing, q) {
    const words = fold(q).split(/\s+/).filter(Boolean);
    const text = fold([thing.name, thing.serial, thing.short, thing.label, (thing.parts || []).join(" "), KIDAN.categories[thing.cat]].join(" "));
    const serialHit = squash(q).length >= 3 && squash(thing.serial).includes(squash(q));
    return serialHit || words.every(w => text.includes(w));
  }

  function render() {
    const searching = query.length > 0;
    filtersEl.querySelectorAll("button").forEach(b => b.setAttribute("aria-pressed", !searching && b.dataset.cat === active));
    clearEl.hidden = !searching;
    const pouch = searching ? null : pouchOf(active);
    const list = searching
      ? KIDAN.items.filter(i => matches(i, query))
      : pouch
        ? pouch.contents.map(s => KIDAN.items.find(i => i.serial === s)).filter(Boolean)
        : KIDAN.items.filter(i => active === "ALL" || i.cat === active);

    // Bags that match the search are linked above the items
    const bags = searching ? KIDAN.bags.filter(b => matches(b, query)) : [];
    bagHits.hidden = !bags.length;
    bagHits.innerHTML = bags.length ? `<span class="serial">Bags:</span>` +
      bags.map(b => `<a href="${detailUrl(b.serial)}">${esc(b.name)} <span class="serial">${esc(b.serial)}</span></a>`).join("") : "";

    catalogEl.innerHTML = list.length ? list.map(itemCard).join("") : `<div class="empty">
        <h3>No items found for “${esc(query)}”</h3>
        <p>Try a shorter word, or a serial number such as K72-WTR-001.</p>
        <div><button type="button" class="btn btn-dark" data-clear-search>Show all items</button></div>
      </div>`;
    countEl.textContent = searching
      ? `${list.length} ${list.length === 1 ? "item matches" : "items match"} “${query}”`
      : pouch
        ? `${list.length} items packed in the ${pouch.name}`
        : `${list.length} of ${KIDAN.items.length} items`;
  }

  function setQuery(q) {
    query = q.trim();
    const url = new URL(location.href);
    if (query) url.searchParams.set("q", query); else url.searchParams.delete("q");
    try { history.replaceState(null, "", url); } catch (err) { /* not available when opened as a local file */ }
    render();
  }
  let typing;
  searchEl.addEventListener("input", () => { clearTimeout(typing); typing = setTimeout(() => setQuery(searchEl.value), 120); });
  searchEl.addEventListener("keydown", e => {
    if (e.key === "Enter") { e.preventDefault(); setQuery(searchEl.value); searchEl.blur(); }
    if (e.key === "Escape") { searchEl.value = ""; setQuery(""); }
  });
  const clearSearch = () => { searchEl.value = ""; setQuery(""); searchEl.focus(); };
  clearEl.addEventListener("click", clearSearch);
  catalogEl.addEventListener("click", e => { if (e.target.closest("[data-clear-search]")) clearSearch(); });

  filtersEl.addEventListener("click", e => {
    const b = e.target.closest("button");
    if (!b) return;
    active = b.dataset.cat;
    // Picking a category ends a search
    if (query) { query = ""; searchEl.value = ""; }
    const url = new URL(location.href);
    url.searchParams.delete("q");
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
  const params = new URLSearchParams(location.search);
  if (params.get("cat") || params.get("q")) document.getElementById("items").scrollIntoView();
});

/* Photo slideshow: swipe, tap the dots, or let it advance every 5 seconds.
   Slides whose photo is missing are left out. */
function kitSlider(box, slides) {
  if (!box) return;
  box.innerHTML = `<div class="kit-slides" tabindex="0" role="region" aria-label="Kit photos (scroll sideways)">${slides.map((sl, i) => `
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
