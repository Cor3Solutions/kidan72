/* KIDAN 72 — Training page */

const list = document.getElementById("modules");

list.innerHTML = KIDAN.modules.map(m => {
  const items = m.items.map(findItem).filter(Boolean);
  return `
  <details class="module" id="module-${m.code}">
    <summary>
      <span class="code">${m.code}</span>
      <span><h3>${m.name}</h3><p>${m.short}</p></span>
      <span class="toggle" aria-hidden="true">+</span>
    </summary>
    <div class="module-body">
      <div class="stack">
        <span class="eyebrow">Lessons</span>
        <ol class="steps">${m.lessons.map(l => `<li><span>${l}</span></li>`).join("")}</ol>
      </div>
      <div class="stack">
        ${videoButton(m, "Module " + m.code + " · " + m.name, "Training video")}
        <div class="panel">
          <span class="serial">Go-Bag items covered</span>
          <ul class="ticklist">
            ${items.map(i => `<li><span><a href="${"item.html?sn=" + i.serial}">${i.name}</a> &nbsp;<span class="serial">${i.serial}</span></span></li>`).join("")}
          </ul>
        </div>
      </div>
    </div>
  </details>`;
}).join("");

// Open the module named in the link (training.html#module-D), or the first one
function openFromHash() {
  const target = location.hash && document.querySelector(location.hash);
  const el = target && target.classList.contains("module") ? target : list.querySelector(".module");
  el.open = true;
  if (target) target.scrollIntoView({ block: "start" });
}
window.addEventListener("hashchange", openFromHash);
openFromHash();
