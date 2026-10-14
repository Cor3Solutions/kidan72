/* =========================================================
   KIDAN 72 — Content data
   Edit this file to change items, serial numbers, or videos.

   PHOTOS: save each photo in assets/img/products/ named by its
   serial number, e.g.  K72-WTR-001.jpg
   Extra photos of the same item: K72-SRV-001-2.jpg, K72-SRV-001-3.jpg ...
   and add  photos: 2  (or 3 ...) to that item below.
   Bag photos: K72-BAG-000.jpg (KIDAN 72 Preparedness Go-Bag),
               K72-BAG-001.jpg (Black KIDAN),
               K72-BAG-002.jpg (Coyote KIDAN)
   Photos appear on the site automatically once the file exists.

   VIDEOS: paste your YouTube link into "video" for any item or
   module, e.g.  video: "https://www.youtube.com/watch?v=abc123XYZ_0"
   The video plays in a popup on the site and counts as a view on
   your YouTube channel. While it is empty, the popup shows
   "Video coming soon".
   ========================================================= */

const KIDAN = {};

/* ---------------------------------------------------------
   1) PRICES — type the price in pesos, numbers only.
      Example:  "K72-BAG-000": 4500,
      Leave as null to show "PHP xx" on the site.
   --------------------------------------------------------- */
KIDAN.prices = {
  // Bags
  "K72-BAG-000": null,   // KIDAN 72 Preparedness Go-Bag
  "K72-BAG-001": null,   // Black KIDAN
  "K72-BAG-002": null,   // Coyote KIDAN
  // Individual items
  "K72-WTR-001": null,   // Water Purifying Kit
  "K72-WTR-002": null,   // Collapsible Silicone Cup
  "K72-WTR-003": null,   // Collapsible Metal Cup
  "K72-FOD-001": null,   // Shelf-Stable Food (MRE)
  "K72-FOD-002": null,   // Honey
  "K72-FOD-003": null,   // Salt
  "K72-FOD-004": null,   // Salt Pouch
  "K72-FOD-005": null,   // Tactical Spoon (Black)
  "K72-FOD-006": null,   // Folding Spoon
  "K72-MED-001": null,   // First Aid Kit
  "K72-MED-002": null,   // Ointment
  "K72-LGT-001": null,   // Headlamp (Warsun)
  "K72-LGT-002": null,   // Rechargeable Flashlight (USB)
  "K72-LGT-003": null,   // Glow Stick
  "K72-PWR-001": null,   // Rechargeable Power Bank (20,000 mAh)
  "K72-PWR-002": null,   // Multi-Port Charging Cable
  "K72-PWR-003": null,   // Solar / USB Power Bank
  "K72-PWR-004": null,   // Power Bank (10,000 mAh)
  "K72-COM-001": null,   // Whistle with Compass
  "K72-COM-002": null,   // Waterproof Phone Pouch
  "K72-DOC-001": null,   // Notebook (A7)
  "K72-DOC-002": null,   // Pencil
  "K72-DOC-003": null,   // Waterproof Zip Bags (L)
  "K72-WTH-001": null,   // Emergency Thermal Blanket
  "K72-WTH-002": null,   // Lightweight Rain Poncho
  "K72-WTH-003": null,   // Heavy-Duty Garbage Bags (3XL)
  "K72-WTH-004": null,   // 2-Person Tent
  "K72-WTH-005": null,   // Sleeping Bag
  "K72-HYG-001": null,   // Compressed Towel (70 × 140 cm)
  "K72-HYG-002": null,   // Urine Bags
  "K72-HYG-003": null,   // Floss (Robinson's)
  "K72-HYG-004": null,   // Wipes
  "K72-HYG-005": null,   // Toothbrush Set
  "K72-TLS-001": null,   // Compact Multi-Tool
  "K72-TLS-002": null,   // Folding Scissors (Stainless)
  "K72-TLS-003": null,   // Paracord
  "K72-TLS-004": null,   // Zip Ties
  "K72-SRV-001": null,   // Fishing Kit
  "K72-SRV-002": null,   // Trap Kit
  "K72-SRV-003": null,  // Fire Starter Kit
  "K72-SRV-004": null,  // Slingshot
  "K72-HYG-006": null,  // Disposable Razor
  "K72-HYG-007": null   // Nail Clipper
};

/* ---------------------------------------------------------
   2) ORDER CONTACTS — where customers send their order.
      Leave a line empty ("") to hide that option.
      messenger: your Facebook page username, e.g. "kidan72ph"
      viber:     mobile number with country code, e.g. "+639171234567"
      email:     e.g. "orders@kidan72.ph"
   --------------------------------------------------------- */
KIDAN.contact = {
  messenger: "",
  viber: "",
  email: ""
};

/* ---------------------------------------------------------
   BRAND FILM — the main KIDAN 72 film (YouTube link).
   Plays on the homepage and from the "Watch the film" button.
   --------------------------------------------------------- */
KIDAN.brandFilm = "https://youtu.be/_H-cqF8l8lw";

KIDAN.categories = {
  WTR: "Water & Hydration",
  FOD: "Food & Nourishment",
  MED: "Medical",
  LGT: "Light",
  PWR: "Power & Charging",
  COM: "Communication & Signal",
  DOC: "Documents & Notes",
  WTH: "Shelter & Weather",
  HYG: "Hygiene",
  TLS: "Tools & Cordage",
  SRV: "Survival Kits"
};

/* ---------------------------------------------------------
   3) BAGS — the three bag options.
      "contents" lists which items are packed in each pouch.
      DRAFT: pouch contents are based on the product photos;
      move serial numbers between the two lists if needed.
   --------------------------------------------------------- */
