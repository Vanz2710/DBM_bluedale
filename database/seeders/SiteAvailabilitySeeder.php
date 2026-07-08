<?php

namespace Database\Seeders;

use App\Models\AdvertisingProduct;
use Illuminate\Database\Seeder;

class SiteAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $sites = [
            [
                'site_name'    => 'Jalan Kuching (before turning to Jalan Kepong Lama)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.2109739, 101.6718485',
            ],
            [
                'site_name'    => 'Old Klang Road (Near Petron)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.08304, 101.6632855',
            ],
            [
                'site_name'    => 'Jalan Pudu (opposite Bomba Jalan Pudu)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1344031, 101.7131772',
            ],
            [
                'site_name'    => 'Jalan Sungai Besi towards Jalan Istana',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1203253, 101.708092',
            ],
            [
                'site_name'    => 'Jalan Lingkaran Tengah 2 (heading to Sri Petaling) via MRR2',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.079625, 101.7200881',
            ],
            [
                'site_name'    => 'MRR2 towards exit Jalan Ampang',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1660274, 101.7535154',
            ],
            [
                'site_name'    => 'Jalan Maarof, Bangsar (towards Pusat Bandar Damansara, near site)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1426487, 101.6630274',
            ],
            [
                'site_name'    => 'LDP (towards exit to Desa Park City)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1930132, 101.6192588',
            ],
            [
                'site_name'    => 'LDP Sunway-Puchong (near BHP)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Puchong, Selangor',
                'coordinate'   => '3.0786028, 101.6137372',
            ],
            [
                'site_name'    => 'Persiaran Surian – Towards Jalan Mahogani, PJ',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kota Damansara, Selangor',
                'coordinate'   => '3.1499946, 101.5889706',
            ],
            [
                'site_name'    => 'Persiaran Surian (towards Mutiara Damansara)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Petaling Jaya, Selangor',
                'coordinate'   => '3.1548224, 101.6041125',
            ],
            [
                'site_name'    => 'Ara Damansara (next to Citta Mall) – view Subang Airport to Subang Jaya',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Petaling Jaya, Selangor',
                'coordinate'   => '3.1100427, 101.5804517',
            ],
            [
                'site_name'    => 'Jalan Putra Permai – Seri Kembangan (Opposite Giant)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Seri Kembangan, Selangor',
                'coordinate'   => '2.9846137, 101.662668',
            ],
            [
                'site_name'    => 'Taman Desa (Heading towards Midvalley)',
                'status'       => 'Existing',
                'type'         => 'A1',
                'product_type' => 'Billboard',
                'state_city'   => 'Kuala Lumpur',
                'coordinate'   => '3.1121574, 101.6852904',
            ],
        ];

        foreach ($sites as $site) {
            AdvertisingProduct::firstOrCreate(
                [
                    'site_name'    => $site['site_name'],
                    'product_type' => $site['product_type'],
                ],
                $site
            );
        }
    }
}
