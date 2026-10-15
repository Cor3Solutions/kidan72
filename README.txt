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
  js/config.js, js/store.js  Load the content for every page (do not edit)
  js/site.js              Menu and footer shared by every page
  js/home.js, gobag.js, item.js, order.js, training.js, readiness.js   Scripts for each page
  assets/video/           Animated logo + the two Go-Bag films
  assets/img/             Logo files and video poster images
  assets/img/products/    PUT PRODUCT PHOTOS HERE

ADDING PRODUCT PHOTOS
Name each photo with the item's serial number and save it in assets/img/products/
  KIDAN 72 Preparedness Bag ............. K72-BAG-000.jpg
  KIDAN Black ........................... K72-BAG-001.jpg
  KIDAN Brown ........................... K72-BAG-002.jpg
  Water Purifying Kit ................... K72-WTR-001.jpg
  Collapsible Silicone Cup .............. K72-WTR-002.jpg
  Collapsible Metal Cup ................. K72-WTR-003.jpg
  Shelf-Stable Food (MRE) ............... K72-FOD-001.jpg
  Honey ................................. K72-FOD-002.jpg
  Salt .................................. K72-FOD-003.jpg
  Tactical Spoon (Black) ................ K72-FOD-005.jpg
  Foldable Spoon ........................ K72-FOD-006.jpg
  First Aid Kit ......................... K72-MED-001.jpg
  Headlamp .............................. K72-LGT-001.jpg
  Rechargeable Flashlight (USB) ......... K72-LGT-002.jpg
  Glow Stick ............................ K72-LGT-003.jpg
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
  Wipes ................................. K72-HYG-004.jpg
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

WHICH ITEMS ARE IN WHICH POUCH
In js/data.js, under BAGS, each pouch has a "contents" list of serial numbers.
This is a draft based on the product photos. Move serial numbers between the
KIDAN Black and KIDAN Brown lists if needed.

STILL TO FILL IN
Prices and order contacts in js/data.js.
Contact details on about.html (email, mobile, Facebook, location).
