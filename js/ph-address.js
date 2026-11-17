/* =========================================================
   KIDAN 72 — Philippine address dropdowns
   Province → City / Municipality → Barangay, from the PSA list
   in assets/data/ph/ (each province's barangays load only when
   that province is picked, so the page stays light on phones).

   Used by the My Order page and the admin customer page:
     PHAddress.attach({ province: sel, city: sel, barangay: sel,
                        base: "assets/data/ph/", value: {province, city, barangay} })
   Returns a promise: true when the lists loaded, false when they
   could not (e.g. the page was opened straight from a file), so the
   page can show a plain address box instead.
   ========================================================= */

const PHAddress = (function () {
  const cache = {};
  const getJSON = url => (cache[url] = cache[url] || fetch(url).then(r => {
    if (!r.ok) throw new Error("Could not load " + url);
    return r.json();
  }));
  const opt = (value, label, selected) => {
    const o = document.createElement("option");
    o.value = value; o.textContent = label;
    if (selected) o.selected = true;
    return o;
  };
  const fill = (sel, placeholder, names, chosen) => {
    sel.replaceChildren(opt("", placeholder));
    names.forEach(n => sel.append(opt(n, n, n === chosen)));
    sel.disabled = names.length === 0;
  };

  async function attach({ province, city, barangay, base = "assets/data/ph/", value = {}, onChange = () => {} }) {
    let list;
    try { list = await getJSON(base + "provinces.json"); }
    catch (e) { console.warn("KIDAN 72: address lists unavailable.", e); return false; }

    const groupOf = name => list.find(g => g.n === name);
    const cityOf = (g, name) => g && g.c.find(c => c.n === name);

    fill(province, "Choose province", list.map(g => g.n), value.province);
    fill(city, "Choose province first", [], "");
    fill(barangay, "Choose city first", [], "");

    async function showCities(chosenCity, chosenBrgy) {
      const g = groupOf(province.value);
      fill(city, g ? "Choose city / municipality" : "Choose province first", g ? g.c.map(c => c.n) : [], chosenCity);
      await showBarangays(chosenBrgy);
    }
    async function showBarangays(chosenBrgy) {
      const g = groupOf(province.value), c = cityOf(g, city.value);
      if (!c) { fill(barangay, "Choose city first", [], ""); return; }
      barangay.disabled = true;
      barangay.replaceChildren(opt("", "Loading barangays…"));
      try {
        const data = await getJSON(base + "brgy/" + g.f + ".json");
        if (city.value !== c.n) return;            // the customer changed city while loading
        fill(barangay, "Choose barangay", data[c.k] || [], chosenBrgy);
      } catch (e) {
        fill(barangay, "Barangay list unavailable", [], "");
      }
    }

    province.addEventListener("change", async () => { await showCities("", ""); onChange(); });
    city.addEventListener("change", async () => { await showBarangays(""); onChange(); });
    barangay.addEventListener("change", () => onChange());

    if (value.province) await showCities(value.city, value.barangay);
    return true;
  }

  /* "123 Rizal St., Brgy. Barandal, Calamba City, Laguna" */
  function format(a) {
    const brgy = a.barangay ? (/^(barangay|brgy|poblacion)\b/i.test(a.barangay) ? a.barangay : "Brgy. " + a.barangay) : "";
    return [a.street, brgy, a.city, a.province].map(s => (s || "").trim()).filter(Boolean).join(", ");
  }

  return { attach, format };
})();
