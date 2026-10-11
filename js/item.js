/* KIDAN 72 — Detail page for a bag or an item (item.html?sn=K72-XXX-000) */

const root = document.getElementById("item");
const sn = new URLSearchParams(location.search).get("sn");

function buyBox(serial, opts = {}) {
  if (!isSold(serial)) {
    const p = pouchOf(serial);
    return `<div class="buy-box"><span class="serial">Availability</span>
      <p>Included with the ${p ? `<a href="${detailUrl(p.serial)}">${esc(p.name)}</a>` : "Go-Bag"} and the Complete Go-Bag. Not sold separately.</p></div>`;
  }
  return `<div class="buy-box">
    <span class="serial">${opts.label || "Price"}</span>
    <div class="bag-price">${peso(price(serial))}</div>
    <div class="buy-row">
      <div class="qty">
        <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
        <input id="qty" type="number" min="1" max="99" value="1" aria-label="Quantity">
        <button type="button" data-step="1" aria-label="Increase quantity">+</button>
      </div>
      <button type="button" class="btn btn-primary" id="add-qty">Add to order</button>
    </div>
    <span class="fineprint">No payment online. You send your order list to us on Messenger, and we confirm availability, delivery and payment with you.</span>
  </div>`;
}

function contentsList(serials) {
  return `<div class="contents-list">${serials.map(s => {
    const it = findItem(s);
    if (!it) return "";
    return `<a href="${detailUrl(s)}">
      <span class="ci">${icon(it.icon)}</span>
      <span><strong>${esc(it.name)}</strong><span class="serial">${s}</span></span>
      <span class="serial">${isSold(s) ? "" : "Bag only"}</span>
    </a>`;
  }).join("")}</div>`;
}

function wireBuy(serial) {
  const input = document.getElementById("qty");
  if (!input) return;
  document.querySelectorAll("[data-step]").forEach(b => b.addEventListener("click", () => {
    input.value = Math.min(99, Math.max(1, (parseInt(input.value, 10) || 1) + Number(b.dataset.step)));
  }));
  document.getElementById("add-qty").addEventListener("click", () => {
    const q = Math.min(99, Math.max(1, parseInt(input.value, 10) || 1));
    Order.add(serial, q);
    toast(`<strong>${q} × ${esc(findItem(serial).name)}</strong> added to your order. <a href="order.html">View order →</a>`);
  });
}

function notFound() {
  root.innerHTML = `
    <nav class="breadcrumb"><a href="index.html">Home</a> / <a href="gobag.html">Go-Bag</a></nav>
    <h1>Item not found</h1>
    <p class="lede">We could not find a bag or item with that serial number.</p>
    <div><a class="btn btn-dark" href="gobag.html">Back to the Go-Bag</a></div>`;
}

