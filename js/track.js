/* KIDAN 72 — Track my order (track.html)
   Opens straight from the link in the confirmation (track.html?o=K72-0001&t=...),
   or by typing the order number and mobile number. Needs the database
   (dataSource "api"); otherwise it explains how to ask about an order. */

const trackForm = document.getElementById("track-form");
const trackMsg = document.getElementById("track-msg");
const trackOut = document.getElementById("track-result");
const STEPS = [["new", "Received"], ["confirmed", "Confirmed"], ["paid", "Paid"], ["delivered", "Delivered"]];

function stepper(status, history) {
  if (status === "cancelled") return `<div class="track-cancelled">This order was cancelled. If this is a mistake, please message us.</div>`;
  const at = {};
  history.forEach(h => { at[h.status] = h.at; });
  const reached = STEPS.findIndex(s => s[0] === status);
  return `<ol class="track-steps">${STEPS.map(([key, label], i) => `
    <li class="${i < reached ? "done" : i === reached ? "current" : ""}">
      <b>${esc(label)}</b><span>${at[key] ? esc(at[key]) : i <= reached ? "" : "Waiting"}</span>
    </li>`).join("")}</ol>`;
}

function nextStep(d) {
  return {
    new: "We are checking stock and the delivery fee. We will message you to confirm the total and how to pay.",
    confirmed: d.payment ? "Your order is confirmed. Please pay using the details below, then upload a screenshot of your payment here."
                         : "Your order is confirmed. We will message you with the payment details. After paying, upload a screenshot of your payment here.",
    paid: "Payment received, thank you. We are preparing your kit for delivery.",
    delivered: "Delivered. Next: learn to use your kit on the Training page.",
    cancelled: ""
  }[d.status] || "";
}

function render(d) {
  const money = v => v == null ? "To be confirmed" : peso(v);
  trackOut.innerHTML = `
    <div class="track-card">
      <div class="track-head">
        <div><span class="serial">Order no.</span><h2>${esc(d.orderNo)}</h2><span class="serial">Placed ${esc(d.placed)}</span></div>
        <span class="track-badge s-${esc(d.status)}">${esc(d.label)}</span>
      </div>
      ${stepper(d.status, d.history)}
      ${nextStep(d) ? `<p class="track-next">${esc(nextStep(d))}</p>` : ""}
      ${d.payment ? `<div class="track-pay"><span class="serial">How to pay</span><p>${esc(d.payment).replace(/\n/g, "<br>")}</p></div>` : ""}
      <div id="proof-box">${proofBox(d)}</div>
      <div class="track-grid">
        <div>
          <span class="serial">Items</span>
          <table class="track-items">${d.items.map(i => `<tr><td>${i.qty} ×</td><td><a href="${detailUrl(i.serial)}">${esc(i.name)}</a></td><td class="num">${i.lineTotal == null ? "" : esc(peso(i.lineTotal))}</td></tr>`).join("")}
            <tr class="sub"><td></td><td>Items</td><td class="num">${esc(money(d.subtotal))}</td></tr>
            ${d.deliveryFee > 0 ? `<tr class="sub"><td></td><td>Delivery fee</td><td class="num">${esc(peso(d.deliveryFee))}</td></tr>` : ""}
            ${d.discount > 0 ? `<tr class="sub"><td></td><td>Discount</td><td class="num">−${esc(peso(d.discount))}</td></tr>` : ""}
            <tr class="grand"><td></td><td>Total</td><td class="num">${esc(money(d.total))}</td></tr>
          </table>
          ${d.status === "new" && d.deliveryFee === 0 ? `<p class="fineprint">The delivery fee is added when we confirm your order.</p>` : ""}
        </div>
        <div>
          <span class="serial">Deliver to</span>
          <p><strong>${esc(d.name)}</strong><br>${esc(d.address || "—")}</p>
          ${d.paymentRef ? `<span class="serial">Payment reference</span><p>${esc(d.paymentRef)}</p>` : ""}
          <p class="fineprint">We send updates by email at each step. Not in your inbox? Please check your Spam or Promotions folder.</p>
          <p class="fineprint">Questions? <a href="about.html#contact">Contact us</a> and mention your order number.</p>
        </div>
      </div>
    </div>`;
  trackOut.hidden = false;
}

/* ---------- Proof of payment ----------
   Shown while the order waits for payment (Received or Confirmed). */
let access = null;     // { order, token } or { order, mobile }, kept to send the proof
let current = null;    // the order as last shown

function proofList(proofs) {
  if (!proofs || !proofs.length) return "";
  return `<ul class="proof-list">${proofs.map(p => `<li><span class="proof-ok" aria-hidden="true">✓</span>
    <span>Proof of payment received ${esc(p.at)}${p.ref ? ` · ref ${esc(p.ref)}` : ""}${p.amount != null ? ` · ${esc(peso(p.amount))}` : ""}</span></li>`).join("")}</ul>`;
}