KIDAN.bags = [
  {
    serial: "K72-BAG-000", icon: "bag", name: "KIDAN 72 Preparedness Go-Bag", label: "Complete system",
    short: "The full 72-hour system: the main carry bag with the Black KIDAN and the Coyote KIDAN packed inside.",
    summary: "Everything one person needs for the first 72 hours after a typhoon, flood or evacuation. The main carry bag holds the Black KIDAN and the Coyote KIDAN, so you can grab the whole system at once or split it between family members.",
    includes: ["K72-BAG-001", "K72-BAG-002"],
    specs: [
      ["Configuration", "Main carry bag + Black KIDAN + Coyote KIDAN"],
      ["Coverage", "1 person · 72 hours"],
      ["Contents", "Everything in the Black KIDAN and the Coyote KIDAN"],
      ["Registration", "Logged in the KIDAN 72 Database with owner profile, inventory and expiry dates"],
      ["Inspection", "Monthly function check · Quarterly full inspection"]
    ]
  },
  {
    serial: "K72-BAG-001", icon: "aid", name: "Black KIDAN", label: "Sustainment module",
    short: "Contains your medical kit, food, electronic needs and documents.",
    summary: "Contains your medical kit, food, electronic needs and documents: everything that keeps you fed, treated and connected, plus the papers you need after an evacuation.",
    contents: ["K72-MED-001", "K72-WTR-002", "K72-LGT-001", "K72-WTH-001", "K72-WTH-002", "K72-HYG-001", "K72-PWR-002", "K72-COM-002", "K72-DOC-001", "K72-DOC-002", "K72-FOD-001", "K72-FOD-002", "K72-FOD-005", "K72-MED-002", "K72-PWR-001", "K72-HYG-002", "K72-HYG-003", "K72-HYG-004", "K72-HYG-005", "K72-HYG-006", "K72-HYG-007", "K72-PWR-004", "K72-LGT-003"],
    specs: [
      ["Role", "Medical kit, food, electronic needs and documents"],
      ["Colour", "Black"],
      ["Carry", "Inside the Preparedness Go-Bag, or on its own"]
    ]
  },
  {
    serial: "K72-BAG-002", icon: "tool", name: "Coyote KIDAN", label: "Field module",
    short: "Contains your tools and gear.",
    summary: "Contains your tools and gear: everything for darkness, rain, shelter and moving safely.",
    contents: ["K72-LGT-002", "K72-WTH-001", "K72-COM-001", "K72-WTH-003", "K72-DOC-003", "K72-TLS-001", "K72-PWR-003", "K72-SRV-001", "K72-SRV-002", "K72-SRV-003", "K72-TLS-002", "K72-FOD-002", "K72-FOD-003", "K72-FOD-004", "K72-FOD-006", "K72-TLS-003", "K72-TLS-004", "K72-WTH-004", "K72-WTH-005", "K72-WTR-001", "K72-WTR-003", "K72-SRV-004"],
    specs: [
      ["Role", "Tools and gear"],
      ["Colour", "Coyote brown"],
      ["Carry", "Inside the Preparedness Go-Bag, or on its own"]
    ]
  }
];

/* Items included with a bag but not sold on their own */
KIDAN.notSoldSeparately = [];