/* ---------- Bag page ---------- */
function renderBag(bag) {
  document.title = `${esc(bag.name)} (${esc(bag.serial)}) — KIDAN 72`;
  const i = KIDAN.bags.indexOf(bag);
  const prev = KIDAN.bags[(i - 1 + KIDAN.bags.length) % KIDAN.bags.length];
  const next = KIDAN.bags[(i + 1) % KIDAN.bags.length];
  const contents = bag.includes
    ? bag.includes.map(p => { const pouch = findBag(p); return `<div class="pouch-group">
        <div class="section-head split" style="margin:0"><h3><a href="${detailUrl(pouch.serial)}" style="text-decoration:none">${esc(pouch.name)}</a></h3><span class="serial">${esc(pouch.serial)} · ${pouch.contents.length} items</span></div>
        ${contentsList(pouch.contents)}</div>`; }).join("")
    : contentsList(bag.contents);

  root.innerHTML = `
    <nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a> / <a href="gobag.html">Go-Bag</a> / ${esc(bag.serial)}</nav>
    <div class="detail" style="margin-top:12px">
      <div class="frame">${photoSlot(bag.serial, bag.icon, "large")}</div>
      <div class="detail-info">
        <span class="eyebrow">${esc(bag.label)}</span>
        <h1>${esc(bag.name)}</h1>
        <p class="lede">${esc(bag.summary)}</p>
        ${buyBox(bag.serial)}
        <dl class="spec-table">
          <div><dt>Serial no.</dt><dd class="serial" style="color:var(--ink)">${esc(bag.serial)}</dd></div>
          ${bag.specs.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v)}</dd></div>`).join("")}
          <div><dt>Items</dt><dd>${bagContents(bag).length} serialised items</dd></div>
        </dl>
      </div>
    </div>
    <div class="mt-lg" style="padding-top:40px;border-top:1px solid var(--line)">
      <div class="section-head"><span class="eyebrow">What's inside</span><h2>Contents</h2></div>
      ${contents}
    </div>
    <div class="pager mt-lg">
      <a href="${detailUrl(prev.serial)}"><span class="serial">← ${esc(prev.serial)}</span><strong>${esc(prev.name)}</strong></a>
      <a href="${detailUrl(next.serial)}"><span class="serial">${esc(next.serial)} →</span><strong>${esc(next.name)}</strong></a>
    </div>`;
  wireBuy(bag.serial);
}

/* ---------- Item page ---------- */
function renderItem(it) {
  const idx = KIDAN.items.indexOf(it);
  const prev = KIDAN.items[(idx - 1 + KIDAN.items.length) % KIDAN.items.length];
  const next = KIDAN.items[(idx + 1) % KIDAN.items.length];
  const module = KIDAN.modules.find(m => m.items.includes(it.serial));
  const pouch = pouchOf(it.serial);
  document.title = `${esc(it.name)} (${esc(it.serial)}) — KIDAN 72`;

  root.innerHTML = `
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="index.html">Home</a> / <a href="gobag.html">Go-Bag</a> /
      <a href="gobag.html?cat=${encodeURIComponent(it.cat)}">${esc(KIDAN.categories[it.cat])}</a> / ${esc(it.serial)}
    </nav>
    <div class="detail" style="margin-top:12px">
      <div class="frame">${photoSlot(it.serial, it.icon, "large")}</div>
      <div class="detail-info">
        <span class="eyebrow">${esc(KIDAN.categories[it.cat])}</span>
        <h1>${esc(it.name)}</h1>
        <p class="lede">${esc(it.short)}</p>
        ${buyBox(it.serial, { label: "Price per piece" })}
        <dl class="spec-table">
          <div><dt>Serial no.</dt><dd class="serial" style="color:var(--ink)">${esc(it.serial)}</dd></div>
          <div><dt>Category</dt><dd>${esc(KIDAN.categories[it.cat])}</dd></div>
          ${pouch ? `<div><dt>Packed in</dt><dd><a href="${detailUrl(pouch.serial)}">${esc(pouch.name)}</a> · Complete Go-Bag</dd></div>` : ""}
          <div><dt>Specification</dt><dd>${esc(it.spec)}</dd></div>
          <div><dt>Maintenance</dt><dd>${esc(it.maintain)}</dd></div>
          ${module ? `<div><dt>Training</dt><dd><a href="training.html#module-${esc(module.code)}">Module ${esc(module.code)} · ${esc(module.name)}</a></dd></div>` : ""}
        </dl>
        ${videoButton(it, "Watch how to use it", "Video walkthrough for the " + it.name.toLowerCase(), it.serial + " · " + it.name)}
      </div>
    </div>
    <div class="two-col mt-lg" style="padding-top:40px;border-top:1px solid var(--line)">
      <div class="howto">
        <div class="stack"><span class="eyebrow">How to use it</span><h2>Field instructions</h2></div>
        <ol class="steps">${it.steps.map(s => `<li><span>${esc(s)}</span></li>`).join("")}</ol>
      </div>
      <div class="stack">
        <div class="note"><strong>Why it matters.</strong> ${esc(it.why)}</div>
        <div class="note"><strong>Inspection.</strong> ${esc(it.maintain)}</div>
      </div>
    </div>
    <div class="pager mt-lg">
      <a href="${detailUrl(prev.serial)}"><span class="serial">← ${esc(prev.serial)}</span><strong>${esc(prev.name)}</strong></a>
      <a href="${detailUrl(next.serial)}"><span class="serial">${esc(next.serial)} →</span><strong>${esc(next.name)}</strong></a>
    </div>`;
  wireBuy(it.serial);
}

Store.ready.then(() => {
  const bag = findBag(sn);
  const item = KIDAN.items.find(i => i.serial === sn);
  if (bag) renderBag(bag);
  else if (item) renderItem(item);
  else notFound();
});
