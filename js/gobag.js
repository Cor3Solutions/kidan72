/* KIDAN 72 — Go-Bag shop page */
Store.ready.then(() => {

  // The three bags
  const bags = document.getElementById("bags");
  bags.innerHTML = KIDAN.bags.map(bagCard).join("");
  swipeDots(bags);

  // Individual items, with a category filter
  const filtersEl = document.getElementById("filters");
  const catalogEl = document.getElementById("catalog");
  const countEl = document.getElementById("count");
  const usedCats = [...new Set(KIDAN.items.map(i => i.cat))];
  // Pouch buttons: show only the items packed in the Black KIDAN or the Coyote KIDAN
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
