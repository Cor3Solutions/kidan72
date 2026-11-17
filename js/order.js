/* KIDAN 72 — Order page
   Builds the order message and sends it through the channels
   set in KIDAN.contact (js/data.js). */

const linesEl = document.getElementById("lines");
const summaryEl = document.getElementById("summary");
const sendEl = document.getElementById("send");
const msgEl = document.getElementById("form-msg");
const form = document.getElementById("details");

/* ---------- Order list ---------- */
function renderLines() {
  const lines = Order.lines();
  linesEl.parentElement.classList.remove("is-done");
  if (!lines.length) {
    linesEl.innerHTML = `<div class="empty">
      <h3>Your order list is empty</h3>
      <p>Start with the Preparedness Bag, or add individual items for refills and upgrades.</p>
      <div class="btn-row"><a class="btn btn-primary" href="gobag.html">Browse the Go-Bag <span class="arrow">→</span></a></div>
    </div>`;
    summaryEl.hidden = true;
    return;
  }
  summaryEl.hidden = false;
  linesEl.innerHTML = `<div class="order-lines">${lines.map(l => `
    <div class="order-line">
      <a href="${detailUrl(l.sn)}" tabindex="-1" aria-hidden="true">${photoSlot(l.sn, l.item.icon, "", l.pick || defaultPick(l.sn))}</a>
      <div class="stack" style="gap:4px">
        <span class="serial">${esc(l.sn)}</span>
        <h3><a href="${detailUrl(l.sn)}">${esc(l.name)}</a></h3>
        ${choiceOf(l.sn) ? `<label class="line-choice">${esc(choiceOf(l.sn).label)}
          <select data-choice-line="${esc(l.key)}"${l.needsPick ? ' class="needs"' : ""}>
            ${l.needsPick ? `<option value="" selected>Choose…</option>` : ""}
            ${choiceOf(l.sn).values.map(v => `<option${v === l.pick ? " selected" : ""}${isPickOut(l.sn, v) && v !== l.pick ? " disabled" : ""}>${esc(v)}${isPickOut(l.sn, v) ? " (sold out)" : ""}</option>`).join("")}
          </select></label>` : ""}
        <span class="serial">${l.unit == null ? "Price to follow" : peso(l.unit) + " each"}</span>
        ${isSoldOut(l.sn) ? `<span class="sold-out-note">Sold out. Please remove it to send your order.</span>`
          : isPickOut(l.sn, l.pick) ? `<span class="sold-out-note">${esc(l.pick)} is sold out. Please choose another colour or remove it.</span>`
          : stockLeft(l.sn) && l.qty > stockLeft(l.sn) ? `<span class="sold-out-note">Only ${stockLeft(l.sn)} left. Please lower the quantity.</span>` : ""}
      </div>
      <div class="right">
        <div class="qty">
          <button type="button" data-qty="${esc(l.key)}" data-step="-1" aria-label="Decrease ${esc(l.name)}">−</button>
          <input type="number" min="1" max="99" value="${l.qty}" data-qty-input="${esc(l.key)}" id="q-${esc(l.key)}" aria-label="Quantity of ${esc(l.name)}">
          <button type="button" data-qty="${esc(l.key)}" data-step="1" aria-label="Increase ${esc(l.name)}">+</button>
        </div>
        <span class="line-total">${l.unit == null ? "Price to follow" : peso(l.unit * l.qty)}</span>
        <button type="button" class="remove" data-remove="${esc(l.key)}">Remove</button>
      </div>
    </div>`).join("")}</div>
    <div class="btn-row" style="margin-top:20px;justify-content:space-between">
      <a class="btn btn-ghost" href="gobag.html">← Continue browsing</a>
      <button type="button" class="remove" id="clear">Clear order list</button>
    </div>`;

  document.getElementById("sum-rows").innerHTML = lines.map(l =>
    `<div style="display:flex;justify-content:space-between;gap:12px;font-size:15px"><span>${l.qty} × ${esc(l.name)}</span><span style="white-space:nowrap">${l.unit == null ? "Price to follow" : peso(l.unit * l.qty)}</span></div>`).join("");
  const sumTotal = document.getElementById("sum-total");
  sumTotal.textContent = Order.total() == null ? "To be confirmed" : peso(Order.total());
  sumTotal.classList.toggle("tbc", Order.total() == null);
}

