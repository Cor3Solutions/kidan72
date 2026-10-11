/* KIDAN 72 — Training page */
Store.ready.then(() => {

  const list = document.getElementById("modules");

  list.innerHTML = KIDAN.modules.map(m => {
    const items = m.items.map(findItem).filter(Boolean);
    return `
    <details class="module" id="module-${esc(m.code)}">
      <summary>
        <span class="code">${esc(m.code)}</span>
        <span><h3>${esc(m.name)}</h3><p>${esc(m.short)}</p></span>
        <span class="toggle" aria-hidden="true">+</span>
      </summary>
      <div class="module-body">
        <div class="stack">
          <span class="eyebrow">Lessons</span>
          <ol class="steps">${m.lessons.map(l => `<li><span>${esc(l)}</span></li>`).join("")}</ol>
        </div>
        <div class="stack">
          ${videoButton(m, "Module " + m.code + " · " + m.name, "Training video")}
          <div class="panel">
            <span class="serial">Go-Bag items covered</span>
            <ul class="ticklist">
              ${items.map(i => `<li><span><a href="${detailUrl(i.serial)}">${esc(i.name)}</a> &nbsp;<span class="serial">${esc(i.serial)}</span></span></li>`).join("")}
            </ul>
          </div>
        </div>
      </div>
    </details>`;
  }).join("");

  // Open the module named in the link (training.html#module-D), or the first one
  function openFromHash() {
    let target = null;
    try { target = location.hash ? document.querySelector(location.hash) : null; } catch (e) { target = null; }
    const el = target && target.classList.contains("module") ? target : list.querySelector(".module");
    if (!el) return;
    el.open = true;
    if (target) target.scrollIntoView({ block: "start" });
  }
  window.addEventListener("hashchange", openFromHash);
  openFromHash();
});
