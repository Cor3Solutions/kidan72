/* =========================================================
   KIDAN 72 — Site settings
   This is the ONLY file to change when the database goes live.

   dataSource: "static"  → content comes from js/data.js
                            (works anywhere, including GitHub Pages)
               "api"     → content comes from the MySQL database
                            through the PHP files in /api
                            (needs a PHP + MySQL host)
   apiBase:    where the PHP files live, relative to the site.
   ========================================================= */

const KIDAN_CONFIG = {
  dataSource: "static",
  apiBase: "api/"
};
