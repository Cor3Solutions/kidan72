/* =========================================================
   KIDAN 72 — Content data
   Edit this file to change items, serial numbers, or videos.

   PHOTOS: save each photo in assets/img/products/ named by its
   serial number, e.g.  K72-WTR-001.jpg
   Bag photos: K72-BAG-000.jpg (Complete Go-Bag),
               K72-BAG-001.jpg (Black Pouch),
               K72-BAG-002.jpg (Coyote Brown Pouch)
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
  "K72-BAG-000": null,   // Complete Go-Bag (both pouches inside the main bag)
  "K72-BAG-001": null,   // Black Pouch
  "K72-BAG-002": null,   // Coyote Brown Pouch
  // Individual items
  "K72-WTR-001": null, "K72-WTR-002": null,
  "K72-FOD-001": null,
  "K72-MED-001": null, "K72-MED-002": null,
  "K72-LGT-001": null, "K72-LGT-002": null,
  "K72-PWR-001": null, "K72-PWR-002": null,
  "K72-COM-001": null, "K72-COM-002": null, "K72-COM-003": null,
  "K72-DOC-001": null,
  "K72-WTH-001": null, "K72-WTH-002": null, "K72-WTH-003": null,
  "K72-HYG-001": null,
  "K72-TLS-001": null, "K72-TLS-002": null, "K72-TLS-003": null,
  "K72-NAV-001": null
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

KIDAN.categories = {
  WTR: "Water & Hydration",
  FOD: "Nourishment",
  MED: "Medical",
  LGT: "Illumination",
  PWR: "Power & Energy",
  COM: "Communication",
  DOC: "Documents & ID",
  WTH: "Weather & Shelter",
  HYG: "Hygiene",
  TLS: "Tools & Hardware",
  NAV: "Navigation"
};

/* ---------------------------------------------------------
   3) BAGS — the three bag options.
      "contents" lists which items are packed in each pouch.
      DRAFT: pouch contents are based on the product photos;
      move serial numbers between the two lists if needed.
   --------------------------------------------------------- */
KIDAN.bags = [
  {
    serial: "K72-BAG-000", icon: "bag", name: "KIDAN 72 Complete Go-Bag", label: "Complete system",
    short: "The full 72-hour system: the main carry bag with the Black Pouch and the Coyote Brown Pouch packed inside.",
    summary: "Everything one person needs for the first 72 hours after a typhoon, flood or evacuation. The main carry bag holds both modular pouches, so you can grab the whole system at once or split it between family members.",
    includes: ["K72-BAG-001", "K72-BAG-002"],
    specs: [
      ["Configuration", "Main carry bag + Black Pouch + Coyote Brown Pouch"],
      ["Coverage", "1 person · 72 hours"],
      ["Contents", "All serialised items in both pouches"],
      ["Registration", "Logged in the KIDAN 72 Database with owner profile, inventory and expiry dates"],
      ["Inspection", "Monthly function check · Quarterly full inspection"]
    ]
  },
  {
    serial: "K72-BAG-001", icon: "aid", name: "Black Pouch", label: "Sustainment module",
    short: "Keeps the body going: water, food, first aid, medicine, hygiene and documents.",
    summary: "The sustainment module. Everything that keeps a person fed, hydrated, clean and treated for injuries, plus the documents and contact card needed after an evacuation.",
    contents: ["K72-WTR-001", "K72-WTR-002", "K72-FOD-001", "K72-MED-001", "K72-MED-002", "K72-HYG-001", "K72-DOC-001", "K72-DOC-002", "K72-COM-002"],
    specs: [
      ["Role", "Sustainment: hydration, nourishment, medical, hygiene, documents"],
      ["Colour", "Black"],
      ["Carry", "Inside the Complete Go-Bag, or on its own"]
    ]
  },
  {
    serial: "K72-BAG-002", icon: "tool", name: "Coyote Brown Pouch", label: "Field module",
    short: "Gets you through the conditions: light, power, radio, shelter, tools and navigation.",
    summary: "The field module. The gear for darkness, rain, signal loss and movement: lights, power, radio, shelter, cordage, tools and navigation.",
    contents: ["K72-LGT-001", "K72-LGT-002", "K72-PWR-001", "K72-PWR-002", "K72-COM-001", "K72-COM-003", "K72-WTH-001", "K72-WTH-002", "K72-WTH-003", "K72-TLS-001", "K72-TLS-002", "K72-TLS-003", "K72-NAV-001"],
    specs: [
      ["Role", "Field: light, power, communication, shelter, tools, navigation"],
      ["Colour", "Coyote brown"],
      ["Carry", "Inside the Complete Go-Bag, or on its own"]
    ]
  }
];