linesEl.addEventListener("click", e => {
  const step = e.target.closest("[data-qty]");
  if (step) {
    const sn = step.dataset.qty;
    const cur = Order.read()[sn] || 1;
    Order.set(sn, Math.min(99, Math.max(1, cur + Number(step.dataset.step))));
    return;
  }
  const rm = e.target.closest("[data-remove]");
  if (rm) { Order.set(rm.dataset.remove, 0); return; }
  if (e.target.id === "clear") Order.clear();
});
linesEl.addEventListener("change", e => {
  const sel = e.target.closest("[data-choice-line]");
  if (sel) { if (sel.value) Order.rechoose(sel.dataset.choiceLine, sel.value); return; }
  const inp = e.target.closest("[data-qty-input]");
  if (inp) Order.set(inp.dataset.qtyInput, Math.min(99, Math.max(1, parseInt(inp.value, 10) || 1)));
});
document.addEventListener("order:change", renderLines);

/* ---------- Message ---------- */
let addressLists = false;   // true once the province/city/barangay dropdowns loaded

function customerDetails() {
  const d = Object.fromEntries(new FormData(form));
  const t = k => (d[k] || "").trim();
  const parts = { province: t("province"), city: t("city"), barangay: t("barangay"), street: t("street") };
  return {
    name: t("name"),
    mobile: t("mobile"),
    email: t("email"),
    website: t("website"),
    ...(addressLists ? parts : { province: "", city: "", barangay: "", street: "" }),
    address: addressLists ? PHAddress.format(parts) : t("address"),
    notes: t("notes")
  };
}

/* Remember the customer's details on this device for their next order */
const DETAILS_KEY = "k72-details";
function saveDetails() {
  const d = customerDetails();
  try { localStorage.setItem(DETAILS_KEY, JSON.stringify({ name: d.name, mobile: d.mobile, email: d.email, province: d.province, city: d.city, barangay: d.barangay, street: d.street, address: addressLists ? "" : d.address })); } catch (e) { /* storage unavailable */ }
}
function savedDetails() {
  try { return JSON.parse(localStorage.getItem(DETAILS_KEY) || "{}"); } catch (e) { return {}; }
}

/* The message the customer sends us. Plain text with short lines,
   so it reads cleanly in Messenger, Viber and email. */
function orderMessage(orderNo) {
  const d = customerDetails();
  const lines = Order.lines();
  const items = lines.map((l, i) => [
    `${i + 1}. ${l.name}`,
    `    Qty: ${l.qty}  |  ${l.unit == null ? "Price to follow" : peso(l.unit * l.qty)}`,
    `    Serial: ${l.sn}`
  ].join("\n"));
  const count = lines.reduce((n, l) => n + l.qty, 0);
  const out = [
    "Hi KIDAN 72! I would like to order:",
    orderNo ? `Order no.: ${orderNo}` : null,
    "",
    items.join("\n\n"),
    "",
    "--------------------",
    `Total items: ${count}`,
    `Estimated total: ${Order.total() == null ? "to be confirmed" : peso(Order.total())}`,
    "--------------------",
    "",
    "MY DETAILS",
    `Name: ${d.name}`,
    `Mobile: ${d.mobile}`,
    d.address ? `Delivery address: ${d.address}` : null,
    d.notes ? `Notes: ${d.notes}` : null,
    "",
    "Thank you!"
  ];
  return out.filter(x => x !== null).join("\n");
}

function updatePreview() {
  const pre = document.getElementById("msg-preview");
  if (pre) pre.textContent = Order.lines().length ? orderMessage("") : "";
}

