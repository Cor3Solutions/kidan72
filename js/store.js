/* =========================================================
   KIDAN 72 — Data layer
   Every page gets its content through Store, never directly.

   Static mode (now):   content is already loaded from js/data.js.
   API mode (database): content is fetched from api/catalog.php,
                        which returns exactly the same shape as
                        js/data.js, so no page script has to change.

   Pages wait for the content like this:
       Store.ready.then(() => { ...build the page... });
   ========================================================= */

const Store = (function () {
  const cfg = (typeof KIDAN_CONFIG !== "undefined" && KIDAN_CONFIG) || { dataSource: "static", apiBase: "api/" };
  const useApi = cfg.dataSource === "api";

  async function getJSON(path, options) {
    const res = await fetch(cfg.apiBase + path, Object.assign({ headers: { "Accept": "application/json" } }, options));
    const body = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(body.error || ("Request failed: " + res.status));
    return body;
  }

  /* Content from the database replaces the static content.
     Icons stay in js/data.js because they are part of the design. */
  const ready = useApi
    ? getJSON("catalog.php").then(data => {
        ["prices", "contact", "categories", "bags", "notSoldSeparately", "items", "modules", "readiness"]
          .forEach(key => { if (data[key] !== undefined) KIDAN[key] = data[key]; });
        return KIDAN;
      }).catch(err => {
        console.error("KIDAN 72: could not load content from the database.", err);
        showLoadError();
        throw err;
      })
    : Promise.resolve(KIDAN);

  function showLoadError() {
    const main = document.querySelector("main");
    if (!main) return;
    const box = document.createElement("div");
    box.className = "wrap load-error";
    box.setAttribute("role", "alert");
    box.innerHTML = "<p><strong>Sorry, the shop could not load right now.</strong> Please refresh the page in a moment.</p>";
    main.prepend(box);
  }

  /* Saves an order in the database (API mode only).
     Static mode: nothing is saved; the order is only sent as a message.
     Returns { saved: true, orderNo: "K72-..." } or { saved: false }. */
  async function saveOrder(order) {
    if (!useApi) return { saved: false };
    const result = await getJSON("orders.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify(order)
    });
    return Object.assign({ saved: true }, result);
  }

  return { ready, saveOrder, mode: useApi ? "api" : "static" };
})();
