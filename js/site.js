/* =========================================================
   KIDAN 72 — Shared site script
   Builds the header and footer on every page, runs the
   order list, and holds helpers used by the page scripts.
   ========================================================= */

const NAV = [
  { id: "home", label: "Home", href: "index.html" },
  { id: "about", label: "About", href: "about.html" },
  { id: "system", label: "The System", href: "system.html" },
  { id: "gobag", label: "Go-Bag", href: "gobag.html" },
  { id: "training", label: "Training", href: "training.html" },
  { id: "readiness", label: "Readiness Check", href: "readiness.html" }
];

/* ---------- Helpers ---------- */
function icon(name) {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">${KIDAN.icons[name] || ""}</svg>`;
}

function findBag(serial) { return KIDAN.bags.find(b => b.serial === serial); }

function findItem(serial) {
  const bag = findBag(serial);
  if (bag) return { ...bag, cat: "BAG" };
  return KIDAN.items.find(i => i.serial === serial);
}

/* Which pouch an item is packed in */
function pouchOf(serial) {
  return KIDAN.bags.find(b => b.contents && b.contents.includes(serial));
}

function bagContents(bag) {
  if (bag.contents) return bag.contents;
  return (bag.includes || []).flatMap(sn => findBag(sn).contents || []);
}

function isSold(serial) { return !KIDAN.notSoldSeparately.includes(serial); }

function price(serial) {
  const p = KIDAN.prices[serial];
  return typeof p === "number" && p > 0 ? p : null;
}

function peso(amount) {
  return amount == null ? "PHP xx" : "PHP " + amount.toLocaleString("en-PH", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

/* ---------- Video popup (YouTube embedded on the site) ----------
   Paste a normal YouTube link in js/data.js ("video": "...").
   The video plays in a popup on this website. Viewers press play
   themselves, so each play is a real view on your YouTube channel. */
function youtubeId(url) {
  if (!url) return "";
  const m = String(url).trim().match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/))([A-Za-z0-9_-]{11})/);
  if (m) return m[1];
  return /^[A-Za-z0-9_-]{11}$/.test(String(url).trim()) ? String(url).trim() : "";
}

function videoButton(entry, title, subtitle, popupTitle = title) {
  const id = youtubeId(entry.video);
  return `<button type="button" class="video-link" data-video="${id}" data-title="${popupTitle.replace(/"/g, "&quot;")}">
    <span class="play">${icon("play")}</span>
    <span><strong>${title}</strong><small>${subtitle}</small></span>
    <span class="ext">${id ? "Play video" : "Coming soon"}</span>
  </button>`;
}

const VideoModal = {
  el: null, lastFocus: null,
  build() {
    this.el = document.createElement("div");
    this.el.className = "vmodal";
    this.el.hidden = true;
    this.el.innerHTML = `
      <div class="vmodal-backdrop" data-close></div>
      <div class="vmodal-box" role="dialog" aria-modal="true" aria-labelledby="vmodal-title">
        <div class="vmodal-head">
          <span class="serial" id="vmodal-title"></span>
          <button type="button" class="vmodal-close" data-close aria-label="Close video">✕</button>
        </div>
        <div class="vmodal-frame" id="vmodal-frame"></div>
      </div>`;
    document.body.appendChild(this.el);
    this.el.addEventListener("click", e => { if (e.target.closest("[data-close]")) this.close(); });
    document.addEventListener("keydown", e => { if (e.key === "Escape" && !this.el.hidden) this.close(); });
  },
  open(id, title) {
    if (!this.el) this.build();
    this.lastFocus = document.activeElement;
    this.el.querySelector("#vmodal-title").textContent = title;
    this.el.querySelector("#vmodal-frame").innerHTML = id
      ? `<iframe src="https://www.youtube.com/embed/${id}?rel=0&modestbranding=1&playsinline=1"
           title="${title.replace(/"/g, "&quot;")}" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
           referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>`
      : `<div class="vmodal-soon">${icon("play")}<strong>Video coming soon</strong><p>This training video is being produced. Check back shortly.</p></div>`;
    this.el.hidden = false;
    document.body.classList.add("no-scroll");
    this.el.querySelector(".vmodal-close").focus();
  },
  close() {
    this.el.hidden = true;
    this.el.querySelector("#vmodal-frame").innerHTML = ""; // stops playback
    document.body.classList.remove("no-scroll");
    if (this.lastFocus) this.lastFocus.focus();
  }
};

