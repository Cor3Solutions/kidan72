/* =========================================================
   KIDAN 72 — Site settings
   This is the ONLY file to change when the database goes live.

   dataSource: "static"  → content comes from js/data.js
                            (works anywhere, including Vercel)
               "api"     → content comes from the MySQL database
                            through the PHP files in /api
                            (needs a PHP + MySQL host)
   apiBase:    where the PHP files live, relative to the site.

   Chosen automatically:
   - Vercel (*.vercel.app) and a page opened straight from the
     computer (double-clicking index.html) use js/data.js.
   - Every PHP host (Hostinger, XAMPP on your computer) uses the
     database, so the admin page's changes show on the site.
   ========================================================= */

const KIDAN_CONFIG = {
  dataSource: (location.protocol === "file:" || /\.vercel\.app$/i.test(location.hostname)) ? "static" : "api",
  apiBase: "api/"
};