function validate() {
  const out = Order.lines().filter(l => isSoldOut(l.sn) || isPickOut(l.sn, l.pick));
  if (out.length) {
    msgEl.textContent = `Sorry, ${out.map(l => l.name).join(", ")} ${out.length === 1 ? "is" : "are"} sold out. Please remove ${out.length === 1 ? "it" : "them"} from your list first.`;
    return false;
  }
  const unpicked = Order.lines().filter(l => l.needsPick);
  if (unpicked.length) {
    const l = unpicked[0];
    msgEl.textContent = `Please choose the ${choiceOf(l.sn).label.toLowerCase()} for the ${l.item.name}.`;
    const sel = linesEl.querySelector(`[data-choice-line="${CSS.escape(l.key)}"]`);
    if (sel) sel.focus();
    return false;
  }
  const name = form.elements.name.value.trim();
  const mobile = form.elements.mobile.value.trim();
  if (!name || !mobile) {
    msgEl.textContent = "Please add your name and mobile number so we can confirm your order.";
    (name ? form.elements.mobile : form.elements.name).focus();
    return false;
  }
  const email = form.elements.email.value.trim();
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
    msgEl.textContent = "Please check your email address, or leave it empty.";
    form.elements.email.focus();
    return false;
  }
  if (addressLists && (!form.elements.province.value || !form.elements.city.value)) {
    msgEl.textContent = "Please choose your province and city / municipality so we can work out delivery.";
    (form.elements.province.value ? form.elements.city : form.elements.province).focus();
    return false;
  }
  msgEl.textContent = "";
  return true;
}

async function copyText(text) {
  try { await navigator.clipboard.writeText(text); return true; }
  catch (e) {
    const ta = document.createElement("textarea");
    ta.value = text; ta.style.position = "fixed"; ta.style.opacity = "0";
    document.body.appendChild(ta); ta.select();
    let ok = false;
    try { ok = document.execCommand("copy"); } catch (err) { ok = false; }
    ta.remove();
    return ok;
  }
}

/* ---------- Saving the order (database mode only) ----------
   Static mode: does nothing, the order is only sent as a message.
   Database mode: saves the order through api/orders.php and puts the
   order number in the message. The same order is saved only once,
   even if the customer presses several send buttons. */
let lastSaved = { key: "", orderNo: "", result: null };

async function saveToDatabase(channel) {
  if (Store.mode !== "api") return "";
  const customer = customerDetails();
  const items = Order.lines().map(l => ({ serial: l.sn, qty: l.qty, choice: l.pick }));
  const key = JSON.stringify([customer, items]);
  if (key === lastSaved.key) return lastSaved.orderNo;
  try {
    const result = await Store.saveOrder({ customer, items, channel });
    lastSaved = { key, orderNo: result.orderNo || "", result };
    return lastSaved.orderNo;
  } catch (err) {
    console.error(err);
    msgEl.textContent = /sold out|mobile|email/i.test(err.message)
      ? err.message
      : "We could not record your order online, but you can still send it to us below.";
    return "";
  }
}

/* ---------- Submit order (database mode) ----------
   One button. The order goes straight to the admin page, then the
   customer sees a "thank you, order received" screen. */
function renderSubmit() {
  const c = KIDAN.contact;
  sendEl.innerHTML = `<button type="button" class="btn btn-primary" id="submit-order">Submit order <span class="arrow">→</span></button>
    <p class="fineprint">No payment yet. We contact you to confirm stock, the delivery fee and payment (GCash or bank transfer).</p>
    ${c.messenger ? `<p class="fineprint">Questions first? <a href="https://m.me/${esc(c.messenger.replace(/^@/, ""))}" target="_blank" rel="noopener">Message us on Messenger</a>.</p>` : ""}`;
}

let submitting = false;
sendEl.addEventListener("click", async e => {
  const b = e.target.closest("#submit-order");
  if (!b || submitting || !validate()) return;
  submitting = true;
  b.disabled = true;
  b.textContent = "Submitting…";
  saveDetails();
  const customer = customerDetails();
  const lines = Order.lines();
  const total = Order.total();
  try {
    const result = await Store.saveOrder({ customer, items: lines.map(l => ({ serial: l.sn, qty: l.qty, choice: l.pick })), channel: "website" });
    if (!result.orderNo) throw new Error("No order number");
    showSubmitted(result, customer, lines, total);
  } catch (err) {
    console.error(err);
    // The shop's own message (e.g. an item sold out) when there is one;
    // otherwise the connection dropped or the server had a problem.
    const fromShop = err.message && !/^Request failed|fetch|network|load failed|No order number/i.test(err.message) && !(err instanceof TypeError);
    msgEl.textContent = fromShop
      ? err.message
      : "Sorry, your order could not be submitted. Please check your connection and try again" +
        (KIDAN.contact.messenger ? ", or message us on Messenger." : ".");
    b.disabled = false;
    b.innerHTML = `Submit order <span class="arrow">→</span>`;
  } finally {
    submitting = false;
  }
});