document.addEventListener("click", e => {
  const b = e.target.closest("[data-video]");
  if (!b) return;
  e.preventDefault();
  VideoModal.open(b.dataset.video, b.dataset.title);
});

function detailUrl(serial) { return "item.html?sn=" + serial; }

/* Photo slot. Shows assets/img/products/<serial>.jpg when present,
   otherwise a specimen plate with the item's icon. */
function photoSlot(serial, iconName, extraClass = "") {
  return `<div class="photo ${extraClass}">
    <span class="sn-badge">${serial}</span>
    <img src="assets/img/products/${serial}.jpg" alt="" loading="lazy" onerror="this.closest('.photo').classList.add('no-photo')">
    <span class="ticks"></span>
    <span class="placeholder">${icon(iconName)}<small>Photo coming soon</small></span>
  </div>`;
}

function addButton(serial, label = "Add to order") {
  if (!isSold(serial)) return `<span class="tag">Included with the Black Pouch</span>`;
  return `<button type="button" class="btn btn-add" data-add="${serial}">${label}</button>`;
}

/* Catalog card for an individual item */
function itemCard(item) {
  return `<article class="item" data-cat="${item.cat}">
    <a class="item-photo" href="${detailUrl(item.serial)}" tabindex="-1" aria-hidden="true">${photoSlot(item.serial, item.icon)}</a>
    <div class="item-body">
      <span class="tag">${KIDAN.categories[item.cat]}</span>
      <h3><a href="${detailUrl(item.serial)}">${item.name}</a></h3>
      <p>${item.short}</p>
      <div class="item-foot">
        <span class="serial">${item.serial}</span>
        <span class="price">${isSold(item.serial) ? peso(price(item.serial)) : "Bag only"}</span>
      </div>
      <div class="item-actions">
        <a class="link-more" href="${detailUrl(item.serial)}">Details →</a>
        ${isSold(item.serial) ? `<button type="button" class="btn-mini" data-add="${item.serial}">+ Add</button>` : ""}
      </div>
    </div>
  </article>`;
}

/* Card for one of the three bags */
function bagCard(bag) {
  const count = bagContents(bag).length;
  return `<article class="bag-card${bag.includes ? " featured" : ""}">
    <a href="${detailUrl(bag.serial)}" tabindex="-1" aria-hidden="true">${photoSlot(bag.serial, bag.icon)}</a>
    <div class="bag-body">
      <span class="tag">${bag.label}</span>
      <h3><a href="${detailUrl(bag.serial)}">${bag.name}</a></h3>
      <p>${bag.short}</p>
      <div class="bag-meta"><span class="serial">${bag.serial}</span><span class="serial">${count} items</span></div>
      <div class="bag-price">${peso(price(bag.serial))}</div>
      <div class="bag-actions">
        <button type="button" class="btn btn-primary" data-add="${bag.serial}">Add to order</button>
        <a class="btn btn-ghost" href="${detailUrl(bag.serial)}">View contents</a>
      </div>
    </div>
  </article>`;
}

/* ---------- Order list (kept in this browser only) ---------- */
const Order = {
  KEY: "k72-order",
  read() {
    try { return JSON.parse(localStorage.getItem(this.KEY) || "{}"); } catch (e) { return this._mem || {}; }
  },
  write(o) {
    this._mem = o;
    try { localStorage.setItem(this.KEY, JSON.stringify(o)); } catch (e) { /* storage unavailable */ }
    document.dispatchEvent(new CustomEvent("order:change"));
  },
  add(sn, qty = 1) { const o = this.read(); o[sn] = (o[sn] || 0) + qty; this.write(o); },
  set(sn, qty) { const o = this.read(); if (qty > 0) o[sn] = qty; else delete o[sn]; this.write(o); },
  clear() { this.write({}); },
  lines() {
    const o = this.read();
    return Object.keys(o).filter(sn => findItem(sn)).map(sn => ({ sn, qty: o[sn], item: findItem(sn), unit: price(sn) }));
  },
  count() { return this.lines().reduce((n, l) => n + l.qty, 0); },
  total() {
    const lines = this.lines();
    if (!lines.length || lines.some(l => l.unit == null)) return null;
    return lines.reduce((t, l) => t + l.unit * l.qty, 0);
  }
};