function proofBox(d) {
  const sent = d.proofs && d.proofs.length;
  if (!d.canUpload) {
    return sent ? `<div class="track-proof"><span class="serial">Proof of payment</span>${proofList(d.proofs)}</div>` : "";
  }
  const total = d.total != null ? peso(d.total) : "";
  return `<form class="track-proof" id="proof-form" novalidate>
    <span class="serial">${sent ? "Proof of payment" : "Already paid?"}</span>
    ${sent ? proofList(d.proofs) + `<p class="fineprint">Thank you. We will check it and mark your order as paid. Sent the wrong picture? You can add another below.</p>`
           : `<h3>Upload your proof of payment</h3><p class="fineprint">A screenshot of your GCash, Maya or bank transfer${d.status === "new" ? " (after we confirm your order and the total)" : ""}. Only our team can see it.</p>`}
    <div class="field"><label for="p-file">Photo or screenshot *</label>
      <input type="file" id="p-file" name="file" accept="image/*,application/pdf" required></div>
    <div class="proof-row">
      <div class="field"><label for="p-ref">Reference no.</label><input id="p-ref" name="ref" maxlength="80" autocomplete="off" placeholder="e.g. 1234 567 890123"></div>
      <div class="field"><label for="p-amount">Amount paid</label><input id="p-amount" name="amount" inputmode="decimal" autocomplete="off" placeholder="${esc(total || "PHP")}"></div>
    </div>
    <p class="fineprint" id="proof-msg" role="alert"></p>
    <button type="submit" class="btn btn-primary" id="proof-send">${sent ? "Send another proof" : "Send proof of payment"}</button>
  </form>`;
}

trackOut.addEventListener("submit", async e => {
  const f = e.target.closest("#proof-form");
  if (!f) return;
  e.preventDefault();
  const msg = f.querySelector("#proof-msg"), btn = f.querySelector("#proof-send");
  const file = f.elements.file.files[0];
  if (!file) { msg.textContent = "Please choose the photo or screenshot of your payment."; return; }
  if (file.size > 10 * 1024 * 1024) { msg.textContent = "That file is larger than 10 MB. Please send a screenshot instead."; return; }
  const fd = new FormData(f);
  fd.append("order", access.order);
  if (access.token) fd.append("token", access.token); else fd.append("mobile", access.mobile);
  btn.disabled = true; btn.textContent = "Sending…"; msg.textContent = "";
  try {
    const res = await fetch((KIDAN_CONFIG.apiBase || "api/") + "payment.php", { method: "POST", body: fd, headers: { "Accept": "application/json" } });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Your proof could not be sent. Please try again.");
    current.proofs = data.proofs; current.canUpload = data.canUpload;
    document.getElementById("proof-box").innerHTML = proofBox(current);
    const box = document.getElementById("proof-box");
    box.insertAdjacentHTML("afterbegin", `<p class="proof-thanks" role="status">Thank you! We received your proof of payment and will confirm it soon.</p>`);
    box.scrollIntoView({ behavior: "smooth", block: "nearest" });
  } catch (err) {
    msg.textContent = err instanceof TypeError ? "Could not send. Please check your connection and try again." : err.message;
    btn.disabled = false; btn.textContent = "Send proof of payment";
  }
});

async function lookup(params) {
  trackMsg.textContent = "";
  try {
    const res = await fetch((KIDAN_CONFIG.apiBase || "api/") + "track.php", {
      method: "POST", headers: { "Content-Type": "application/json", "Accept": "application/json" }, body: JSON.stringify(params)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Order status is not available right now.");
    access = params; current = data;
    render(data);
    trackForm.hidden = true;
  } catch (err) {
    trackMsg.textContent = err.message;
    trackForm.hidden = false;
    trackOut.hidden = true;
  }
}

if (Store.mode !== "api") {
  trackForm.innerHTML = `<p class="lede">To ask about your order, message us on our <a href="about.html#contact">contact page</a> with your name and order details.</p>`;
} else {
  const q = new URLSearchParams(location.search);
  if (q.get("o")) trackForm.elements.order.value = q.get("o");
  if (q.get("o") && q.get("t")) lookup({ order: q.get("o"), token: q.get("t") });
  trackForm.addEventListener("submit", e => {
    e.preventDefault();
    const order = trackForm.elements.order.value.trim(), mobile = trackForm.elements.mobile.value.trim();
    if (!order || !mobile) { trackMsg.textContent = "Please enter your order number and mobile number."; return; }
    lookup({ order, mobile });
  });
}