/* The "order received" screen. Takes the place of the order list. */
function showSubmitted(r, customer, lines, total) {
  const first = customer.name.split(/\s+/)[0] || customer.name;
  const items = lines.map(l => `<li><span>${l.qty} × ${esc(l.name)} <span class="serial">${esc(l.sn)}</span></span><span>${l.unit == null ? "Price to follow" : peso(l.unit * l.qty)}</span></li>`).join("");
  Order.clear();               // the list is now saved with us, so start fresh
  form.elements.notes.value = "";
  summaryEl.hidden = true;
  linesEl.parentElement.classList.add("is-done");
  linesEl.innerHTML = `<div class="order-done" id="order-confirm" role="status" tabindex="-1">
    <span class="done-mark" aria-hidden="true">✓</span>
    <span class="serial">Order submitted</span>
    <h2>Thank you, ${esc(first)}!</h2>
    <p class="lede">We received your order <strong>${esc(r.orderNo)}</strong>.</p>
    <ul class="done-items">${items}</ul>
    <div class="done-total"><span class="serial">Estimated total</span><b${total == null ? ' class="tbc"' : ""}>${total == null ? "To be confirmed" : peso(total)}</b></div>
    ${r.emailed ? `<p>A confirmation was emailed to <strong>${esc(customer.email)}</strong>. Not in your inbox? Please check your <strong>Spam</strong> or Promotions folder and mark it "Not spam".</p>` : ""}
    <h3>What happens next</h3>
    <ol class="done-next">
      <li>We check stock and work out the delivery fee to ${esc([customer.city, customer.province].filter(Boolean).join(", ") || "your address")}.</li>
      <li>We contact you on <strong>${esc(customer.mobile)}</strong>${customer.email ? " or by email" : ""} with the final total and how to pay.</li>
      <li>After paying, upload a screenshot of your payment on your order's tracking page. Then we deliver your kit, registered under your name.</li>
    </ol>
    <div class="btn-row">
      ${r.trackUrl ? `<a class="btn btn-primary" href="${esc(r.trackUrl)}">Track this order <span class="arrow">→</span></a>` : ""}
      <a class="btn btn-ghost" href="gobag.html">Back to the Go-Bag</a>
    </div>
    ${r.trackUrl ? `<p class="fineprint">Save or bookmark the tracking link to check your order any time. You can also track it with your order number and mobile number.</p>` : ""}
  </div>`;
  const box = document.getElementById("order-confirm");
  box.scrollIntoView({ behavior: "smooth", block: "start" });
  box.focus({ preventScroll: true });
}

/* ---------- Send buttons (no database, e.g. the Vercel preview) ---------- */
function renderSend() {
  if (Store.mode === "api") { renderSubmit(); return; }
  const c = KIDAN.contact;
  const btns = [];
  if (c.messenger) btns.push(`<button type="button" class="btn btn-primary" data-send="messenger">Send on Facebook Messenger</button>`);
  if (c.viber) btns.push(`<button type="button" class="btn btn-dark" data-send="viber">Send on Viber</button>`);
  if (c.email) btns.push(`<button type="button" class="btn btn-ghost" data-send="email">Send by email</button>`);
  btns.push(`<button type="button" class="btn ${btns.length ? "btn-ghost" : "btn-primary"}" data-send="copy">Copy order list</button>`);
  sendEl.innerHTML = btns.join("") +
    (c.messenger ? `<p class="fineprint">Your order is copied, then our Facebook page opens in Messenger. Paste it in the chat (press and hold, then Paste) and send.</p>`
                 : `<p class="fineprint">Copy your order list and send it to us through any of our contact channels on the <a href="about.html#contact">About page</a>.</p>`) +
    `<details class="msg-preview"><summary>Preview your message</summary><pre id="msg-preview"></pre></details>`;
  updatePreview();
}