/* Items included with a bag but not sold on their own */
KIDAN.notSoldSeparately = ["K72-DOC-002"];

/* Each item: serial is K72-<CAT>-<NNN> */
KIDAN.items = [
  { serial: "K72-WTR-001", cat: "WTR", icon: "water", name: "Water Container",
    short: "Durable bottle carrying the drinking water you need for the first 72 hours.",
    why: "Water service and refilling stations are often cut for days after a typhoon. Plan about 3 to 4 litres per person per day for drinking and basic food preparation.",
    steps: ["Fill with clean drinking water and close the cap tightly.", "Write the fill date on the label.", "Pack it upright in a side pocket or at the base of the bag for balance.", "Drink small amounts regularly. Do not ration water to the point of dehydration."],
    spec: "BPA-free, 1–2 L, screw cap", maintain: "Replace stored water every 6 months. Check the cap and seal for cracks.",
    q: "emergency water storage bottle preparedness", video: "" },

  { serial: "K72-WTR-002", cat: "WTR", icon: "filter", name: "Water Purification Set",
    short: "Filter and purification tablets for when clean water runs out.",
    why: "Floodwater and collected water can carry bacteria and leptospirosis. Purification lets you safely use other sources such as rainwater or well water.",
    steps: ["Pre-filter cloudy water through a clean cloth.", "Run the water through the filter as directed by the manufacturer.", "For tablets, follow the dose on the pack and wait the full contact time.", "Never drink floodwater, even after filtering."],
    spec: "Filter straw + purification tablets", maintain: "Check tablet expiry. Backflush and dry the filter after each use.",
    q: "how to use water filter straw purification tablets", video: "" },

  { serial: "K72-FOD-001", cat: "FOD", icon: "food", name: "Emergency Food Ration",
    short: "Ready-to-eat, non-perishable food that needs no gas or stove.",
    why: "Electricity and LPG may be unavailable for days. Food that is ready to eat keeps energy up without cooking.",
    steps: ["Pack three days of ready-to-eat items such as ration bars, pull-tab cans, crackers.", "Include a manual can opener if any cans need one.", "Eat perishable items first, then shelf-stable ones.", "Keep food sealed and away from moisture."],
    spec: "Ready-to-eat, 3 days per person", maintain: "Log expiry dates. Rotate items one month before they expire.",
    q: "72 hour emergency food no cook", video: "" },

  { serial: "K72-MED-001", cat: "MED", icon: "aid", name: "First-Aid Kit",
    short: "Wound care, bandages, antiseptic and basic trauma supplies.",
    why: "Clinics may be overwhelmed or unreachable. Cuts from debris and injuries near floodwater need immediate cleaning and cover.",
    steps: ["Know where every item is. Practise opening the kit by feel.", "Clean the wound with clean water before applying antiseptic.", "Apply firm pressure with gauze to control bleeding.", "Cover the wound and keep it dry, especially around floodwater."],
    spec: "Gauze, bandages, tape, antiseptic, gloves, shears", maintain: "Inspect quarterly. Replace used or expired items.",
    q: "basic first aid kit how to use wound care", video: "" },

  { serial: "K72-MED-002", cat: "MED", icon: "pill", name: "Personal Medicine Pouch",
    short: "A labelled supply of each person's maintenance medicines.",
    why: "Pharmacies may be closed for days. A missed maintenance dose can turn an evacuation into a medical emergency.",
    steps: ["Pack medicines in a labelled waterproof pouch with the person's name.", "Include a written list of medicine names, doses and prescribing doctor.", "Add basic items such as paracetamol and oral rehydration salts.", "Ask your doctor about keeping an emergency supply."],
    spec: "Labelled, waterproof, with written list", maintain: "Check expiry monthly. Keep away from heat.",
    q: "emergency medication kit preparation", video: "" },

  { serial: "K72-LGT-001", cat: "LGT", icon: "head", name: "Headlamp",
    short: "Hands-free light for moving, carrying and working in the dark.",
    why: "Power outages after typhoons can last for days. A headlamp keeps both hands free for carrying children, gear, or holding a rail.",
    steps: ["Adjust the strap before you need it.", "Use low or red mode to save battery and protect night vision.", "Tilt the beam down when speaking to others.", "Keep it in the same pocket every time so you can find it in darkness."],
    spec: "100–300 lumens, red mode, water-resistant", maintain: "Test monthly. Keep charged or store batteries separately.",
    q: "how to use a headlamp properly", video: "" },

  { serial: "K72-LGT-002", cat: "LGT", icon: "torch", name: "Tactical Flashlight",
    short: "Waterproof hand light for long-range searching and signalling.",
    why: "A focused beam reaches farther than a headlamp for checking routes, finding people, or signalling rescuers.",
    steps: ["Clip it to the same spot every time so you can find it by feel.", "Use strobe mode to attract attention.", "Signal SOS: three short, three long, three short flashes.", "Keep spare batteries in a waterproof bag."],
    spec: "Waterproof, strobe mode", maintain: "Test monthly. Check the O-ring seal.",
    q: "flashlight SOS signal emergency", video: "" },

  { serial: "K72-PWR-001", cat: "PWR", icon: "battery", name: "High-Capacity Power Bank",
    short: "Keeps phones, radios and lights running when the grid is down.",
    why: "Your phone is your weather bulletin, family channel and backup light. A full power bank can extend it for several days.",
    steps: ["Charge to 100% as soon as a weather warning is issued.", "Turn on low-power mode and lower screen brightness first.", "Charge phones from about 20% to 80% to stretch capacity.", "Keep the bank and cables dry in a sealed bag."],
    spec: "20,000 mAh, USB-C and USB-A", maintain: "Recharge monthly even if unused. Replace if swollen or hot.",
    q: "power bank tips emergency phone battery", video: "" },

  { serial: "K72-PWR-002", cat: "PWR", icon: "plug", name: "Batteries & Cable Set",
    short: "Spare batteries and the right cable for every device you carry.",
    why: "A power bank is useless without the right cable. Spare batteries keep lights and radios going when everything else is flat.",
    steps: ["Match a cable to every device: USB-C, Lightning, micro-USB.", "Store batteries in a case to prevent short circuits.", "Use the oldest batteries first.", "Label which set belongs to which device."],
    spec: "AA/AAA set + 3 cable types", maintain: "Check battery dates yearly. Test cables quarterly.",
    q: "emergency battery storage tips", video: "" },

  { serial: "K72-COM-001", cat: "COM", icon: "radio", name: "Emergency Radio",
    short: "AM/FM radio with hand crank and solar charging.",
    why: "When cell towers and internet fail, AM/FM radio is how official bulletins and evacuation orders reach you.",
    steps: ["Know your local AM and FM emergency stations in advance.", "About one minute of cranking gives several minutes of listening.", "Extend the antenna fully and face a window for better reception.", "Listen at set times to conserve power."],
    spec: "AM/FM, hand crank, solar, USB output", maintain: "Test monthly. Charge by crank or solar.",
    q: "how to use hand crank emergency radio", video: "" },

  { serial: "K72-COM-002", cat: "COM", icon: "phone", name: "Phone Readiness Card",
    short: "A checklist card to set up your phone for offline use.",
    why: "A prepared phone keeps working with weak signal: offline maps, saved contacts and downloaded guides.",
    steps: ["Download offline maps of your area.", "Save emergency contacts and show ICE details on the lock screen.", "Turn on emergency alerts in settings.", "Use SMS instead of calls when the network is congested."],
    spec: "Laminated card", maintain: "Refresh offline maps every few months.",
    q: "prepare your phone for a disaster offline maps", video: "" },

  { serial: "K72-COM-003", cat: "COM", icon: "whistle", name: "Signal Whistle",
    short: "Loud whistle for calling for help when your voice cannot carry.",
    why: "A whistle carries much farther than shouting and costs almost no energy if you are trapped or separated.",
    steps: ["Attach it to the shoulder strap within reach.", "Three blasts means you need help.", "Teach every family member, including children, the signal.", "Blow in intervals and listen for a reply."],
    spec: "Pealess, 100+ dB", maintain: "Check the lanyard for wear.",
    q: "emergency whistle signal three blasts", video: "" },

  { serial: "K72-DOC-001", cat: "DOC", icon: "doc", name: "Waterproof Document Pouch",
    short: "Sealed pouch for IDs, titles and copies of important papers.",
    why: "Replacing documents lost to a flood can take months and delays assistance claims.",
    steps: ["Store copies of IDs, birth certificates, titles, insurance and medical records.", "Double-bag: a zip bag inside the dry pouch.", "Keep encrypted digital copies in cloud storage.", "Pack it in the innermost compartment, closest to your back."],
    spec: "Roll-top dry pouch, A4", maintain: "Update copies whenever a document changes.",
    q: "waterproof document storage emergency", video: "" },

  { serial: "K72-DOC-002", cat: "DOC", icon: "cash", name: "Emergency Cash & Contact Card",
    short: "Small bills, coins and a written emergency contact list.",
    why: "ATMs and e-wallets go down with power and signal. Small bills let you buy essentials.",
    steps: ["Keep small denominations: ₱20, ₱50, ₱100 bills and coins.", "Split the cash between two places in the bag.", "Write a paper contact list: family, barangay, LGU, nearest hospital.", "Keep IDs with the contact card."],
    spec: "Small bills + laminated contact card", maintain: "Review the contact list every 6 months.",
    q: "emergency cash and contact list preparedness", video: "" },

  { serial: "K72-WTH-001", cat: "WTH", icon: "rain", name: "Rain Poncho",
    short: "Heavy-duty poncho that covers both you and your bag.",
    why: "Staying dry prevents chills and keeps the bag's contents usable during evacuation in heavy rain.",
    steps: ["Put the poncho on over both you and the bag.", "Keep the hood and sides snug in strong wind.", "It doubles as a ground sheet or shelter roof.", "Dry it before repacking to prevent mildew."],
    spec: "Heavy-duty, pack-compatible", maintain: "Check for tears and seam leaks every 6 months.",
    q: "how to use poncho emergency shelter", video: "" },

  { serial: "K72-WTH-002", cat: "WTH", icon: "tent", name: "Tarp Shelter",
    short: "Lightweight tarp for temporary cover and keeping gear dry.",
    why: "Evacuation centres fill up and roofs get damaged. A tarp gives you dry space quickly.",
    steps: ["Choose high ground away from trees and runoff paths.", "Tie a ridgeline between two anchors with cordage.", "Drape the tarp and stake or weigh down the corners.", "Angle one side low into the wind and rain."],
    spec: "2 × 3 m, reinforced grommets", maintain: "Check grommets. Fold only when dry.",
    q: "simple tarp shelter setup", video: "" },

  { serial: "K72-WTH-003", cat: "WTH", icon: "blanket", name: "Thermal Emergency Blanket",
    short: "Reflective blanket that holds body heat when you are wet.",
    why: "Being wet in wind after floodwater can cause dangerous chills, even in the tropics.",
    steps: ["Wrap with the reflective side facing your body.", "Cover the head and neck, keeping the face clear.", "Use as a ground layer to block damp.", "The reflective side can signal rescuers."],
    spec: "Mylar, 140 × 210 cm", maintain: "Single use. Replace once opened.",
    q: "how to use emergency thermal blanket", video: "" },

  { serial: "K72-HYG-001", cat: "HYG", icon: "hygiene", name: "Hygiene Kit",
    short: "Soap, sanitiser, toothbrush, wipes, towel and waste bags.",
    why: "Poor sanitation after floods spreads disease quickly. Basic hygiene keeps the family healthy through day three.",
    steps: ["Clean hands before eating and after using the toilet.", "Use wipes for bathing when water is scarce.", "Seal waste in bags.", "Add sanitary and baby supplies if needed."],
    spec: "Soap, sanitiser, toothbrush, toothpaste, wipes, towel", maintain: "Replace used items. Check sanitiser expiry.",
    q: "emergency hygiene kit what to pack", video: "" },

  { serial: "K72-TLS-001", cat: "TLS", icon: "tool", name: "Multi-Tool",
    short: "Pliers, blade, drivers and cutters in one compact tool.",
    why: "Repair gear, open packaging, cut cordage, and handle small fixes without a full toolbox.",
    steps: ["Learn where each tool folds out before you need it.", "Always cut away from your body.", "Lock blades before use.", "Wipe dry and oil after exposure to water."],
    spec: "Locking blade, pliers, drivers", maintain: "Oil hinges every 3 months.",
    q: "how to use a multi tool beginner", video: "" },

  { serial: "K72-TLS-002", cat: "TLS", icon: "rope", name: "Rope & Locking Carabiner",
    short: "High-tensile rope with a screw-lock carabiner.",
    why: "Rope ties down tarps, secures bags, lashes gear and makes a line to hold during movement in rough ground.",
    steps: ["Learn the bowline, clove hitch and taut-line hitch.", "Screw the carabiner gate fully closed before loading it.", "Coil the rope so it pays out without tangling.", "Never rely on it for climbing or rescue unless rated and trained."],
    spec: "High-tensile rope + screw-lock carabiner", maintain: "Check for fraying and gate function after each use.",
    q: "essential knots bowline clove hitch carabiner", video: "" },

  { serial: "K72-TLS-003", cat: "TLS", icon: "fire", name: "Fire Starter Set",
    short: "Lighter, storm matches and ferro rod in a dry container.",
    why: "Useful for boiling water and light, but dangerous indoors or in crowded shelters. Use only in safe, open, ventilated areas.",
    steps: ["Keep the lighter and matches in a waterproof container.", "Pull the ferro rod back against the striker to throw sparks.", "Never leave a flame unattended.", "Never use fire indoors without ventilation."],
    spec: "Lighter, storm matches, ferro rod", maintain: "Test the lighter monthly.",
    q: "how to use ferro rod fire starter safely", video: "" },

  { serial: "K72-NAV-001", cat: "NAV", icon: "compass", name: "Compass & Area Map",
    short: "Navigation that works without GPS, signal or battery.",
    why: "Familiar streets look different under floodwater or debris. A paper map with routes marked keeps you on track.",
    steps: ["Mark the primary and secondary evacuation routes on a printed map.", "Mark the family meeting point and evacuation centre.", "Learn to orient the map to north with the compass.", "Avoid low ground, bridges and riverbanks."],
    spec: "Baseplate compass + printed barangay map", maintain: "Recheck routes against the LGU hazard map yearly.",
    q: "map and compass basics", video: "" }
];

