/* KIDAN 72 — Go-Bag shop page */

// The three bags
document.getElementById("bags").innerHTML = KIDAN.bags.map(bagCard).join("");

// Individual items, with a category filter
const filtersEl = document.getElementById("filters");
const catalogEl = document.getElementById("catalog");
const countEl = document.getElementById("count");
const usedCats = [...new Set(KIDAN.items.map(i => i.cat))];
let active = new URLSearchParams(location.search).get("cat") || "ALL";
if (active !== "ALL" && !usedCats.includes(active)) active = "ALL";

function render() {
  filtersEl.innerHTML = [["ALL", "All items"], ...usedCats.map(c => [c, KIDAN.categories[c]])]
    .map(([c, label]) => `<button type="button" data-cat="${c}" aria-pressed="${c === active}">${label}</button>`).join("");
  const list = KIDAN.items.filter(i => active === "ALL" || i.cat === active);
  catalogEl.innerHTML = list.map(itemCard).join("");
  countEl.textContent = `${list.length} of ${KIDAN.items.length} items`;
}

filtersEl.addEventListener("click", e => {
  const b = e.target.closest("button");
  if (!b) return;
  active = b.dataset.cat;
  const url = new URL(location.href);
  if (active === "ALL") url.searchParams.delete("cat"); else url.searchParams.set("cat", active);
  try { history.replaceState(null, "", url); } catch (err) { /* not available when opened as a local file */ }
  render();
});

render();
if (new URLSearchParams(location.search).get("cat")) document.getElementById("items").scrollIntoView();