sendEl.addEventListener("click", async e => {
  const b = e.target.closest("[data-send]");
  if (!b || !validate()) return;
  const c = KIDAN.contact;
  const how = b.dataset.send;
  // Only wait for the database when it is switched on, so copying keeps
  // working on iPhones (Safari only allows copying right after a tap).
  saveDetails();
  const orderNo = Store.mode === "api" ? await saveToDatabase(how) : "";
  const text = orderMessage(orderNo);
  if (orderNo) showConfirmation(orderNo, how);

  if (how === "copy") {
    const ok = await copyText(text);
    toast(ok ? "Order list copied. Paste it into your message to us." : "Could not copy automatically. Please select and copy your order list.");
    return;
  }
  if (how === "messenger") {
    const ok = await copyText(text);
    toast(ok ? "Order copied. Paste it in the Messenger chat and send." : "Messenger is opening. Please copy your order list from the preview on this page.");
    const url = "https://m.me/" + encodeURIComponent(c.messenger.replace(/^@/, ""));
    // Phones open the Messenger app; computers open Messenger in a new tab so this page stays open
    const phone = matchMedia("(max-width: 900px), (pointer: coarse)").matches;
    if (phone || !window.open(url, "_blank", "noopener")) window.location.href = url;
    return;
  }
  if (how === "viber") {
    const num = c.viber.replace(/[^\d+]/g, "");
    window.location.href = `viber://chat?number=${encodeURIComponent(num)}&draft=${encodeURIComponent(text)}`;
    return;
  }
  if (how === "email") {
    window.location.href = `mailto:${c.email}?subject=${encodeURIComponent("KIDAN 72 Order")}&body=${encodeURIComponent(text)}`;
  }
});

/* ---------- Confirmation (database mode) ----------
   Shown once the order is recorded, so the customer knows it arrived
   and what happens next. */
function showConfirmation(orderNo, how) {
  const next = {
    messenger: "Messenger is opening. Paste the copied message in the chat and press Send.",
    email: "Your email app is opening. Press Send on the email.",
    viber: "Viber is opening. Press Send on the message.",
    copy: "Your order is copied. Paste it into a message to us."
  }[how] || "";
  let box = document.getElementById("order-confirm");
  if (!box) {
    box = document.createElement("div");
    box.id = "order-confirm";
    box.className = "order-confirm";
    box.setAttribute("role", "status");
    sendEl.before(box);
  }
  const r = lastSaved.result || {};
  const email = form.elements.email.value.trim();
  box.innerHTML = `<span class="serial">Order received</span>
    <h3>Order no. ${esc(orderNo)}</h3>
    <p>${esc(next)}</p>
    ${r.emailed ? `<p>A confirmation was emailed to <strong>${esc(email)}</strong>.</p>` : email ? `<p>We will also email <strong>${esc(email)}</strong> as your order is confirmed, paid and delivered.</p>` : ""}
    <p>We will reply to confirm stock, the delivery fee and payment (GCash or bank transfer).</p>
    ${r.trackUrl ? `<p><a class="btn btn-ghost" href="${esc(r.trackUrl)}">Track this order</a> <span class="fineprint">Bookmark it to check your order any time.</span></p>` : ""}
    <button type="button" class="remove" data-new-order>Done? Clear this list and start a new order</button>`;
  box.scrollIntoView({ behavior: "smooth", block: "nearest" });
}

summaryEl.addEventListener("click", e => {
  if (!e.target.closest("[data-new-order]")) return;
  Order.clear();
  form.elements.notes.value = "";
  lastSaved = { key: "", orderNo: "" };
  const box = document.getElementById("order-confirm");
  if (box) box.remove();
});

document.addEventListener("order:change", updatePreview);
form.addEventListener("input", updatePreview);

Store.ready.then(() => {
  renderLines();
  renderSend();
});

/* Address dropdowns, with the customer's last details filled in */
(async function () {
  const saved = savedDetails();
  if (saved.name) form.elements.name.value = saved.name;
  if (saved.mobile) form.elements.mobile.value = saved.mobile;
  if (saved.email) form.elements.email.value = saved.email;
  if (saved.street) form.elements.street.value = saved.street;
  addressLists = await PHAddress.attach({
    province: form.elements.province, city: form.elements.city, barangay: form.elements.barangay,
    value: saved, onChange: updatePreview
  });
  if (!addressLists) {
    // Lists could not load: fall back to one free-text address box
    document.querySelectorAll("#addr > .field:not(#addr-fallback)").forEach(f => { f.hidden = true; });
    document.getElementById("addr-fallback").hidden = false;
    if (saved.address) form.elements.address.value = saved.address;
  }
  updatePreview();
})();