/* Each item: serial is K72-<CAT>-<NNN>. "parts" lists what is inside a kit. */
KIDAN.items = [
  { serial: "K72-WTR-001", cat: "WTR", icon: "filter", name: "Water Purifying Kit",
    short: "Chlorine tablets in a sealed amber bottle, kept dry with silica gel, to make collected water safer to drink.",
    why: "Tap water and refilling stations can stop for days after a typhoon. Treating rainwater or well water lets you stretch your supply. Never drink floodwater, even after treatment.",
    steps: ["Collect the clearest water you can find and let any dirt settle.", "Pour it through a clean cloth into a clean container.", "Add chlorine tablets following the dose printed on the pack, then close the container.", "Wait the full time stated on the pack before drinking."],
    spec: "Amber bottle · Silica gel · Chlorine tablets", maintain: "Check the tablet expiry date every 6 months. Replace the silica gel if it changes colour or clumps.",
    parts: ["Amber bottle", "Silica gel", "Chlorine tablets"],
    q: "how to use chlorine tablets purify water emergency", video: "" },

  { serial: "K72-WTR-002", cat: "WTR", icon: "cup", name: "Collapsible Silicone Cup",
    short: "A cup that folds flat, for drinking, measuring water and eating.",
    why: "Space in a go-bag is limited. A folding cup gives you a clean drinking vessel without taking room.",
    steps: ["Pull the cup open from the base until it locks into shape.", "Rinse it with clean water before first use.", "After use, wipe it dry and press it flat to pack it."],
    spec: "Food-grade silicone, folds flat", maintain: "Keep it clean and dry before packing to prevent mould.",
    q: "collapsible silicone cup camping", video: "" },

  { serial: "K72-WTR-003", cat: "WTR", icon: "cup", name: "Collapsible Metal Cup",
    short: "A metal cup that collapses for packing and can sit near a flame to warm water.",
    why: "Warm water and drinks help you stay comfortable at night and after getting wet. A metal cup can be used with your fire starter kit.",
    steps: ["Extend the cup to its full height before filling.", "To warm water, set it beside a small fire, not inside it, and hold it by the handle with a cloth.", "Let it cool before collapsing it back down."],
    spec: "Metal, collapsible", maintain: "Dry it fully after use to prevent rust or stains.",
    q: "collapsible metal cup camping", video: "" },

  { serial: "K72-FOD-001", cat: "FOD", icon: "food", name: "Shelf-Stable Food (MRE)",
    short: "A ready-to-eat meal that keeps for a long time and needs no stove.",
    why: "Electricity and LPG may be cut for days. Ready-to-eat food keeps your energy up without cooking.",
    steps: ["Check the expiry date when you pack it.", "Open the pack and follow the eating or heating instructions printed on it.", "Eat in a dry, clean spot and pack the wrapper away as rubbish."],
    spec: "Ready-to-eat, long shelf life", maintain: "Log the expiry date and replace it at least one month before it expires.",
    q: "MRE meal ready to eat how to use", video: "" },

  { serial: "K72-FOD-002", cat: "FOD", icon: "honey", name: "Honey",
    short: "Bottled honey for quick energy. One is packed in each pouch.",
    why: "Honey gives fast energy when you are tired, cold or stressed, and it keeps for a very long time if sealed.",
    steps: ["Take a spoonful or two when you need quick energy.", "Close the bottle tightly after every use.", "Do not give honey to babies under one year old."],
    spec: "Sealed bottle", maintain: "Check the seal regularly. Honey can thicken over time, which is normal.",
    q: "honey emergency food energy", video: "" },

  { serial: "K72-FOD-003", cat: "FOD", icon: "salt", name: "Salt",
    short: "A supply of salt for seasoning, preserving food and replacing salt lost through sweat.",
    why: "Hot, humid conditions and hard work make you sweat. Salt helps with taste and can preserve food you catch.",
    steps: ["Use a small pinch to season food.", "Keep it closed so it stays dry.", "Store it in the salt pouch to keep moisture out."],
    spec: "Food-grade salt", maintain: "Keep it sealed and dry. Replace it if it gets wet.",
    q: "salt survival uses", video: "" },

  { serial: "K72-FOD-004", cat: "FOD", icon: "salt", name: "Salt Pouch",
    short: "A small resealable pouch that keeps your salt dry and easy to carry.",
    why: "Salt clumps and dissolves when it gets damp. A dedicated pouch keeps it usable.",
    steps: ["Fill the pouch with salt and press out the air.", "Seal it fully before packing.", "Open only what you need, then reseal it."],
    spec: "Resealable pouch", maintain: "Check that it still seals properly. Replace it if torn.",
    q: "", video: "" },

  { serial: "K72-FOD-005", cat: "FOD", icon: "spoon", name: "Tactical Spoon (Black)",
    short: "A strong spoon for eating straight from a meal pack or container.",
    why: "You may have no kitchen tools after an evacuation. A sturdy spoon makes eating cleaner and easier.",
    steps: ["Wipe or rinse the spoon before eating.", "Use it to eat directly from your food pack.", "Clean it with a wipe after use and pack it away."],
    spec: "Black, sturdy", maintain: "Keep it clean and stored in the pouch.",
    q: "", video: "" },

  { serial: "K72-FOD-006", cat: "FOD", icon: "spoon", name: "Folding Spoon",
    short: "A spoon that folds small to save space.",
    why: "Each person needs their own utensil. A folding spoon fits easily in a pouch.",
    steps: ["Unfold the spoon until it locks open.", "Use it to eat or stir.", "Clean it, fold it and pack it away."],
    spec: "Folding", maintain: "Check that the hinge still locks. Keep it clean and dry.",
    q: "", video: "" },

  { serial: "K72-MED-001", cat: "MED", icon: "aid", name: "First Aid Kit",
    short: "Basic supplies for cleaning, covering and protecting cuts, scrapes and small injuries.",
    why: "Clinics may be crowded or hard to reach after a disaster. Cuts from debris, especially near floodwater, need quick cleaning and cover.",
    steps: ["Learn where everything is in the kit before you need it.", "Clean the wound with clean water before treating it.", "Press firmly with gauze to control bleeding, then cover the wound.", "Seek medical help for deep wounds, heavy bleeding or signs of infection."],
    spec: "Basic wound-care supplies", maintain: "Check the kit every 3 months. Replace anything used or expired.",
    q: "basic first aid kit how to use wound care", video: "" },

  { serial: "K72-MED-002", cat: "MED", icon: "pill", name: "Ointment",
    short: "A topical ointment for minor skin problems such as small cuts, scrapes or irritation.",
    why: "Small skin injuries can get worse in wet, dirty conditions. A protective ointment helps keep them covered.",
    steps: ["Clean and dry the area first.", "Apply a thin layer as directed on the label.", "Cover with a clean dressing if needed.", "Stop using it and seek help if irritation gets worse."],
    spec: "Topical ointment", maintain: "Check the expiry date and keep the cap tightly closed.",
    q: "", video: "" },

  { serial: "K72-LGT-001", cat: "LGT", icon: "head", name: "Headlamp (Warsun)",
    short: "A head-worn light that keeps both hands free.",
    why: "Power is often out for days. A headlamp lets you work, walk and help others in the dark with both hands free.",
    steps: ["Adjust the strap so it sits firmly on your forehead.", "Switch it on and choose the lowest brightness that works, to save battery.", "Angle it down so you don't blind the people around you.", "Charge it whenever you have power."],
    spec: "Head-worn, adjustable strap", maintain: "Test it every month and keep it charged.",
    q: "how to use headlamp", video: "" },

  { serial: "K72-LGT-002", cat: "LGT", icon: "torch", name: "Rechargeable Flashlight (USB)",
    short: "A handheld flashlight you recharge by USB.",
    why: "A strong handheld light helps you see further, check routes and signal for help.",
    steps: ["Charge it fully by USB before packing.", "Use the lower mode for everyday tasks and the strongest mode only when needed.", "Recharge it from a power bank when it runs low."],
    spec: "Rechargeable, USB charging", maintain: "Test it every month and top up the charge.",
    q: "rechargeable flashlight usb", video: "" },

  { serial: "K72-LGT-003", cat: "LGT", icon: "glow", name: "Glow Stick",
    short: "A chemical light that glows without batteries or flame.",
    why: "It is a safe light for inside tents and shelters, and a marker so others can see you or find your bag.",
    steps: ["Bend the stick until you hear it snap inside.", "Shake it to start the glow.", "Hang it where you need light or use it to mark a spot."],
    spec: "Single-use, no batteries", maintain: "Single use only. Replace it after use or when it passes the date on the pack.",
    q: "", video: "" },

  { serial: "K72-PWR-001", cat: "PWR", icon: "battery", name: "Rechargeable Power Bank (20,000 mAh)",
    short: "A high-capacity battery pack to recharge your phone and USB devices.",
    why: "Your phone is your lifeline for warnings, maps and contacting family. A charged power bank keeps it working through an outage.",
    steps: ["Charge it fully before packing it.", "Use it to top up your phone when the battery gets low, not only when it is empty.", "Recharge the power bank whenever you have electricity."],
    spec: "20,000 mAh, rechargeable", maintain: "Recharge it every month, even if unused. Keep it dry.",
    q: "how to use power bank emergency", video: "" },

  { serial: "K72-PWR-002", cat: "PWR", icon: "plug", name: "Multi-Port Charging Cable",
    short: "One cable with several tips, to charge different phones and devices.",
    why: "Family members often use different phones. One cable that fits several types means nobody is left without power.",
    steps: ["Plug the cable into your power bank.", "Use the tip that fits your phone or device.", "Unplug and coil it neatly when you're done."],
    spec: "Several connector types in one cable", maintain: "Check the cable and tips for damage. Keep it dry.",
    q: "", video: "" },

  { serial: "K72-PWR-003", cat: "PWR", icon: "solar", name: "Solar / USB Power Bank",
    short: "A power bank you can charge from sunlight or by USB.",
    why: "If the power stays out for days, sunlight lets you keep recharging your phone and lights.",
    steps: ["Charge it fully by USB before packing.", "In an outage, place the solar panel facing direct sunlight to recharge it.", "Use it to charge your phone, flashlight or headlamp."],
    spec: "Solar panel and USB charging", maintain: "Recharge it every month and keep the panel clean.",
    q: "solar power bank how to use", video: "" },

  { serial: "K72-PWR-004", cat: "PWR", icon: "battery", name: "Power Bank (10,000 mAh)",
    short: "A slim USAMS fast-charge power bank to keep your phone running.",
    why: "Power can be out for days after a typhoon. A charged power bank keeps your phone ready for alerts, calls and your flashlight.",
    steps: ["Charge it fully before storing it in the bag.", "Connect your phone with the charging cable when its battery runs low.", "Turn it off and unplug it once your phone is charged to save power."],
    spec: "10,000 mAh, fast charge", maintain: "Recharge it every month, even if unused. Keep it dry and away from heat.",
    q: "", video: "" },

  { serial: "K72-COM-001", cat: "COM", icon: "whistle", name: "Whistle with Compass",
    short: "A loud signal whistle with a small built-in compass.",
    why: "A whistle carries further than your voice and uses less energy. The compass helps you keep a direction when landmarks are gone.",
    steps: ["Blow three short blasts to signal for help, then pause and repeat.", "Keep it on a cord where you can reach it quickly.", "Hold the compass flat to read the direction."],
    spec: "Signal whistle with built-in compass", maintain: "Test it every month. Keep it clean and dry.",
    q: "emergency whistle signal three blasts", video: "" },

  { serial: "K72-COM-002", cat: "COM", icon: "phone", name: "Waterproof Phone Pouch",
    short: "A sealed pouch that keeps your phone dry while you can still use the screen.",
    why: "Rain and floodwater can kill a phone in seconds. A waterproof pouch protects your most important tool.",
    steps: ["Put your phone inside and close the seal fully.", "Test it with no phone inside first to check that it seals.", "You can usually use the touchscreen through the pouch."],
    spec: "Waterproof, sealable", maintain: "Check the seal for cracks or tears before each season.",
    q: "", video: "" },

  { serial: "K72-DOC-001", cat: "DOC", icon: "notebook", name: "Notebook (A7)",
    short: "A small notebook for contact numbers, notes and messages.",
    why: "Phones die and networks go down. Writing down key numbers, plans and notes keeps them available.",
    steps: ["Write important contact numbers on the first page.", "Note your family meeting place and evacuation route.", "Use it to record what happened and what you still need."],
    spec: "A7 pocket size", maintain: "Keep it in a zip bag so it stays dry. Update the numbers regularly.",
    q: "", video: "" },

  { serial: "K72-DOC-002", cat: "DOC", icon: "pencil", name: "Pencil",
    short: "A pencil that writes even when paper is damp.",
    why: "Pens can leak, dry out or fail on wet paper. A pencil keeps working.",
    steps: ["Keep it with the notebook.", "Use it to write notes, messages or directions.", "Sharpen it with your multi-tool if needed."],
    spec: "Standard pencil", maintain: "Keep it sharpened and stored with the notebook.",
    q: "", video: "" },

  { serial: "K72-DOC-003", cat: "DOC", icon: "doc", name: "Waterproof Zip Bags (L)",
    short: "Large waterproof zip bags for documents, clothes and electronics.",
    why: "IDs, papers and spare clothes are useless once soaked. Zip bags keep them dry through rain and flooding.",
    steps: ["Put your documents, cash and spare clothes in separate bags.", "Press out the air and close the zip fully.", "Keep the most important documents in the bag on top."],
    spec: "Large, 28 × 38, zip seal", maintain: "Check them for holes and replace any that don't seal.",
    q: "", video: "" },

  { serial: "K72-WTH-001", cat: "WTH", icon: "blanket", name: "Emergency Thermal Blanket",
    short: "A light foil blanket that keeps in body heat. One is packed in each pouch.",
    why: "After getting wet in rain or floodwater, your body loses heat fast, even in warm weather. A thermal blanket helps keep you warm.",
    steps: ["Unfold it fully.", "Wrap it around your body, shiny side in, covering your head if possible.", "Use it under a tarp or tent for extra warmth."],
    spec: "Foil emergency blanket, folds small", maintain: "Keep it sealed until needed. Replace it once it has been used.",
    q: "how to use emergency thermal blanket", video: "" },

  { serial: "K72-WTH-002", cat: "WTH", icon: "rain", name: "Lightweight Rain Poncho",
    short: "A light, packable poncho that keeps you and your bag dry.",
    why: "Staying dry helps you stay warm and healthy during typhoon rains.",
    steps: ["Unfold it and put it on over your clothes.", "Wear it over your bag to keep the bag dry too.", "Shake it dry and fold it after use."],
    spec: "Lightweight, packable", maintain: "Check it for tears. Dry it fully before packing it again.",
    q: "", video: "" },

  { serial: "K72-WTH-003", cat: "WTH", icon: "trash", name: "Heavy-Duty Garbage Bags (3XL)",
    short: "Large, tough bags for rubbish, rain covers and keeping gear dry.",
    why: "Extra-large bags have many uses: a rain cover, a ground sheet, a bag liner, or sealing away rubbish and waste.",
    steps: ["Line your pouch or bag with one to keep the contents dry.", "Cut a head hole to make an emergency rain cover.", "Use one to collect and seal away rubbish."],
    spec: "Heavy-duty, 3XL", maintain: "Keep them folded flat. Replace any that are used or torn.",
    q: "", video: "" },

  { serial: "K72-WTH-004", cat: "WTH", icon: "tent", name: "2-Person Tent",
    short: "A compact tent that sleeps two people.",
    why: "Evacuation centres can be crowded. A tent gives your family dry, private space to rest.",
    steps: ["Choose high, flat ground away from rivers and slopes.", "Lay out the tent, then put in the poles and raise it.", "Stake down all corners and tie the guy lines firmly in wind."],
    spec: "2-person", maintain: "Practise setting it up at home. Dry it fully before packing it away.",
    q: "how to set up 2 person tent", video: "" },

  { serial: "K72-WTH-005", cat: "WTH", icon: "sleep", name: "Sleeping Bag",
    short: "A packable sleeping bag for rest in shelters or outdoors.",
    why: "Rest keeps you able to make good decisions. A sleeping bag keeps you warm and off the cold floor.",
    steps: ["Unpack it and let it fluff up before use.", "Lay it on a dry surface, or on a garbage bag as a ground sheet.", "Roll it tightly and pack it in its sack after use."],
    spec: "Packable", maintain: "Air it out and keep it dry. Store it loosely when not in the bag for long periods.",
    q: "", video: "" },

  { serial: "K72-HYG-001", cat: "HYG", icon: "towel", name: "Compressed Towel (70 × 140 cm)",
    short: "A large disposable towel compressed into a small tablet.",
    why: "Drying off after rain or a wash helps prevent chills and skin problems.",
    steps: ["Add a little water to the compressed tablet.", "Unroll it to its full size.", "Use it to dry off or clean up, then dispose of it."],
    spec: "Disposable, 70 × 140 cm when open", maintain: "Keep it sealed and dry until needed.",
    q: "", video: "" },

  { serial: "K72-HYG-002", cat: "HYG", icon: "hygiene", name: "Urine Bags",
    short: "Sealed bags for going to the toilet when no toilet is available.",
    why: "Toilets may be flooded, broken or unavailable during evacuation. Urine bags keep things clean and private.",
    steps: ["Open the bag and use it following the instructions on the pack.", "Seal it tightly after use.", "Dispose of it properly in a rubbish bag."],
    spec: "Single-use, sealable", maintain: "Keep enough for 72 hours. Replace any you use.",
    q: "", video: "" },

  { serial: "K72-HYG-003", cat: "HYG", icon: "hygiene", name: "Floss (Robinson's)",
    short: "Dental floss for cleaning your teeth. It also works as strong thread.",
    why: "Keeping your mouth clean helps you stay healthy. Floss can also be used to tie or mend things.",
    steps: ["Use it after eating to clean between your teeth.", "Use a length as strong thread for small repairs if needed."],
    spec: "Dental floss", maintain: "Replace it when it runs low.",
    q: "", video: "" },

  { serial: "K72-HYG-004", cat: "HYG", icon: "hygiene", name: "Wipes",
    short: "Wet wipes for cleaning hands, face and body when water is short.",
    why: "Clean hands prevent illness, especially when water is limited.",
    steps: ["Wipe your hands before eating and after using the toilet.", "Use them to freshen up when you can't bathe.", "Seal the pack after each use so they don't dry out."],
    spec: "Wet wipes", maintain: "Check the pack is still sealed and moist. Replace it when opened for long.",
    q: "", video: "" },

  { serial: "K72-HYG-005", cat: "HYG", icon: "hygiene", name: "Toothbrush Set",
    short: "A toothbrush and toothpaste kit.",
    why: "Daily routines like brushing help you stay healthy and feel normal during a stressful time.",
    steps: ["Brush twice a day using as little water as possible.", "Rinse and dry the toothbrush before packing it.", "Keep it in its case."],
    spec: "Toothbrush and toothpaste", maintain: "Replace the toothbrush every 3 months and the toothpaste before it expires.",
    q: "", video: "" },

  { serial: "K72-HYG-006", cat: "HYG", icon: "razor", name: "Disposable Razor",
    short: "A light disposable razor for shaving during an evacuation.",
    why: "Staying clean and groomed helps you feel normal and healthy in a shelter. It can also trim small things in a pinch.",
    steps: ["Wet the skin first, with soap if you have it.", "Shave slowly in the direction the hair grows.", "Rinse it, put the cap back on and pack it."],
    spec: "Disposable, with cap", maintain: "Keep the cap on and replace it once the blade is dull.",
    q: "", video: "" },

  { serial: "K72-HYG-007", cat: "HYG", icon: "clipper", name: "Nail Clipper",
    short: "A steel nail clipper on a ball chain so it doesn't get lost.",
    why: "Short, clean nails prevent cuts, dirt and infection when water is limited. It can also snip thread and small cords.",
    steps: ["Trim nails straight across.", "Wipe the clipper clean after use.", "Clip it to a zipper or inside a pouch with the chain."],
    spec: "Stainless steel, with ball chain", maintain: "Wipe it dry to prevent rust.",
    q: "", video: "" },

  { serial: "K72-TLS-001", cat: "TLS", icon: "tool", name: "Compact Multi-Tool",
    short: "Pliers, blades and other tools in one compact tool.",
    why: "After a storm you may need to cut, grip, open or fix things. A multi-tool covers most small jobs.",
    steps: ["Open only the tool you need and lock it before using.", "Cut away from your body.", "Close every tool before putting it away."],
    spec: "Compact, several tools in one", maintain: "Wipe it dry after use and add a drop of oil to the hinges now and then.",
    q: "multi tool how to use safely", video: "" },

  { serial: "K72-TLS-002", cat: "TLS", icon: "scissors", name: "Folding Scissors (Stainless)",
    short: "Stainless steel scissors that fold for safe packing.",
    why: "Useful for cutting bandages, tape, cord, plastic and fabric.",
    steps: ["Unfold them fully before cutting.", "Cut away from your body and other people.", "Fold them closed before packing."],
    spec: "Stainless steel, folding", maintain: "Wipe them dry to keep them sharp and rust-free.",
    q: "", video: "" },

  { serial: "K72-TLS-003", cat: "TLS", icon: "rope", name: "Paracord",
    short: "Strong cord for tying, hanging, lashing and repairs.",
    why: "Cord lets you put up a shelter, hang a tarp, secure gear or make a clothesline.",
    steps: ["Measure the length you need before cutting.", "Tie it off with a secure knot.", "If you know how, seal cut ends with a little heat so they don't fray."],
    spec: "Paracord", maintain: "Coil it neatly so it doesn't tangle. Replace damaged lengths.",
    q: "paracord knots basics", video: "" },

  { serial: "K72-TLS-004", cat: "TLS", icon: "ziptie", name: "Zip Ties",
    short: "Plastic ties in two lengths for quick fastening and repairs.",
    why: "Zip ties fix, bundle and secure things in seconds, from tent poles to broken straps.",
    steps: ["Choose the short or long tie for the job.", "Loop it around and pull it tight through the lock.", "Trim the extra tail with scissors."],
    spec: "100 mm and 200 mm", maintain: "Replace any you use.",
    parts: ["100 mm", "200 mm"],
    q: "", video: "" },

  { serial: "K72-SRV-001", cat: "SRV", icon: "fish", name: "Fishing Kit",
    short: "A small fishing set to catch food from rivers or the sea.",
    why: "If food runs short over several days, fishing gives you another source of food. Only fish in safe, clean water, never in floodwater.",
    steps: ["Wind the nylon thread around the wood dowel or tin can as your line.", "Tie on a swivel, then a hook. Use the deep or shallow hook to match the water.", "Add a sinker above the hook so it sinks.", "Follow the instruction sticker in the kit."],
    spec: "Hooks, sinkers, swivel, nylon thread, tin can, wood dowel, instruction sticker", maintain: "Check the hooks for rust and keep the kit dry.",
    parts: ["Hook (deep water)", "Hook (shallow water)", "Sinker (10 g)", "Sinker (5 g)", "Swivel", "Nylon thread", "Tin can (silver)", "Wood dowel", "Sticker for instructions (8 × 5 cm)"],
    q: "survival fishing kit how to use", photos: 2, video: "" },

  { serial: "K72-SRV-002", cat: "SRV", icon: "trap", name: "Trap Kit",
    short: "Dowels, thread and instructions for setting a simple snare.",
    why: "If you are stranded for several days, a simple trap can help you catch small game for food.",
    steps: ["Read the instruction stickers in the kit first.", "Set the trap using the dowels and thread as shown.", "Check it regularly and handle any catch safely."],
    spec: "Dowels, brown and black thread, instruction stickers", maintain: "Keep the thread dry and untangled.",
    parts: ["Dowels", "Thread (brown)", "Thread (black)", "Sticker (30 × 15)", "Sticker (50 × 30)"],
    q: "", photos: 2, video: "" },

  { serial: "K72-SRV-003", cat: "SRV", icon: "fire", name: "Fire Starter Kit",
    short: "A ferro rod with cotton balls and petroleum jelly to start a fire, even when it's damp.",
    why: "Fire gives warmth, boils water and helps you signal. Cotton balls with petroleum jelly catch a spark and burn long enough to light kindling.",
    steps: ["Prepare dry kindling and small sticks in a safe, open spot.", "Rub a cotton ball with petroleum jelly and fluff it out.", "Scrape the ferro rod firmly to throw sparks onto the cotton ball.", "Feed the flame slowly with small sticks. Never leave a fire unattended."],
    spec: "Ferro rod, cotton balls, petroleum jelly, tin can, instruction sticker", maintain: "Keep the cotton balls dry in the sealed tin. Practise using it at home.",
    parts: ["Ferro rod", "Cotton balls", "Petroleum jelly", "Tin can (black)", "Sticker (50 × 30)"],
    q: "how to use ferro rod cotton ball petroleum jelly", video: "" },

  { serial: "K72-SRV-004", cat: "SRV", icon: "slingshot", name: "Slingshot",
    short: "A wooden slingshot with a strong rubber band.",
    why: "It can be used to signal by launching small objects, to scare off animals, or to hunt small game when stranded for several days.",
    steps: ["Hold the handle firmly with your arm straight.", "Place a small stone in the pouch and pull back to your cheek.", "Aim away from people, windows and animals you don't intend to hit, then release."],
    spec: "Wooden frame, rubber band", maintain: "Keep the band out of the sun and replace it if it cracks.",
    q: "", video: "" }
];

