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
  if (!lines.length) {
    linesEl.innerHTML = `<div class="empty">
      <h3>Your order list is empty</h3>
      <p>Start with one of the three bags, or add individual items for refills and upgrades.</p>
      <div class="btn-row"><a class="btn btn-primary" href="gobag.html">Browse the Go-Bag <span class="arrow">→</span></a></div>
    </div>`;
    summaryEl.hidden = true;
    return;
  }
  summaryEl.hidden = false;
  linesEl.innerHTML = `<div class="order-lines">${lines.map(l => `
    <div class="order-line">
      <a href="${detailUrl(l.sn)}" tabindex="-1" aria-hidden="true">${photoSlot(l.sn, l.item.icon)}</a>
      <div class="stack" style="gap:4px">
        <span class="serial">${l.sn}</span>
        <h3><a href="${detailUrl(l.sn)}">${l.item.name}</a></h3>
        <span class="serial">${peso(l.unit)} each</span>
      </div>
      <div class="right">
        <div class="qty">
          <button type="button" data-qty="${l.sn}" data-step="-1" aria-label="Decrease ${l.item.name}">−</button>
          <input type="number" min="1" max="99" value="${l.qty}" data-qty-input="${l.sn}" id="q-${l.sn}" aria-label="Quantity of ${l.item.name}">
          <button type="button" data-qty="${l.sn}" data-step="1" aria-label="Increase ${l.item.name}">+</button>
        </div>
        <span class="line-total">${l.unit == null ? "PHP xx" : peso(l.unit * l.qty)}</span>
        <button type="button" class="remove" data-remove="${l.sn}">Remove</button>
      </div>
    </div>`).join("")}</div>
    <div class="btn-row" style="margin-top:20px;justify-content:space-between">
      <a class="btn btn-ghost" href="gobag.html">← Continue browsing</a>
      <button type="button" class="remove" id="clear">Clear order list</button>
    </div>`;

  document.getElementById("sum-rows").innerHTML = lines.map(l =>
    `<div style="display:flex;justify-content:space-between;gap:12px;font-size:15px"><span>${l.qty} × ${l.item.name}</span><span>${l.unit == null ? "PHP xx" : peso(l.unit * l.qty)}</span></div>`).join("");
  document.getElementById("sum-total").textContent = peso(Order.total());
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
  const inp = e.target.closest("[data-qty-input]");
  if (inp) Order.set(inp.dataset.qtyInput, Math.min(99, Math.max(1, parseInt(inp.value, 10) || 1)));
});
document.addEventListener("order:change", renderLines);

/* ---------- Message ---------- */
function orderMessage() {
  const d = Object.fromEntries(new FormData(form));
  const lines = Order.lines();
  const rows = lines.map(l => `• ${l.qty} × ${l.item.name} (${l.sn}) — ${l.unit == null ? "PHP xx" : peso(l.unit * l.qty)}`);
  return [
    "KIDAN 72 ORDER",
    "",
    ...rows,
    "",
    `Estimated total: ${peso(Order.total())}`,
    "",
    `Name: ${d.name || ""}`,
    `Mobile: ${d.mobile || ""}`,
    d.address ? `Delivery: ${d.address}` : "",
    d.notes ? `Notes: ${d.notes}` : ""
  ].filter((line, i, arr) => !(line === "" && arr[i - 1] === "")).join("\n").trim();
}

function validate() {
  const name = form.elements.name.value.trim();
  const mobile = form.elements.mobile.value.trim();
  if (!name || !mobile) {
    msgEl.textContent = "Please add your name and mobile number so we can confirm your order.";
    (name ? form.elements.mobile : form.elements.name).focus();
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

/* ---------- Send buttons ---------- */
function renderSend() {
  const c = KIDAN.contact;
  const btns = [];
  if (c.messenger) btns.push(`<button type="button" class="btn btn-primary" data-send="messenger">Send on Messenger</button>`);
  if (c.viber) btns.push(`<button type="button" class="btn btn-dark" data-send="viber">Send on Viber</button>`);
  if (c.email) btns.push(`<button type="button" class="btn btn-ghost" data-send="email">Send by email</button>`);
  btns.push(`<button type="button" class="btn ${btns.length ? "btn-ghost" : "btn-primary"}" data-send="copy">Copy order list</button>`);
  sendEl.innerHTML = btns.join("") +
    (c.messenger ? `<p class="fineprint">Messenger opens our page with your order copied. Just paste it in the chat and send.</p>`
                 : `<p class="fineprint">Copy your order list and send it to us through any of our contact channels on the <a href="about.html#contact">About page</a>.</p>`);
}

sendEl.addEventListener("click", async e => {
  const b = e.target.closest("[data-send]");
  if (!b || !validate()) return;
  const text = orderMessage();
  const c = KIDAN.contact;
  const how = b.dataset.send;

  if (how === "copy") {
    const ok = await copyText(text);
    toast(ok ? "Order list copied. Paste it into your message to us." : "Could not copy automatically. Please select and copy your order list.");
    return;
  }
  if (how === "messenger") {
    const ok = await copyText(text);
    toast(ok ? "Order copied. Paste it in the Messenger chat and send." : "Messenger is opening. Please copy your order list from this page.");
    window.location.href = "https://m.me/" + encodeURIComponent(c.messenger.replace(/^@/, ""));
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

renderLines();
renderSend();
