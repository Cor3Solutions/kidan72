/* KIDAN 72 — Readiness Check */

const checksEl = document.getElementById("checks");
const STORE = "k72-readiness";
let saved = [];
try { saved = JSON.parse(localStorage.getItem(STORE) || "[]"); } catch (e) { saved = []; }

checksEl.innerHTML = KIDAN.readiness.map((c, i) => `
  <label class="check" for="rc-${i}">
    <input type="checkbox" id="rc-${i}" ${saved[i] ? "checked" : ""}>
    <span>${c.text}</span>
    <span class="serial">${c.code}</span>
  </label>`).join("");

function update() {
  const values = [...checksEl.querySelectorAll("input")].map(b => b.checked);
  const done = values.filter(Boolean).length;
  const pct = Math.round((done / values.length) * 100);
  try { localStorage.setItem(STORE, JSON.stringify(values)); } catch (e) { /* storage unavailable */ }

  document.getElementById("pct").textContent = pct + "%";
  document.getElementById("meter").style.width = pct + "%";

  const [status, advice] =
    pct === 100 ? ["Always Ready", "Keep it that way. Re-test every six months and run a family drill at night."] :
    pct >= 70 ? ["Nearly ready", "Close the remaining gaps. Each one is a skill you can drill this week."] :
    pct >= 40 ? ["Partly ready", "You have the gear but need practice. Start with the modules below."] :
                ["Not yet ready", "Start with the Go-Bag and Module A, then come back and re-test."];
  document.getElementById("status").textContent = status;
  document.getElementById("advice").textContent = advice;

  const gapModules = [...new Set(KIDAN.readiness.filter((c, i) => !values[i]).map(c => c.module))]
    .map(code => KIDAN.modules.find(m => m.code === code));
  document.getElementById("gaps").innerHTML = gapModules.length
    ? `<span class="serial">Recommended training</span><ul class="ticklist">${gapModules
        .map(m => `<li><a href="training.html#module-${m.code}">Module ${m.code} · ${m.name}</a></li>`).join("")}</ul>`
    : "";
}

checksEl.addEventListener("change", update);
document.getElementById("reset").addEventListener("click", () => {
  checksEl.querySelectorAll("input").forEach(b => { b.checked = false; });
  update();
});
update();