KIDAN.modules = [
  { code: "A", icon: "bag", name: "Bag Preparation", short: "Why 72 hours, local hazards, packing and carrying.",
    lessons: ["Why 72 hours?", "Knowing local hazards", "Selecting the right bag", "Packing and compartment organisation", "Weight distribution and ergonomics", "Inspection routines and carrying technique"],
    items: ["K72-BAG-000", "K72-BAG-001", "K72-BAG-002"], q: "how to pack a 72 hour go bag", video: "https://youtu.be/6_uHsU6K71Y" },
  { code: "B", icon: "water", name: "Water", short: "Preparation, storage, carrying, safety and purification.",
    lessons: ["Daily water needs per person", "Safe storage and rotation", "Carrying water efficiently", "Purification and filtration", "Floodwater dangers"],
    items: ["K72-WTR-001", "K72-WTR-002", "K72-WTR-003"], q: "emergency water purification methods", video: "" },
  { code: "C", icon: "food", name: "Food", short: "Emergency selection, safe storage and non-gas preparation.",
    lessons: ["Choosing ready-to-eat food", "Safe storage and expiry tracking", "Non-gas preparation methods", "Rationing across 72 hours"],
    items: ["K72-FOD-001", "K72-FOD-002", "K72-FOD-003", "K72-SRV-001", "K72-SRV-002"], q: "no cook emergency food", video: "" },
  { code: "D", icon: "aid", name: "First Aid", short: "Kit contents, basic demonstrations, critical medication.",
    lessons: ["Kit contents and layout", "Wound cleaning and dressing", "Bleeding control", "Sprains and immobilisation", "Critical medication management"],
    items: ["K72-MED-001", "K72-MED-002"], q: "basic first aid training bleeding control", video: "" },
  { code: "E", icon: "battery", name: "Power & Light", short: "Lights, battery care, power bank and phone power.",
    lessons: ["Flashlight and headlamp use", "Battery care and storage", "Power bank optimisation", "Phone power management"],
    items: ["K72-LGT-001", "K72-LGT-002", "K72-LGT-003", "K72-PWR-001", "K72-PWR-002", "K72-PWR-003", "K72-PWR-004"], q: "save phone battery power outage", video: "" },
  { code: "F", icon: "radio", name: "Communication", short: "Emergency channels, phone prep, radio and family protocol.",
    lessons: ["Official emergency channels", "Phone preparation", "Radio operation", "Family communication during outages"],
    items: ["K72-COM-001", "K72-COM-002", "K72-DOC-001", "K72-DOC-002"], q: "family emergency communication plan", video: "" },
  { code: "G", icon: "fire", name: "Fire & Light", short: "Safe use of fire-starting equipment and light tools.",
    lessons: ["Lighters, matches and ferro rods", "Fire safety and ventilation", "Flame safety indoors", "Light discipline in shelters"],
    items: ["K72-SRV-003", "K72-LGT-003"], q: "fire starting safety ferro rod", video: "" },
  { code: "H", icon: "tent", name: "Shelter & Weather", short: "Rain protection, temporary shelter, keeping gear dry.",
    lessons: ["Rain protection layers", "Setting up a tarp shelter", "Choosing a safe spot", "Keeping gear dry"],
    items: ["K72-WTH-001", "K72-WTH-002", "K72-WTH-003", "K72-WTH-004", "K72-WTH-005"], q: "emergency tarp shelter rain", video: "" },
  { code: "I", icon: "route", name: "Navigation & Movement", short: "Offline navigation, safe routes, moving with gear.",
    lessons: ["Paper map and compass navigation", "Safe route selection in floods", "Moving with a loaded bag", "Group movement and headcounts"],
    items: ["K72-COM-001"], q: "map and compass navigation basics", video: "" },
  { code: "J", icon: "rope", name: "Cordage & Basic Tools", short: "Essential knots, cordage uses and emergency tools.",
    lessons: ["Bowline, clove hitch, taut-line hitch", "Lashing and securing gear", "Multi-tool use and safety", "Improvised repairs"],
    items: ["K72-TLS-003", "K72-TLS-001", "K72-TLS-002", "K72-TLS-004"], q: "essential knots survival", video: "" }
];

