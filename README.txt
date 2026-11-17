KIDAN 72 WEBSITE
================

HOW TO OPEN IT
Double-click index.html. It opens in your browser. No installation needed.

PAGES
  index.html      Home
  about.html      About (contact details are at the bottom of this page)
  system.html     The System (Database, Family, Typhoon, Flood, 72-Hour, Community, Standard)
  gobag.html      The Kit: the 3 bags + all 39 individual items
  item.html       Detail page for one bag or item (opened from the Go-Bag page)
  order.html      My Order: the customer's order list and the "Send" buttons
  training.html   Training Modules A to J
  readiness.html  Readiness Check

FOLDERS
  css/style.css           All colours, fonts and layout
  js/data.js              PRICES, ORDER CONTACTS, BAGS, ITEMS, MODULES, VIDEO LINKS (edit this one)
  js/config.js            The switch between js/data.js and the database (see DATABASE below)
  js/store.js             Loads the content for every page (do not edit)
  js/site.js              Menu and footer shared by every page
  js/home.js, gobag.js, item.js, order.js, training.js, readiness.js   Scripts for each page
  assets/video/           Animated logo + the two Go-Bag films
  assets/img/             Logo files and video poster images
  assets/img/products/    PUT PRODUCT PHOTOS HERE

ADDING PRODUCT PHOTOS
Name each photo with the item's serial number and save it in assets/img/products/
  KIDAN 72 Preparedness Bag ............. K72-BAG-000.jpg
  KIDAN Pouch (black / brown / inside) .. K72-PCH-001.jpg, K72-PCH-001-2.jpg, K72-PCH-001-3.jpg
  Water Purifying Kit ................... K72-WTR-001.jpg
  Collapsible Metal Cup ................. K72-WTR-003.jpg
  Food Pack ............................. K72-FOD-001.jpg
  Honey ................................. K72-FOD-002.jpg
  Salt .................................. K72-FOD-003.jpg
  Tactical Spoon (Black) ................ K72-FOD-005.jpg
  First Aid Kit ......................... K72-MED-001.jpg
  Headlamp .............................. K72-LGT-001.jpg
  Rechargeable Flashlight (USB) ......... K72-LGT-002.jpg
  Rechargeable Power Bank (10,000 mA .... K72-PWR-001.jpg
  Multi-Port Charging Cable ............. K72-PWR-002.jpg
  Solar / USB Power Bank ................ K72-PWR-003.jpg
  Whistle with Compass .................. K72-COM-001.jpg
  Waterproof Phone Pouch ................ K72-COM-002.jpg
  Notebook (A7) ......................... K72-DOC-001.jpg
  Pencil ................................ K72-DOC-002.jpg
  Waterproof Zip Bags (L) ............... K72-DOC-003.jpg
  Emergency Thermal Blanket ............. K72-WTH-001.jpg
  Lightweight Rain Poncho ............... K72-WTH-002.jpg
  Heavy-Duty Garbage Bags (3XL) ......... K72-WTH-003.jpg
  2-Person Tent ......................... K72-WTH-004.jpg
  Sleeping Bag .......................... K72-WTH-005.jpg
  Compressed Towel (70 × 140 cm) ........ K72-HYG-001.jpg
  Portable Car Urine Bags ................ K72-HYG-002.jpg
  Floss ................................. K72-HYG-003.jpg
  Wipes (1 pc) .......................... K72-HYG-004.jpg
  Folding Scissors (Stainless) .......... K72-TLS-002.jpg
  Paracord .............................. K72-TLS-003.jpg
  Zip Ties .............................. K72-TLS-004.jpg
  Fishing Kit ........................... K72-SRV-001.jpg
  Trap Kit .............................. K72-SRV-002.jpg
  Fire Starter Kit ...................... K72-SRV-003.jpg
  Slingshot ............................. K72-SRV-004.jpg
Use landscape photos (4:3 works best). The photo replaces the "Photo coming soon" box automatically.

ADDING YOUR YOUTUBE VIDEOS
Open js/data.js, find the item or training module, and paste the YouTube link between the quotes:
  video: "https://www.youtube.com/watch?v=XXXXXXXXXXX"
Any normal YouTube link works (youtube.com/watch, youtu.be, shorts).
The video plays in a popup on the website, not on YouTube's site.
Plays count as views on your YouTube channel. Visitors press play themselves,
which is what YouTube counts as a real view.
While a link is empty, the popup says "Video coming soon".
Note: YouTube videos may not play when you open the site straight from your
computer (double-clicking index.html). They play once the site is online.

SETTING PRICES
Open js/data.js. At the top is a price list with every serial number.
Replace null with the price in pesos, numbers only:
  "K72-BAG-000": 4500,
Anything left as null shows "PHP xx" on the site.

WHERE ORDERS GO
In js/data.js, under ORDER CONTACTS, fill in:
  messenger: your Facebook page username (the part after facebook.com/)
  viber:     mobile number with +63
  email:     an email address
Each one you fill in becomes a "Send on ..." button on the My Order page.
Customers' order lists are kept only in their own browser; nothing is stored online.

STOCK (INVENTORY) — Admin → More → Stock
- Type how many you have of each item (each pouch colour separately) and press
  "Save counts". Items left empty are not tracked and never show Sold out.
- Stock goes down by itself when an order is set to Confirmed (or Paid /
  Delivered) and comes back if it is Cancelled, set back to New, or deleted.
  A Preparedness Bag takes 1 of every item packed in it + 1 pouch of the chosen colour.
- The website shows "Sold out" at 0 (per colour for the pouch), and
  "Only 3 left" at or below the "Alert at" number. Website orders larger than
  the stock are refused with a clear message; the admin can still record them.
- "Stock received, damaged or counted" for one item; History shows every change;
  "Download Excel" gives the stock list with its value and the history.
- The Stock page shows how many bags you can still pack and which item runs out first.
- Sample orders never change the stock. (Database update no. 9.)

THE KIT (revised October 2026)
- The KIDAN 72 Preparedness Bag holds every item plus 1 KIDAN Pouch.
  Customers choose the pouch colour (Black or Brown) when they order.
- The KIDAN Pouch (K72-PCH-001) is also sold on its own under Individual items,
  in Black or Brown. (It replaced the KIDAN Black and KIDAN Brown pouch kits.)
- Removed: Collapsible Silicone Cup, Foldable Spoon, Glow Stick.
- The MRE is now the Food Pack. Wipes are 1 pc (one pack).
- Colour choices: Admin → Products → (product) → "Customer chooses", e.g.
  "Black, Brown". The colour is saved in the order, e.g. "KIDAN Pouch (Brown)".
  The website's database was updated automatically (update no. 8).

STILL TO FILL IN
Prices and order contacts in js/data.js.
Contact details on about.html (email, mobile, Facebook, location).


DATABASE VERSION (PHP + MySQL)
==============================
The site can also take all its content from a MySQL database, edited
through an admin page instead of js/data.js. This needs a PHP + MySQL
host (for example Hostinger). Vercel cannot run it.

  api/            PHP that sends the content to the pages and saves orders
  admin/          The admin page (prices, text, videos, photos, orders, users)
  database/       The database tables (schema.sql) and starting content (seed.sql)
  tools/          make-seed.js rebuilds database/seed.sql from js/data.js

HOW TO SWITCH IT ON (once, on the new host)
1. Create a MySQL database in the hosting panel.
2. Open  yoursite/admin/setup.php  and follow the 3 steps:
   database login -> tables + content are loaded -> create your admin login.
3. In js/config.js change  dataSource: "static"  to  dataSource: "api"
4. Log in at  yoursite/admin/  and add the client's login under Users.

AFTER THAT
- Change prices, text, videos, photos and contacts in the admin page.
  js/data.js is no longer used for content (keep it as a backup);
  only its icons are still used.
- Orders appear under Orders in the admin page, with an order number
  (K72-0001 ...) that is also put into the customer's Messenger message.
- Photos uploaded in the admin page are saved in the uploads/ folder on the
  server. That folder and api/config.php (the database password) are never
  uploaded to GitHub.

MORE ADMIN FEATURES (added October 2026)
- Dashboard checklist: products without a price, photo or video, and items
  marked sold out. Click a number to see the list.
- Sold out: tick "Sold out" on a product. The website hides "Add to order"
  and the order page will not send it.
- Orders: add the delivery fee, discount, payment method and reference
  (e.g. GCash). The total updates. "Reply to the customer" gives a ready
  message to paste in Messenger. "Print packing slip" prints a checklist.
- New order alerts are emailed to the address under Contact & site
  (works on the live host; not from XAMPP on your own computer).
- Tools on the dashboard: download all orders as a spreadsheet (Excel),
  download a backup of the database, and the activity log (who changed what).
- Forgot password? on the login page emails a reset link. Each person
  should save their email under Users > Your details for this to work.

WEBSITE
- The Kit page has a search box: type a name, a word ("chlorine") or a
  serial number ("K72-WTR-001" or just "wtr001").
- On a PHP host, every item link shared on Facebook or Messenger shows that
  item's own photo, name and description (item.php + .htaccess do this).
- The homepage video (assets/video/kidan72-hero.mp4) was made smaller
  (24 MB to 9 MB). The original is kept in assets/video/_original/ and is
  not uploaded to GitHub.

DATABASE UPGRADES
When the website files are updated with new features, the database upgrades
itself automatically the first time a page is opened (api/migrations.php).
Products, orders and logins are kept.

CUSTOMERS (CRM) AND ADDRESSES (added October 2026)
- My Order page: the customer picks Province, then City / Municipality, then
  Barangay from lists, and types only the house no. / street. Their details
  are remembered on their phone for the next order.
  The lists are the official PSA list (PSGC 2025) in assets/data/ph/.
  To update them later: node tools/make-ph-address.js <PSGC data folder>.
- Admin > Customers: every website order is added to a customer, matched by
  mobile number (0917..., +63917... count as the same person). Each customer
  has a profile with their orders, total spent, what they usually buy, a
  timeline of notes (calls, messages, visits) and a follow-up date.
  Follow-ups due show on the dashboard. You can also add customers by hand
  (phone inquiries, offices, barangays) and download the list for Excel.

CUSTOMER CONFIRMATIONS AND ORDER TRACKING (added October 2026)
- The order form has an optional Email box. Customers who fill it in get:
  a confirmation email right away (order no., items, what happens next),
  and an email when you mark the order Confirmed (with the total and how to
  pay), Paid, Delivered or Cancelled. Replies go to the shop's email.
- Every customer gets a private "Track this order" link (track.html), and can
  also look up an order with the order no. + their mobile number. It shows
  Received > Confirmed > Paid > Delivered with dates, items and totals.
- Admin > Contact & site > "How customers pay": your GCash / bank details.
  They are shown to the customer once you confirm their order.
- Admin > Orders > "Record an order": for orders by phone, Messenger chat,
  walk-in or events, so they are in the customer's history too.
- Emails need the live host (Hostinger); they are not sent from XAMPP.

AFTER CHANGING A .js OR .css FILE (for whoever edits the code)
Hostinger tells browsers to keep scripts and styles for 7 days. Every page
links them with a version tag, e.g. js/site.js?v=20261016. After changing
any .js or .css file, change that number on every page (use today's date),
plus admin.css?v=... in admin/_inc.php, so visitors get the new version at once.

ORDERING ON THE LIVE SITE (Hostinger)
With the database on, the My Order page has one "Submit order" button. The
order goes straight to the admin (Orders) and the customer sees an "order
received" screen with the order number and a tracking link.
On Vercel (no database) the page keeps the Messenger / copy buttons.

PHONE ALERTS (text message) — added October 2026
A text to the shop's phone for every new website order and every proof of
payment. Uses Semaphore (semaphore.co), a Philippine SMS service, about
PHP 0.50 per text. To switch on:
1. Create an account at semaphore.co and buy credits.
2. Apply for a sender name (e.g. KIDAN72) in Semaphore; approval can take a few days.
3. In the admin page: Contact & site → Phone alerts. Paste the API key
   (Semaphore: Account → API), check the phone number, Save, then
   "Send a test text".
Texts have no links (Philippine networks block texts with links).

PROOF OF PAYMENT — added October 2026
On the tracking page, while an order is Received or Confirmed, the customer
can upload a screenshot or PDF of their GCash / Maya / bank payment, with the
reference no. and amount. The shop gets an email and a text. The proof shows
on the order in the admin page (Orders → the order → Proof of payment), and
the dashboard counts proofs to check. Files are saved in uploads/payments/,
which the public cannot open; only logged-in admins can see them.

ADMIN LAYOUT (redesigned October 2026)
Phone: a bar at the bottom with Home, Orders, Customers, Products and More.
Computer: the same pages in a menu on the left.
- Home: a "To do" list (new orders, proofs of payment, paid orders to
  deliver, follow-ups, setup reminders), this month's figures, latest orders.
- Orders: filters with counts, search, tap an order to open it. On the order:
  next step, customer (Call / Text), items, proof of payment, Update order
  (status buttons, delivery fee, payment), the ready-made message, history.
- Customers: filters, search, "Add customer". On a customer: one "Add a note"
  form (with an optional follow-up date), timeline, Edit details.
- Products: filters, quick search, one "Save prices" button.
- More: Training modules, Readiness check, Settings, Logins, Activity log,
  Downloads (orders / customers Excel, full backup), log out.
- Settings: Contact details, Payments, Order alerts (email and text),
  Homepage film, Kit page photos; each part has its own Save button.
Leaving a page with unsaved changes asks first.

EMAIL SENDING (added October 2026) — IMPORTANT
Hostinger's built-in website mail is limited and often lands in spam or is
not delivered at all. Send through the shop's own email account instead:
Admin → More → Settings → Email sending → Gmail.
1. In the shop's Gmail: turn on 2-Step Verification
   (myaccount.google.com/security), then create an App Password
   (myaccount.google.com/apppasswords), e.g. named "KIDAN 72 website".
2. Paste the 16-letter App Password in the admin page, Save, then press
   "Send a test email".
Every email (order received, confirmed, paid, delivered, cancelled, alerts,
password resets) is listed on the order page under "History and emails",
with the reason if it failed. Home shows a warning when emails fail.

REPORTS (added October 2026)
Admin → More → Reports (on a computer: Reports in the left menu).
Pick a month, a year or all time. "Download Excel" gives a formatted file
with 4 sheets: Summary, Orders, Products, Customers.
"Customer list (Excel)" under More → Downloads lists every customer.

FORGOT PASSWORD
"Forgot password?" on the login page emails a reset link (needs Email
sending set up). Another admin can also make a reset link for you:
More → Logins → "Forgot password? Make a link", and send it by Messenger.

OTHER IMPROVEMENTS (October 20, 2026)
- Admin login: "Keep me logged in on this device for 30 days" (on your own
  phone or computer only) and a Show button for the password. Logging out or
  changing the password ends kept logins.
- Admin on a phone: open the admin in Chrome/Safari and choose "Add to Home
  screen" to get a KIDAN 72 icon that opens it like an app.
- Easier to read: deeper orange for buttons and small orange text, darker
  grey for small print (passes the WCAG AA readability standard).
- Lighter photos on phones (assets/img/photo-*-800.jpg).
- Security headers, README.txt hidden from the public, a "page not found"
  page (404.html), robots.txt and sitemap.xml for Google (made by api/seo.php).
- After moving to a real domain: in Hostinger turn on "Force HTTPS"
  (Websites → Security → SSL), and submit https://YOURDOMAIN/sitemap.xml in
  Google Search Console.