KIDAN.modules = [
  { code: "A", icon: "bag", name: "Bag Preparation", short: "Why 72 hours, local hazards, packing and carrying.",
    lessons: ["Why 72 hours?", "Knowing local hazards", "Selecting the right bag", "Packing and compartment organisation", "Weight distribution and ergonomics", "Inspection routines and carrying technique"],
    items: ["K72-BAG-000"], q: "how to pack a 72 hour go bag", video: "" },
  { code: "B", icon: "water", name: "Water", short: "Preparation, storage, carrying, safety and purification.",
    lessons: ["Daily water needs per person", "Safe storage and rotation", "Carrying water efficiently", "Purification and filtration", "Floodwater dangers"],
    items: ["K72-WTR-001", "K72-WTR-002"], q: "emergency water purification methods", video: "" },
  { code: "C", icon: "food", name: "Food", short: "Emergency selection, safe storage and non-gas preparation.",
    lessons: ["Choosing ready-to-eat food", "Safe storage and expiry tracking", "Non-gas preparation methods", "Rationing across 72 hours"],
    items: ["K72-FOD-001"], q: "no cook emergency food", video: "" },
  { code: "D", icon: "aid", name: "First Aid", short: "Kit contents, basic demonstrations, critical medication.",
    lessons: ["Kit contents and layout", "Wound cleaning and dressing", "Bleeding control", "Sprains and immobilisation", "Critical medication management"],
    items: ["K72-MED-001", "K72-MED-002"], q: "basic first aid training bleeding control", video: "" },
  { code: "E", icon: "battery", name: "Power & Light", short: "Lights, battery care, power bank and phone power.",
    lessons: ["Flashlight and headlamp use", "Battery care and storage", "Power bank optimisation", "Phone power management"],
    items: ["K72-LGT-001", "K72-LGT-002", "K72-PWR-001", "K72-PWR-002"], q: "save phone battery power outage", video: "" },
  { code: "F", icon: "radio", name: "Communication", short: "Emergency channels, phone prep, radio and family protocol.",
    lessons: ["Official emergency channels", "Phone preparation", "Radio operation", "Family communication during outages"],
    items: ["K72-COM-001", "K72-COM-002", "K72-COM-003"], q: "family emergency communication plan", video: "" },
  { code: "G", icon: "fire", name: "Fire & Light", short: "Safe use of fire-starting equipment and light tools.",
    lessons: ["Lighters, matches and ferro rods", "Fire safety and ventilation", "Flame safety indoors", "Light discipline in shelters"],
    items: ["K72-TLS-003"], q: "fire starting safety ferro rod", video: "" },
  { code: "H", icon: "tent", name: "Shelter & Weather", short: "Rain protection, temporary shelter, keeping gear dry.",
    lessons: ["Rain protection layers", "Setting up a tarp shelter", "Choosing a safe spot", "Keeping gear dry"],
    items: ["K72-WTH-001", "K72-WTH-002", "K72-WTH-003"], q: "emergency tarp shelter rain", video: "" },
  { code: "I", icon: "route", name: "Navigation & Movement", short: "Offline navigation, safe routes, moving with gear.",
    lessons: ["Paper map and compass navigation", "Safe route selection in floods", "Moving with a loaded bag", "Group movement and headcounts"],
    items: ["K72-NAV-001"], q: "map and compass navigation basics", video: "" },
  { code: "J", icon: "rope", name: "Cordage & Basic Tools", short: "Essential knots, cordage uses and emergency tools.",
    lessons: ["Bowline, clove hitch, taut-line hitch", "Lashing and securing gear", "Multi-tool use and safety", "Improvised repairs"],
    items: ["K72-TLS-001", "K72-TLS-002"], q: "essential knots survival", video: "" }
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
