<?php

namespace Database\Seeders;

use App\Models\AdvertisingProduct;
use Illuminate\Database\Seeder;

class TempBoardProductPlaceSeeder extends Seeder
{
    public function run(): void
    {
        $places = [
            ['KL Ampang: Jalan Ampang (opposite Russian Embassy)', 'Existing', 'A1'],
            ['KL - Bangsar: Jalan Maarof, Bangsar (heading to Pusat Bandar Damansara)', 'Existing', 'reject'],
            ['KL - Brickfields: Jalan Tun Sambanthan towards Jalan Robson, Brickfields KL', 'Existing', 'A1'],
            ['KL - Bukit Jalil: Lebuhraya Bukit Jalil (heading to Pavilion Bukit Jalil)', 'Existing', 'A1'],
            ['KL - Bukit Jalil: Lebuhraya Bukit Jalil (next to Depot Imigresen Bukit Jalil)', 'Raw New', 'A2'],
            ['KL - Bukit Jalil: Lebuhraya Bukit Jalil Towards Bandar Kinrara Puchong (After Caltex)', 'Existing', 'A1'],
            [
                'KL - Bukit Jalil: Persiaran Puncak Jalil 1, Bukit Jalil',
                'Existing',
                'A1',
                [
                    'site_code' => 'TB-WPKL-0001-DBKL-A',
                    'size' => '15 feet x 10 feet',
                    'state_city' => 'KL - Bukit Jalil',
                    'coordinate' => '3.017973, 101.675106',
                    'nearest_landmarks' => [
                        ['category' => 'Exhibition Center', 'place' => '5.5km to Pavilion Bukit Jalil Exhibition Centre'],
                        ['category' => 'Shopping Mall', 'place' => '5.9km to Pavilion Bukit Jalil'],
                        ['category' => 'International School', 'place' => '6.2km to Tzu Chi International School'],
                        ['category' => 'Hosp/ Medical Centre', 'place' => '8.2km to Columbia Asia Bukit Jalil'],
                    ],
                ],
            ],
            ['KL City Centre: Jalan Kuching (before turning to Jalan Kepong Lama)', 'Existing', 'ongoing'],
            ['KL City Centre: Jalan Kuching (beside Bus Stop, in front of Petronas)', 'Raw New', 'A2'],
            ['KL - City Centre: Jalan Kuching to Bulatan Kepong, KL', 'Existing', 'A1'],
            ['KL City Centre: Jalan Loke Yew (turning to Dewan Bahasa Pustaka)', 'Raw New', 'A2'],
            ['KL - City Centre: Jalan Semantan (750 meter to Jalan Tuanku Abdul Halim), KL', 'Existing', 'A1'],
            ['KL City Centre: Jalan Sultan Salahuddin (towards Lebuhraya Sultan Iskandar)', 'Existing', 'reject'],
            ['KL City Centre: Jalan Tuanku Abdul Halim (heading to MITEC)', 'Existing', 'reject'],
            ['KL City Centre: Old Klang Road (Near Petron)', 'Existing', 'A1'],
            ['KL - City Centre: Taman Desa Towards Midvalley, KL', 'Raw New', 'A2'],
            ['KL Desapark: Jalan 1/62B - Desa Park City', 'Raw New', 'A2'],
            ['KL Desapark: LDP (Exit to Desapark city)', 'Existing', 'A1'],
            ['KL Desapark: LDP (towards exit to Desa Park City)', 'Existing', 'A1'],
            ['KL - Gombak: Jalan Lingkaran Tengah 2 heading to KL East Mall, Gombak KL', 'Raw New', 'A2'],
            ['KL - Gombak: Jalan Lintang (Exit to Lebuh Utama Sri Gombak)', 'Existing', 'A1'],
        ];

        foreach ($places as $place) {
            [$siteName, $status, $type] = $place;
            $details = $place[3] ?? [];

            AdvertisingProduct::updateOrCreate(
                [
                    'site_name' => $siteName,
                    'product_type' => 'Temp Board',
                ],
                [
                    'status' => $status,
                    'type' => $type,
                    ...$details,
                ]
            );
        }
    }
}
