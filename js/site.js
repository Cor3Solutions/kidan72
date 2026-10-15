/* =========================================================
   KIDAN 72 — Shared site script
   Builds the header and footer on every page, runs the
   order list, and holds helpers used by the page scripts.
   ========================================================= */

const NAV = [
  { id: "home", label: "Home", href: "index.html" },
  { id: "system", label: "About", href: "system.html" },
  { id: "gobag", label: "Kit", href: "gobag.html" },
  { id: "training", label: "Training", href: "training.html" },
  { id: "readiness", label: "Community", href: "readiness.html" }
];

/* ---------- Helpers ---------- */
/* Makes text safe to put into the page. Use it for every piece of
   content that comes from js/data.js or, later, the database. */
function esc(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
}

function icon(name) {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="square" stroke-linejoin="miter" aria-hidden="true">${KIDAN.icons[name] || ""}</svg>`;
}

function findBag(serial) { return KIDAN.bags.find(b => b.serial === serial); }

function findItem(serial) {
  const bag = findBag(serial);
  if (bag) return { ...bag, cat: "BAG" };
  return KIDAN.items.find(i => i.serial === serial);
}

/* Which pouches an item is packed in (some items are in both) */
function pouchesOf(serial) {
  return KIDAN.bags.filter(b => b.contents && b.contents.includes(serial));
}
function pouchOf(serial) { return pouchesOf(serial)[0]; }

/* Number of items packed in a bag (a kit counts as one) */
function packingCount(bag) {
  if (bag.includes) return bag.includes.reduce((n, sn) => n + packingCount(findBag(sn)), 0);
  return (bag.contents || []).length;
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
  return `<button type="button" class="video-link" data-video="${id}" data-title="${esc(popupTitle)}">
    <span class="play">${icon("play")}</span>
    <span><strong>${esc(title)}</strong><small>${esc(subtitle)}</small></span>
    <span class="ext">${id ? "Play video" : "Coming soon"}</span>
  </button>`;
}

/* ---------- Clean YouTube player (no title, channel name, logo or controls) ----------
   Used for the homepage film and every video pop-up.
   - The YouTube player is made taller than its frame, so the strips where
     YouTube shows its title and logo sit outside the visible area.
   - A clear layer on top catches taps, so YouTube's own buttons never show:
     tap to play, tap again to pause.
   - Plays still count as views on the YouTube channel.
   box must contain  .yt-film-stage  and  button.yt-film-play  (and may contain
   an img.yt-film-cover shown before playing). */
function loadYouTubeApi() {
  if (window.YT && window.YT.Player) return Promise.resolve();
  if (loadYouTubeApi._p) return loadYouTubeApi._p;
  loadYouTubeApi._p = new Promise(resolve => {
    const prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => { if (prev) prev(); resolve(); };
    const tag = document.createElement("script");
    tag.src = "https://www.youtube.com/iframe_api";
    document.head.appendChild(tag);
  });
  return loadYouTubeApi._p;
}

function cleanPlayer(box, id, opts = {}) {
  const label = opts.label || "the video";
  const btn = box.querySelector(".yt-film-play");
  const stage = box.querySelector(".yt-film-stage");
  let player = null, started = false, timer = null, gone = false;

  function setState(state) {            // "idle" | "loading" | "playing" | "paused"
    box.dataset.state = state;
    btn.setAttribute("aria-label", (state === "playing" ? "Pause " : "Play ") + label);
  }
  setState("idle");

  function start() {
    setState("loading");
    loadYouTubeApi().then(() => {
      if (gone) return;
      const holder = document.createElement("div");
      stage.appendChild(holder);
      player = new YT.Player(holder, {
        videoId: id,
        host: "https://www.youtube-nocookie.com",
        playerVars: { autoplay: 1, controls: 0, disablekb: 1, fs: 0, rel: 0, modestbranding: 1, iv_load_policy: 3, playsinline: 1 },
        events: {
          onReady: e => e.target.playVideo(),
          onStateChange: e => {
            if (e.data === YT.PlayerState.PLAYING) { started = true; box.classList.remove("needs-tap"); setState("playing"); }
            else if (e.data === YT.PlayerState.PAUSED) setState("paused");
            else if (e.data === YT.PlayerState.ENDED) { player.seekTo(0, true); player.pauseVideo(); setState("paused"); }
          }
        }
      });
      // Some browsers block playing until the video itself is tapped:
      // if it has not started after a few seconds, let the tap through (still cropped).
      timer = setTimeout(() => { if (!started) box.classList.add("needs-tap"); }, 3500);
    });
  }

  btn.addEventListener("click", () => {
    if (!player) { start(); return; }
    if (box.dataset.state === "playing") player.pauseVideo();
    else player.playVideo();
  });
  if (opts.autostart) start();

  return {
    destroy() {
      gone = true; clearTimeout(timer);
      try { if (player && player.destroy) player.destroy(); } catch (e) { /* already gone */ }
      player = null;
    }
  };
}

function cleanPlayerMarkup(cover) {
  return `<div class="yt-film">
      ${cover ? `<img class="yt-film-cover" src="${cover}" alt="">` : ""}
      <div class="yt-film-stage"></div>
      <button type="button" class="yt-film-play"><span class="film-link-play" aria-hidden="true"></span></button>
    </div>`;
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
    const frame = this.el.querySelector("#vmodal-frame");
    if (id) {
      frame.innerHTML = cleanPlayerMarkup("");
      this.player = cleanPlayer(frame.querySelector(".yt-film"), id, { label: title, autostart: true });
    } else {
      frame.innerHTML = `<div class="vmodal-soon">${icon("play")}<strong>Video coming soon</strong><p>This training video is being produced. Check back shortly.</p></div>`;
    }
    this.el.hidden = false;
    document.body.classList.add("no-scroll");
    this.el.querySelector(".vmodal-close").focus();
  },
  close() {
    this.el.hidden = true;
    if (this.player) { this.player.destroy(); this.player = null; }
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

function detailUrl(serial) { return "item.html?sn=" + encodeURIComponent(serial); }

/* Photo slot. Shows assets/img/products/<serial>.jpg when present,
   otherwise a specimen plate with the item's icon. */
function photoSlot(serial, iconName, extraClass = "") {
  // photo: false in js/data.js hides an item's photo (e.g. while a new one is being taken)
  const it = findItem(serial);
  const hidden = it && it.photo === false;
  return `<div class="photo ${extraClass}${hidden ? " no-photo" : ""}">
    <span class="sn-badge">${esc(serial)}</span>
    ${hidden ? "" : `<img src="assets/img/products/${encodeURIComponent(serial)}.jpg" alt="" loading="lazy" onerror="this.closest('.photo').classList.add('no-photo')">`}
    <span class="ticks"></span>
    <span class="placeholder">${icon(iconName)}<small>Photo coming soon</small></span>
  </div>`;
}

function addButton(serial, label = "Add to order") {
  if (!isSold(serial)) return `<span class="tag">Included with the bag</span>`;
  return `<button type="button" class="btn btn-add" data-add="${esc(serial)}">${esc(label)}</button>`;
}

/* Catalog card for an individual item */
function itemCard(item) {
  return `<article class="item" data-cat="${esc(item.cat)}">
    <a class="item-photo" href="${detailUrl(item.serial)}" tabindex="-1" aria-hidden="true">${photoSlot(item.serial, item.icon)}</a>
    <div class="item-body">
      <span class="tag">${esc(KIDAN.categories[item.cat])}</span>
      <h3><a href="${detailUrl(item.serial)}">${esc(item.name)}</a></h3>
      ${item.short ? `<p>${esc(item.short)}</p>` : ""}
      <div class="item-foot">
        <span class="serial">${esc(item.serial)}</span>
        <span class="price">${isSold(item.serial) ? peso(price(item.serial)) : "Bag only"}</span>
      </div>
      <div class="item-actions">
        <a class="link-more" href="${detailUrl(item.serial)}">Details →</a>
        ${isSold(item.serial) ? `<button type="button" class="btn-mini" data-add="${esc(item.serial)}">+ Add</button>` : ""}
      </div>
    </div>
  </article>`;
}

/* Card for one of the three bags */
function bagCard(bag) {
  const count = packingCount(bag);
  return `<article class="bag-card${bag.includes ? " featured" : ""}">
    <a href="${detailUrl(bag.serial)}" tabindex="-1" aria-hidden="true">${photoSlot(bag.serial, bag.icon)}</a>
    <div class="bag-body">
      <span class="tag">${esc(bag.label)}</span>
      <h3><a href="${detailUrl(bag.serial)}">${esc(bag.name)}</a></h3>
      <p>${esc(bag.short)}</p>
      <div class="bag-meta"><span class="serial">${esc(bag.serial)}</span><span class="serial">${count} items</span></div>
      <div class="bag-price">${peso(price(bag.serial))}</div>
      <div class="bag-actions">
        <button type="button" class="btn btn-primary" data-add="${esc(bag.serial)}">Add to order</button>
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
  const it = findItem(sn);
  if (!it) return;
  toast(`<strong>${esc(it.name)}</strong> added to your order. <a href="order.html">View order →</a>`);
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
      <span><b>■</b> Equipping you, before you need it.</span>
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
          <p>A practical readiness system built for the first 72 hours after a major disruption.</p>
        </div>
        <div><h4>Shop</h4><ul>
          <li><a href="item.html?sn=K72-BAG-000">Preparedness Bag</a></li>
          <li><a href="item.html?sn=K72-BAG-001">KIDAN Black</a></li>
          <li><a href="item.html?sn=K72-BAG-002">KIDAN Brown</a></li>
          <li><a href="gobag.html#items">Individual items</a></li>
          <li><a href="order.html">My Order</a></li>
        </ul></div>
        <div><h4>Prepare</h4><ul>
          <li><a href="system.html">About</a></li>
          <li><a href="training.html">Training Modules</a></li>
          <li><a href="readiness.html">Community</a></li>
          <li><a href="system.html#family">Family System</a></li>
        </ul></div>
        <div><h4>KIDAN 72</h4><ul>
          <li><a href="system.html">About KIDAN 72</a></li>
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
Store.ready.then(updateOrderBadge);

/* ---------- Phone helpers ---------- */
// Height of the sticky header, so sticky bars can sit right under it
function setHeaderHeight() {
  const h = document.getElementById("site-header");
  if (h) document.documentElement.style.setProperty("--header-h", h.offsetHeight + "px");
}
setHeaderHeight();
addEventListener("resize", setHeaderHeight);
addEventListener("load", setHeaderHeight);

// Back-to-top button, shown once the visitor has scrolled down
(function backToTop() {
  const b = document.createElement("button");
  b.type = "button";
  b.className = "to-top";
  b.hidden = true;
  b.setAttribute("aria-label", "Back to top");
  b.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 14l6-6 6 6"/></svg>';
  b.addEventListener("click", () => scrollTo({ top: 0, behavior: "smooth" }));
  document.body.appendChild(b);
  // Shows only while scrolling back up (so it never sits over a button while reading),
  // then hides again after a few seconds.
  let lastY = scrollY, timer = null, waiting = false;
  addEventListener("scroll", () => {
    if (waiting) return;
    waiting = true;
    requestAnimationFrame(() => {
      const y = scrollY;
      if (y < innerHeight * 1.5) b.hidden = true;
      else if (y < lastY - 4) {
        b.hidden = false;
        clearTimeout(timer);
        timer = setTimeout(() => { b.hidden = true; }, 2500);
      } else if (y > lastY + 4) b.hidden = true;
      lastY = y;
      waiting = false;
    });
  }, { passive: true });
})();

// Dots under a sideways-swipe row (used for the bags on phones)
function swipeDots(row) {
  if (!row) return;
  const old = row.nextElementSibling;
  if (old && old.classList.contains("swipe-dots")) old.remove();
  const cards = [...row.children];
  if (cards.length < 2) return;
  const dots = document.createElement("div");
  dots.className = "swipe-dots";
  dots.innerHTML = cards.map((c, i) => `<button type="button" aria-label="Show card ${i + 1} of ${cards.length}"></button>`).join("");
  row.after(dots);
  const update = () => {
    const max = row.scrollWidth - row.clientWidth;
    const idx = max <= 0 ? 0 : Math.round(row.scrollLeft / max * (cards.length - 1));
    [...dots.children].forEach((d, i) => d.classList.toggle("on", i === idx));
  };
  dots.addEventListener("click", e => {
    const i = [...dots.children].indexOf(e.target.closest("button"));
    if (i >= 0) row.scrollTo({ left: cards[i].offsetLeft - cards[0].offsetLeft, behavior: "smooth" });
  });
  row.addEventListener("scroll", () => requestAnimationFrame(update), { passive: true });
  update();
}

// "Jump to" bar: highlights the section on screen and keeps its button visible
(function jumpBar() {
  const bar = document.querySelector(".subnav-bar .subnav");
  if (!bar || !("IntersectionObserver" in window)) return;
  const links = [...bar.querySelectorAll('a[href^="#"]')];
  const sections = links.map(a => document.getElementById(a.getAttribute("href").slice(1))).filter(Boolean);
  const mark = id => links.forEach(a => {
    const on = a.getAttribute("href") === "#" + id;
    if (on && a.getAttribute("aria-current") !== "true") {
      bar.scrollTo({ left: a.offsetLeft - bar.offsetLeft - 16, behavior: "smooth" });
    }
    if (on) a.setAttribute("aria-current", "true"); else a.removeAttribute("aria-current");
  });
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => { if (en.isIntersecting) mark(en.target.id); });
  }, { rootMargin: "-35% 0px -60% 0px" });
  sections.forEach(s => io.observe(s));
})();