function updateOrderBadge() {
  document.querySelectorAll("[data-order-count]").forEach(el => {
    const n = Order.count();
    el.textContent = n;
    el.hidden = n === 0;
  });
}

function toast(message) {
  let el = document.getElementById("toast");
  if (!el) {
    el = document.createElement("div");
    el.id = "toast";
    el.className = "toast";
    el.setAttribute("role", "status");
    document.body.appendChild(el);
  }
  el.innerHTML = message;
  el.classList.add("show");
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove("show"), 3200);
}

document.addEventListener("click", e => {
  const btn = e.target.closest("[data-add]");
  if (!btn) return;
  e.preventDefault();
  const sn = btn.dataset.add;
  Order.add(sn, 1);
  toast(`<strong>${findItem(sn).name}</strong> added to your order. <a href="order.html">View order →</a>`);
});
document.addEventListener("order:change", updateOrderBadge);

/* ---------- Header & footer ---------- */
function buildHeader() {
  const page = document.body.dataset.page;
  const el = document.getElementById("site-header");
  if (!el) return;
  el.className = "site-header";
  el.innerHTML = `
    <div class="topline"><div class="wrap">
      <span><b>■</b> Equip people before they need it</span>
      <span class="hide-sm">Always Ready.</span>
    </div></div>
    <div class="wrap nav">
      <a class="brand" href="index.html" aria-label="KIDAN 72 home"><img src="assets/img/kidan72-logo-header.png" alt="KIDAN 72 — Always Ready."></a>
      <ul class="menu" id="menu">
        ${NAV.map(n => `<li><a href="${n.href}"${n.id === page ? ' aria-current="page"' : ""}>${n.label}</a></li>`).join("")}
      </ul>
      <a class="order-btn${page === "order" ? " active" : ""}" href="order.html"><span class="hide-xs">My&nbsp;</span>Order <span class="badge" data-order-count hidden>0</span></a>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="menu">Menu</button>
    </div>`;
  const toggle = el.querySelector(".menu-toggle");
  const menu = el.querySelector("#menu");
  toggle.addEventListener("click", () => {
    const open = menu.classList.toggle("open");
    toggle.setAttribute("aria-expanded", open);
    toggle.textContent = open ? "Close" : "Menu";
  });
}

function buildFooter() {
  const el = document.getElementById("site-footer");
  if (!el) return;
  el.className = "site-footer";
  el.innerHTML = `
    <div class="wrap">
      <div class="foot-grid">
        <div>
          <img src="assets/img/kidan72-logo-light.png" alt="KIDAN 72 — Always Ready.">
          <p>A practical preparedness system built for Philippine typhoons, flooding, and the first 72 hours after a major disruption.</p>
        </div>
        <div><h4>Shop</h4><ul>
          <li><a href="item.html?sn=K72-BAG-000">Complete Go-Bag</a></li>
          <li><a href="item.html?sn=K72-BAG-001">Black Pouch</a></li>
          <li><a href="item.html?sn=K72-BAG-002">Coyote Brown Pouch</a></li>
          <li><a href="gobag.html#items">Individual items</a></li>
          <li><a href="order.html">My Order</a></li>
        </ul></div>
        <div><h4>Prepare</h4><ul>
          <li><a href="system.html">The System</a></li>
          <li><a href="training.html">Training Modules</a></li>
          <li><a href="readiness.html">Readiness Check</a></li>
          <li><a href="system.html#family">Family System</a></li>
        </ul></div>
        <div><h4>KIDAN 72</h4><ul>
          <li><a href="about.html">About</a></li>
          <li><a href="about.html#contact">Contact</a></li>
          <li><a href="system.html#typhoon">Typhoon System</a></li>
          <li><a href="system.html#flood">Flood System</a></li>
        </ul></div>
      </div>
      <div class="foot-base">
        <span>© ${new Date().getFullYear()} KIDAN 72 · Always Ready.</span>
        <span>Powered by <a class="credit" href="https://globalcor3solutions.com" target="_blank" rel="noopener">Global Cor3 Solutions</a> · <a class="credit" href="https://globalcor3solutions.com" target="_blank" rel="noopener">globalcor3solutions.com</a></span>
      </div>
    </div>`;
}

buildHeader();
buildFooter();
updateOrderBadge();
