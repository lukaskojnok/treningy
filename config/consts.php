<?php
// 1 = pondelok, 2 = utorok, 3 = streda, 4 = stvrtok, 5 = piatok, 6 = sobota, 7 = nedela.
define('TRENINGY_DEN_OTVORENIA_DALSIEHO_TYZDNA', 4);
define('REZERVACIE_CASOVE_PASMO', 'Europe/Bratislava');

$ADMIN_TOP["link"] = "";
$ADMIN_TOP["text"] = "";
$ADMIN_TOP["class"] = "";

define('TEAMS', [ 
  'dospeli-m-a' => 'Muži',
  'u19-m-a' => 'U19 - Starší dorast',
  'u15-m-a' => 'U15 - Starší žiaci',
  'u13-m-a' => 'U13 - Mladší žiaci',
  'u11-m-a' => 'U11 - Prípravka',
  'u10-m-a' => 'U10 - Prípravka',
  'u09-m-a' => 'U9 - Prípravka (A)',
  'u09-m-b' => 'U9 - Prípravka (B)',
]);

// Podmienky platia iba pre udalosti typu trening na ihrisku A.
// Acko_viackrat_za_tyzden = bez limitu.
// Acko_2x_za_tyzden = najviac 2 treningy timu na Acku za kalendarny tyzden.
// Acko_1x_za_tyzden = najviac 1 trening timu na Acku za kalendarny tyzden.
// Zapasy tieto podmienky nemaju. Tim bez znamej podmienky nemoze ulozit trening na A.
define('TEAMS_PODMIENKY', [
  'dospeli-m-a' => [
    "Acko_viackrat_za_tyzden"
  ],
  'u19-m-a' => [
    "Acko_2x_za_tyzden"
  ],
  'u15-m-a' => [
    "Acko_1x_za_tyzden"
  ],
  'u13-m-a' => [
    "Acko_1x_za_tyzden"
  ],
  'u11-m-a' => [
    "Acko_1x_za_tyzden"
  ],
  'u10-m-a' => [
    "Acko_1x_za_tyzden"
  ],
  'u09-m-a' => [
    "Acko_1x_za_tyzden"
  ],
  'u09-m-b' => [
    "Acko_1x_za_tyzden"
  ],
]);


define('POSTY', [
  'brankar' => 'Brankár',
  'obranca' => 'Obranca',
  'zaloznik' => 'Záložník',
  'utocnik' => 'Útočník',
  'hrac' => 'Hráč v poli',
]);

define('TRENERI', [
  "admin" => [
    "meno" => "Lukáš Kojnok",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "dospeli-m-a" => "admin",
      "u19-m-a" => "admin",
      "u15-m-a" => "admin",
      "u13-m-a" => "admin",
      "u11-m-a" => "admin",
      "u10-m-a" => "admin",
      "u09-m-a" => "admin",
      "u09-m-b" => "admin",
    ]
  ],

  "cyrilsiman" => [
    "meno" => "Cyril Siman",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "dospeli-m-a" => "Hlavný tréner",
      "u19-m-a" => "Hlavný tréner",
    ]
  ],

  "zoltangomori" => [
    "meno" => "Zoltán Gömöri",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "u15-m-a" => "Hlavný tréner",
    ]
  ],

  "slavomirspisak" => [
    "meno" => "Slavomír Spišák",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "u11-m-a" => "Hlavný tréner",
    ]
  ],

  "danielvojcik" => [
    "meno" => "Daniel Vojčík",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "u11-m-b" => "Hlavný tréner",
    ]
  ],

  "janhodos" => [
    "meno" => "Ján Hodoš",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "u09-m-a" => "Hlavný tréner",
    ]
  ],

  "janhodos" => [
    "meno" => "Lukáš Kožička",
    "phone" => "",
    "email" => "",
    "photo" => "",
    "timy" => [
      "u13-m-a" => "Hlavný tréner",
    ]
  ],
]);

define('IHRISKA', [
  "B" => [
    "parts" => [
      "B1" => "B1 tréningové",
      "B2" => "B2 tréningové",
      "B3" => "B3 tréningové",
      "B4" => "B4 tréningové",
    ],
    "popis" => "Tréningové"
  ],
  "A" => [
    "parts" => [
      "A1" => "A1 hlavné",
      "A2" => "A2 hlavné",
      "A3" => "A3 hlavné",
      "A4" => "A4 hlavné",
    ],
    "popis" => "Hlavné"
  ],
  "C" => [
    "parts" => [
      "C1" => "C1 pri Áčku",
      "C2" => "C2 pri Áčku",
    ],
    "popis" => "Tréningové"
  ],
  "U" => [
    "parts" => [
      "U1" => "U1 umelé",
    ],
    "popis" => "Umelé"
  ],
]);