KIDAN.readiness = [
  { code: "RC-01", text: "Can you find your flashlight in complete darkness?", module: "E" },
  { code: "RC-02", text: "Can you operate and charge devices with your power bank?", module: "E" },
  { code: "RC-03", text: "Can you access your waterproof documents quickly?", module: "A" },
  { code: "RC-04", text: "Can you carry your bag comfortably over a distance?", module: "A" },
  { code: "RC-05", text: "Can you perform basic first aid on an injury?", module: "D" },
  { code: "RC-06", text: "Can your family coordinate communication during a network outage?", module: "F" },
  { code: "RC-07", text: "Do you know your exact evacuation route and destination?", module: "I" }
];

/* Line icons (24×24) */
KIDAN.icons = {
  razor: '<path d="M4 10h11l2-2 3 3-2 2-3-3"/><path d="M15 10v4H4z"/><path d="M8 14l-3 7"/>',
  clipper: '<path d="M3 10h14a3 3 0 0 1 0 6H8"/><path d="M3 10l5 4M17 13h.01"/><circle cx="20.5" cy="8.5" r="1.5"/>',
  slingshot: '<path d="M12 21v-8M12 13L6 4M12 13l6-9"/><path d="M6 4c2 4 10 4 12 0"/>',
  cup: '<path d="M6 5h12l-1.5 14a2 2 0 0 1-2 1.8h-5a2 2 0 0 1-2-1.8z"/><path d="M6.5 10h11M7 15h10"/>',
  honey: '<path d="M9 3h6v3H9z"/><path d="M7 6h10v13a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2z"/><path d="M10 12l2-1.5 2 1.5v2.5l-2 1.5-2-1.5z"/>',
  salt: '<path d="M8 9h8l-1 12H9z"/><path d="M8 9a4 4 0 0 1 8 0"/><path d="M11 6h.01M13 6h.01M12 4h.01"/>',
  spoon: '<ellipse cx="12" cy="7" rx="3.5" ry="4.5"/><path d="M12 11.5V21"/>',
  glow: '<rect x="9" y="3" width="6" height="18" rx="3"/><path d="M5 8l-2-1M5 16l-2 1M19 8l2-1M19 16l2 1"/>',
  solar: '<rect x="3" y="4" width="18" height="11" rx="1"/><path d="M3 9.5h18M9 4v11M15 4v11M12 15v5M8 20h8"/>',
  notebook: '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 3v18M12 8h4M12 12h4"/>',
  pencil: '<path d="M4 20l1-5L16 4l4 4L9 19z"/><path d="M14 6l4 4"/>',
  trash: '<path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14"/>',
  sleep: '<rect x="3" y="9" width="18" height="8" rx="4"/><path d="M7 9V7M7 17v3M17 17v3"/>',
  towel: '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M5 8h14M5 16h14"/>',
  scissors: '<circle cx="6" cy="18" r="3"/><circle cx="18" cy="18" r="3"/><path d="M8 16L19 3M16 16L5 3"/>',
  ziptie: '<path d="M4 12a8 8 0 1 0 8-8"/><rect x="10" y="2" width="5" height="4"/><path d="M15 4h6"/>',
  fish: '<path d="M3 12c3-5 9-6 14-3l4-3v12l-4-3c-5 3-11 2-14-3z"/><circle cx="8" cy="11" r=".8"/>',
  trap: '<path d="M4 20h16M7 20L12 6l5 14M9.5 14h5"/><circle cx="12" cy="5" r="1.5"/>',
  water: '<path d="M12 3s6 7 6 11a6 6 0 0 1-12 0c0-4 6-11 6-11z"/>',
  filter: '<path d="M4 4h16l-6 8v6l-4 2v-8z"/>',
  food: '<rect x="5" y="7" width="14" height="13" rx="1"/><path d="M5 11h14M8 3h8v4H8z"/>',
  aid: '<rect x="3" y="6" width="18" height="14" rx="1"/><path d="M12 9v8M8 13h8M9 6V4h6v2"/>',
  pill: '<rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-35 12 12)"/><path d="M9.5 8.5l5 7"/>',
  head: '<path d="M4 13a8 8 0 0 1 16 0"/><rect x="9" y="11" width="6" height="5"/><path d="M12 16v4M8 20h8"/>',
  torch: '<path d="M8 3h8l-1 6H9z"/><rect x="9" y="9" width="6" height="12"/><path d="M12 13v3"/>',
  battery: '<rect x="3" y="7" width="16" height="10" rx="1"/><path d="M21 10v4M7 10v4M11 10v4"/>',
  plug: '<path d="M9 3v5M15 3v5M6 8h12v4a6 6 0 0 1-12 0zM12 18v3"/>',
  radio: '<rect x="3" y="8" width="18" height="12" rx="1"/><path d="M7 8l10-5M7 14h4"/><circle cx="16" cy="14" r="2.5"/>',
  phone: '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>',
  whistle: '<path d="M3 12h9a5 5 0 1 0 5-5H9"/><circle cx="17" cy="12" r="1.5"/><path d="M3 9v6"/>',
  doc: '<path d="M6 3h9l4 4v14H6z"/><path d="M15 3v4h4M9 12h7M9 16h7"/>',
  cash: '<rect x="2" y="6" width="20" height="12" rx="1"/><circle cx="12" cy="12" r="3"/><path d="M6 9v6M18 9v6"/>',
  rain: '<path d="M3 12a9 9 0 0 1 18 0z"/><path d="M12 12v7a2 2 0 0 1-4 0"/>',
  tent: '<path d="M12 4L2 20h20z"/><path d="M12 4v16M8 20l4-6 4 6"/>',
  blanket: '<path d="M4 5h16v14H4z"/><path d="M4 9l16 6M4 15l16-6"/>',
  hygiene: '<rect x="7" y="8" width="10" height="13" rx="1"/><path d="M10 8V5h4v3M12 3v2"/>',
  tool: '<path d="M14 4l6 6-3 3-6-6zM11 7l-8 8 3 3 8-8"/>',
  rope: '<path d="M6 4c6 0 6 4 0 4s-6 4 0 4 6 4 0 4-6 4 0 4h8"/>',
  fire: '<path d="M12 3c1 4 6 5 6 11a6 6 0 0 1-12 0c0-3 2-4 2-7 2 1 3 2 4 4 0-3 0-5 0-8z"/>',
  compass: '<circle cx="12" cy="12" r="9"/><path d="M15 9l-2 5-4 1 2-5z"/>',
  bag: '<path d="M6 8h12l1 13H5z"/><path d="M9 8V5a3 3 0 0 1 6 0v3M9 13h6"/>',
  route: '<circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M8 18h6a3 3 0 0 0 0-6h-4a3 3 0 0 1 0-6h6"/>',
  play: '<path d="M7 4l13 8-13 8z" fill="currentColor" stroke="none"/>'
};
