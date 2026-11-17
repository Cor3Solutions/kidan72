/* =========================================================
   KIDAN 72 — Builds the Province → City → Barangay lists used
   by the address dropdowns (assets/data/ph/).

   Source: PSA Philippine Standard Geographic Code (PSGC), as
   packaged in the npm package @jobuntux/psgc (data/<quarter>/).
   Only needed again when the PSA releases changes. Run:
       node tools/make-ph-address.js <folder with regions.json,
            provinces.json, muncities.json, barangays.json>
   ========================================================= */

const fs = require("fs");
const path = require("path");

const src = process.argv[2];
if (!src) { console.error("Give the PSGC data folder, e.g. node tools/make-ph-address.js node_modules/@jobuntux/psgc/data/2025-2Q"); process.exit(1); }
const load = f => JSON.parse(fs.readFileSync(path.join(src, f), "utf8"));
const provinces = load("provinces.json"), muncities = load("muncities.json"), barangays = load("barangays.json");

const out = path.join(__dirname, "..", "assets", "data", "ph");
fs.mkdirSync(path.join(out, "brgy"), { recursive: true });

const clean = s => String(s || "").replace(/\s+/g, " ").trim();
// "City of Calamba" → "Calamba City" so cities sort by their own name
const cityName = s => { s = clean(s); const m = s.match(/^City of (.+)$/i); return m ? `${m[1]} City` : s; };

// Highly urbanised / independent cities that the PSA lists on their own:
// customers look for them under the province they are in.
const HOST = {
  "301": "054", // Angeles → Pampanga
  "302": "045", // Bacolod → Negros Occidental
  "303": "011", // Baguio → Benguet
  "304": "002", // Butuan → Agusan del Norte
  "305": "043", // Cagayan de Oro → Misamis Oriental
  "306": "022", // Cebu City → Cebu
  "307": "024", // Davao City → Davao del Sur
  "308": "063", // General Santos → South Cotabato
  "309": "035", // Iligan → Lanao del Norte
  "310": "030", // Iloilo City → Iloilo
  "311": "022", // Lapu-Lapu → Cebu
  "312": "056", // Lucena → Quezon
  "313": "022", // Mandaue → Cebu
  "314": "071", // Olongapo → Zambales
  "315": "053", // Puerto Princesa → Palawan
  "316": "037", // Tacloban → Leyte
  "317": "073", // Zamboanga City → Zamboanga del Sur
  "901": "007", // Isabela City → Basilan
};
const provByCode = Object.fromEntries(provinces.map(p => [p.provCode, p]));
for (const [huc, host] of Object.entries(HOST)) {
  if (!provByCode[huc] && huc !== "901") throw new Error("PSGC changed: city code " + huc + " not found");
  if (!provByCode[host] || provByCode[host].cityClass) throw new Error("PSGC changed: province code " + host + " not found");
}

// Groups: one per real province, plus Metro Manila and the BARMM Special Geographic Area
const groups = {};
const group = (key, name) => (groups[key] = groups[key] || { n: name, f: key, c: [] });
for (const p of provinces) if (!p.cityClass && p.regCode !== "13") group(p.provCode, clean(p.provName));
group("ncr", "Metro Manila");
group("999", "Special Geographic Area (BARMM)");

const groupOf = provCode => {
  if (provCode.startsWith("8")) return "ncr";          // NCR cities and Pateros
  if (HOST[provCode]) return HOST[provCode];
  return provCode;
};

// Cities / municipalities. Manila's 14 districts are folded into "Manila".
const manilaDistricts = new Set();
const cityKey = {};      // munCityCode → key used for its barangay list
for (const m of muncities) {
  const g = groupOf(m.provCode);
  if (!groups[g]) throw new Error("No province for " + m.munCityName + " (" + m.provCode + ")");
  if (m.provCode === "806" && m.munCityCode !== "80600") { manilaDistricts.add(m.munCityCode); cityKey[m.munCityCode] = "80600"; continue; }
  cityKey[m.munCityCode] = m.munCityCode;
  groups[g].c.push({ n: m.munCityCode === "80600" ? "Manila" : cityName(m.munCityName), k: m.munCityCode });
}

// Barangays, one file per province group: { cityKey: ["Barangay", ...] }
const districtName = Object.fromEntries(muncities.filter(m => manilaDistricts.has(m.munCityCode)).map(m => [m.munCityCode, clean(m.munCityName)]));
const files = {};
let count = 0;
for (const b of barangays) {
  const key = cityKey[b.munCityCode];
  if (!key) continue;
  const g = groupOf(b.provCode);
  const name = manilaDistricts.has(b.munCityCode) ? `${clean(b.brgyName)} (${districtName[b.munCityCode]})` : clean(b.brgyName);
  ((files[g] = files[g] || {})[key] = files[g][key] || []).push(name);
  count++;
}

const sortNames = (a, b) => a.localeCompare(b, "en", { numeric: true, sensitivity: "base" });
const list = Object.values(groups).filter(g => g.c.length).map(g => {
  g.c.sort((a, b) => sortNames(a.n, b.n));
  return g;
}).sort((a, b) => a.f === "ncr" ? -1 : b.f === "ncr" ? 1 : sortNames(a.n, b.n));

fs.writeFileSync(path.join(out, "provinces.json"), JSON.stringify(list));
for (const [g, cities] of Object.entries(files)) {
  for (const k of Object.keys(cities)) cities[k].sort(sortNames);
  fs.writeFileSync(path.join(out, "brgy", g + ".json"), JSON.stringify(cities));
}
console.log(`assets/data/ph written: ${list.length} provinces, ${list.reduce((n, g) => n + g.c.length, 0)} cities/municipalities, ${count} barangays`);
