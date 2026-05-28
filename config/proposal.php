<?php

return [

    'company' => [
        'name'    => 'Bluedale Integrated (M) Sdn. Bhd.',
        'reg_no'  => '(Co. # 535305-K)',
        'address' => 'No.31-2, Block F2, Level 2, Jalan PJU 1/42A, Dataran Prima, 47301 Petaling Jaya, Selangor Darul Ehsan, Malaysia',
        'tel'     => '+(6)03 - 7886 9219',
        'fax'     => '+(6)03 - 7887 8212',
        'website' => 'www.bluedale.com.my',
        'email'   => 'enquiry@bluedale.com.my',
        'group_label' => 'A Member of Bluedale Group of Companies',
    ],

    'signatory' => [
        'name'        => 'NURUL ASYIQIN JAAFAR',
        'title'       => 'Assistant Business Manager',
        'mobile'      => '+6014- 907 253',
        'signature_label' => 'Asyiqin',
    ],

    // Default pricing tiers (RM per pc/site per month). Override per request.
    'pricing' => [
        'Lamp Post Bunting' => [
            'normal_price'   => 180,    // shown in banner
            'promo_price'    => 120,    // shown as header rate
            'per_unit_label' => 'pcs',
            'sst_rate'       => 0.08,
        ],
        'Temp Board' => [
            'normal_price'   => 6000,
            'promo_price'    => 4800,
            'per_unit_label' => 'site',
            'sst_rate'       => 0.08,
        ],
        'Billboard' => [
            'normal_price'   => 6000,
            'promo_price'    => 4800,
            'per_unit_label' => 'site',
            'sst_rate'       => 0.08,
        ],
    ],

    // Default terms — editable per proposal.
    'terms' => [
        'Payment Term: 100% Pre-Payment from the Investment Cost 1 week upon confirmation.',
        'Contract duration: :duration.',
        'Material must be submitted 14 working days before the in-charge date.',
        'The site is subject to availability, authority arrangement, and safety regulations. In the event that the proposed sites are unavailable on the installation day—whether due to changes in local council regulations, site upgrades to a protocol road, the presence of existing boards from other parties, or safety-related issues—Bluedale will proceed to install the bunting at a nearby location as close as possible to the original site, or suggest an alternative. Photo evidence will be provided as proof of installation.',
        'Replacement of missing bunting is only applicable at no extra charge for clients who purchase the current promotion with a minimum 3-month contract or longer. If a new skin is required, an additional fee of RM99/pcs will apply.',
        'PROMO UNTIL :promo_until.',
        'Any delay in artwork will not affect the in-charge date. There will be no change on the in-charge date should the material arrive after the material deadline. No postponement of the in-charge date.',
        'There\'s no cancellation upon proposal confirmation. Any cancellation will be charged in full.',
        'Others Terms & Conditions apply.',
    ],

    'landmark_categories' => [
        'Exhibition Center',
        'Shopping Mall',
        'International School',
        'Hosp/ Medical Center',
        'Hotel',
        'Highway',
        'Landmark',
    ],
];
