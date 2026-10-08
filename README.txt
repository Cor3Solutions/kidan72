KIDAN 72 WEBSITE
================

HOW TO OPEN IT
Double-click index.html. It opens in your browser. No installation needed.

PAGES
  index.html      Home
  about.html      About (contact details are at the bottom of this page)
  system.html     The System (Database, Family, Typhoon, Flood, 72-Hour, Community, Standard)
  gobag.html      The Go-Bag: the 3 bags + all 22 individual items
  item.html       Detail page for one bag or item (opened from the Go-Bag page)
  order.html      My Order: the customer's order list and the "Send" buttons
  training.html   Training Modules A to J
  readiness.html  Readiness Check

FOLDERS
  css/style.css           All colours, fonts and layout
  js/data.js              PRICES, ORDER CONTACTS, BAGS, ITEMS, MODULES, VIDEO LINKS (edit this one)
  js/site.js              Menu and footer shared by every page
  js/home.js, gobag.js, item.js, order.js, training.js, readiness.js   Scripts for each page
  assets/video/           Animated logo + the two Go-Bag films
  assets/img/             Logo files and video poster images
  assets/img/products/    PUT PRODUCT PHOTOS HERE

ADDING PRODUCT PHOTOS
Name each photo with the item's serial number and save it in assets/img/products/
  Complete Go-Bag ...... K72-BAG-000.jpg
  Black Pouch .......... K72-BAG-001.jpg
  Coyote Brown Pouch ... K72-BAG-002.jpg
  Water Container ...... K72-WTR-001.jpg
  Water Purification ... K72-WTR-002.jpg
  Emergency Food ....... K72-FOD-001.jpg
  First-Aid Kit ........ K72-MED-001.jpg
  Medicine Pouch ....... K72-MED-002.jpg
  Headlamp ............. K72-LGT-001.jpg
  Flashlight ........... K72-LGT-002.jpg
  Power Bank ........... K72-PWR-001.jpg
  Batteries & Cables ... K72-PWR-002.jpg
  Emergency Radio ...... K72-COM-001.jpg
  Phone Readiness Card . K72-COM-002.jpg
  Signal Whistle ....... K72-COM-003.jpg
  Document Pouch ....... K72-DOC-001.jpg
  Cash & Contact Card .. K72-DOC-002.jpg
  Rain Poncho .......... K72-WTH-001.jpg
  Tarp Shelter ......... K72-WTH-002.jpg
  Thermal Blanket ...... K72-WTH-003.jpg
  Hygiene Kit .......... K72-HYG-001.jpg
  Multi-Tool ........... K72-TLS-001.jpg
  Rope & Carabiner ..... K72-TLS-002.jpg
  Fire Starter Set ..... K72-TLS-003.jpg
  Compass & Map ........ K72-NAV-001.jpg
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
Black Pouch and Coyote Brown Pouch lists if needed.

STILL TO FILL IN
Prices and order contacts in js/data.js.
Contact details on about.html (email, mobile, Facebook, location).
