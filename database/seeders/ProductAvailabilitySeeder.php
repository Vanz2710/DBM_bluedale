<?php

namespace Database\Seeders;

use App\Models\AdvertisingProduct;
use App\Models\AdvertisingProductBooking;
use Illuminate\Database\Seeder;

class ProductAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        // ─── Billboards ────────────────────────────────────────────────────────
        $billboards = [
            [
                'site_name'   => 'Billboard: Persiaran Surian, Mutiara Damansara (Facing Towards LDP)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-PJ-001',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Petaling Jaya, Selangor',
                'coordinate'  => '3.1548° N, 101.6123° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'The Curve'],
                    ['category' => 'Shopping Mall', 'place' => 'Ikea Damansara'],
                    ['category' => 'Highway', 'place' => 'LDP (Damansara-Puchong Expressway)'],
                ],
                'bookings' => [
                    ['company_name' => 'Aeon Co. (M) Bhd', 'months' => [1, 2, 3]],
                    ['company_name' => 'Sunway Group', 'months' => [5, 6]],
                ],
            ],
            [
                'site_name'   => 'Billboard: Jalan Bukit Bintang, KL (Facing Bukit Bintang Intersection)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-KL-002',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1478° N, 101.7121° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Pavilion KL'],
                    ['category' => 'Hotel', 'place' => 'JW Marriott Kuala Lumpur'],
                    ['category' => 'Landmark', 'place' => 'Bintang Walk'],
                ],
                'bookings' => [
                    ['company_name' => 'Bonia Corporation Bhd', 'months' => [3, 4, 5, 6]],
                ],
            ],
            [
                'site_name'   => 'Billboard: Lebuhraya Damansara-Puchong (LDP), Puchong Utama (Outbound)',
                'status'      => 'Existing',
                'type'        => 'A2',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-PU-003',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Puchong, Selangor',
                'coordinate'  => '3.0395° N, 101.6221° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'IOI Mall Puchong'],
                    ['category' => 'Highway', 'place' => 'LDP (Lebuhraya Damansara-Puchong)'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Billboard: Jalan Ipoh, KL (Facing South, Near SOGO)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-KL-004',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1620° N, 101.6983° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'SOGO KL'],
                    ['category' => 'Hospital', 'place' => 'Hospital Kuala Lumpur'],
                ],
                'bookings' => [
                    ['company_name' => 'MR DIY Group Bhd', 'months' => [4, 5, 6, 7, 8]],
                ],
            ],
            [
                'site_name'   => 'Billboard: Persiaran Mahogani, Kota Damansara (Facing NPE)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-KD-005',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Kota Damansara, Selangor',
                'coordinate'  => '3.1719° N, 101.5876° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Encorp Strand Mall'],
                    ['category' => 'Highway', 'place' => 'NPE (New Pantai Expressway)'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Billboard: Jalan Cheras, KL (Northbound, Near Leisure Mall)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-CH-006',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Cheras, Kuala Lumpur',
                'coordinate'  => '3.0988° N, 101.7398° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Leisure Mall Cheras'],
                    ['category' => 'Hospital', 'place' => 'Hospital Cheras'],
                ],
                'bookings' => [
                    ['company_name' => 'Petronas Dagangan Bhd', 'months' => [1, 2]],
                    ['company_name' => 'TM Berhad', 'months' => [6, 7, 8, 9]],
                ],
            ],
            [
                'site_name'   => 'Billboard: Persiaran Puchong Jaya Selatan, Puchong (Facing IOI Boulevard)',
                'status'      => 'Raw New',
                'type'        => 'A2',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-PJ2-007',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Puchong, Selangor',
                'coordinate'  => '3.0253° N, 101.6199° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'IOI Boulevard Puchong'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Billboard: Federal Highway, Shah Alam (Southbound, Near Setia City Mall)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Billboard',
                'site_code'   => 'BD-SA-008',
                'size'        => '20ft x 40ft',
                'state_city'  => 'Shah Alam, Selangor',
                'coordinate'  => '3.0733° N, 101.5350° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Setia City Mall'],
                    ['category' => 'Highway', 'place' => 'Federal Highway'],
                    ['category' => 'Hospital', 'place' => 'Columbia Asia Hospital Shah Alam'],
                ],
                'bookings' => [
                    ['company_name' => 'Majid Al Futtaim Hypermarkets', 'months' => [2, 3, 4]],
                ],
            ],
        ];

        // ─── Temp Boards ───────────────────────────────────────────────────────
        $tempBoards = [
            [
                'site_name'   => 'Temp Board: Jalan Klang Lama, KL (Approaching Mid Valley)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-KL-001',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1155° N, 101.6710° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Mid Valley Megamall'],
                    ['category' => 'Shopping Mall', 'place' => 'The Gardens Mall'],
                ],
                'bookings' => [
                    ['company_name' => 'CIMB Bank Bhd', 'months' => [1, 2, 3, 4, 5]],
                ],
            ],
            [
                'site_name'   => 'Temp Board: Jalan Sultan Ismail, KL (Near Suria KLCC)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-KL-002',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1579° N, 101.7131° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Suria KLCC'],
                    ['category' => 'Landmark', 'place' => 'Petronas Twin Towers'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Temp Board: Persiaran Barat, Petaling Jaya (Section 51A)',
                'status'      => 'Existing',
                'type'        => 'A2',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-PJ-003',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Petaling Jaya, Selangor',
                'coordinate'  => '3.1050° N, 101.6366° E',
                'nearest_landmarks' => [
                    ['category' => 'Hospital', 'place' => 'Ara Damansara Medical Centre'],
                    ['category' => 'Shopping Mall', 'place' => '1 Utama Shopping Centre'],
                ],
                'bookings' => [
                    ['company_name' => 'Maybank Bhd', 'months' => [4, 5]],
                ],
            ],
            [
                'site_name'   => 'Temp Board: Jalan Ampang, KL (AKLEH On-Ramp)',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-KL-004',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1643° N, 101.7283° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Great Eastern Mall'],
                    ['category' => 'Highway', 'place' => 'AKLEH (Ampang-KL Elevated Highway)'],
                ],
                'bookings' => [
                    ['company_name' => 'Genting Malaysia Bhd', 'months' => [3, 4, 5, 6]],
                    ['company_name' => 'Axiata Group Bhd', 'months' => [8, 9, 10]],
                ],
            ],
            [
                'site_name'   => 'Temp Board: Jalan Subang, Subang Jaya (SS15, Near Subang Parade)',
                'status'      => 'Existing',
                'type'        => 'A2',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-SJ-005',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Subang Jaya, Selangor',
                'coordinate'  => '3.0776° N, 101.5829° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Subang Parade'],
                    ['category' => 'Hospital', 'place' => 'Sunway Medical Centre'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Temp Board: Persiaran Multimedia, Cyberjaya (Near Tamarind Square)',
                'status'      => 'Raw New',
                'type'        => 'A2',
                'product_type'=> 'Temp Board',
                'site_code'   => 'TB-CJ-006',
                'size'        => '10ft x 20ft',
                'state_city'  => 'Cyberjaya, Selangor',
                'coordinate'  => '2.9213° N, 101.6559° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Tamarind Square'],
                    ['category' => 'Exhibition Centre', 'place' => 'Cyberjaya Exhibition & Convention Centre'],
                ],
                'bookings' => [],
            ],
        ];

        // ─── Lamp Post Buntings ────────────────────────────────────────────────
        $lampPosts = [
            [
                'site_name'   => 'Lamp Post Bunting: Jalan PJU 1/42A, Dataran Prima, Petaling Jaya',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-PJ-001',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Petaling Jaya, Selangor',
                'coordinate'  => '3.1245° N, 101.6106° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Citta Mall'],
                    ['category' => 'Hospital', 'place' => 'Columbia Asia Hospital Petaling Jaya'],
                ],
                'bookings' => [
                    ['company_name' => 'Bluedale Integrated (M) Sdn. Bhd.', 'months' => [1, 2, 3, 4, 5, 6]],
                ],
            ],
            [
                'site_name'   => 'Lamp Post Bunting: Persiaran Mahogani, Kota Damansara',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-KD-002',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Kota Damansara, Selangor',
                'coordinate'  => '3.1710° N, 101.5893° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Encorp Strand Mall'],
                    ['category' => 'Hospital', 'place' => 'Thomson Hospital Kota Damansara'],
                ],
                'bookings' => [
                    ['company_name' => 'Digi Telecommunications Sdn Bhd', 'months' => [3, 4]],
                ],
            ],
            [
                'site_name'   => 'Lamp Post Bunting: Jalan Semarak, KL (Near PWTC)',
                'status'      => 'Existing',
                'type'        => 'A2',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-KL-003',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Kuala Lumpur',
                'coordinate'  => '3.1782° N, 101.7044° E',
                'nearest_landmarks' => [
                    ['category' => 'Exhibition Centre', 'place' => 'PWTC (Putra World Trade Centre)'],
                    ['category' => 'Shopping Mall', 'place' => 'Sogo KL'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Lamp Post Bunting: Persiaran Puchong Jaya, Puchong',
                'status'      => 'Existing',
                'type'        => 'A1',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-PU-004',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Puchong, Selangor',
                'coordinate'  => '3.0261° N, 101.6210° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'IOI Mall Puchong'],
                    ['category' => 'Hospital', 'place' => 'Puchong Specialist Hospital'],
                ],
                'bookings' => [
                    ['company_name' => 'Nestlé Products Sdn Bhd', 'months' => [5, 6, 7]],
                ],
            ],
            [
                'site_name'   => 'Lamp Post Bunting: Jalan USJ 10, Subang Jaya (Near Giant USJ)',
                'status'      => 'Existing',
                'type'        => 'A2',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-SJ-005',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Subang Jaya, Selangor',
                'coordinate'  => '3.0461° N, 101.5742° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Giant USJ'],
                    ['category' => 'Hospital', 'place' => 'KPJ Puteri Specialist Hospital'],
                ],
                'bookings' => [],
            ],
            [
                'site_name'   => 'Lamp Post Bunting: Persiaran Bestari, Cyberjaya',
                'status'      => 'Raw New',
                'type'        => 'A2',
                'product_type'=> 'Lamp Post Bunting',
                'site_code'   => 'LP-CJ-006',
                'size'        => '2ft x 4ft (10 pcs)',
                'state_city'  => 'Cyberjaya, Selangor',
                'coordinate'  => '2.9228° N, 101.6538° E',
                'nearest_landmarks' => [
                    ['category' => 'Shopping Mall', 'place' => 'Tamarind Square'],
                ],
                'bookings' => [],
            ],
        ];

        $allProducts = array_merge($billboards, $tempBoards, $lampPosts);

        foreach ($allProducts as $productData) {
            $bookings = $productData['bookings'];
            unset($productData['bookings']);

            $product = AdvertisingProduct::create($productData);

            foreach ($bookings as $booking) {
                foreach ($booking['months'] as $month) {
                    $start = \Carbon\Carbon::create($year, $month, 1);
                    $end   = $start->copy()->endOfMonth();

                    AdvertisingProductBooking::create([
                        'advertising_product_id' => $product->id,
                        'contact_id'             => null,
                        'company_name'           => $booking['company_name'],
                        'year'                   => $year,
                        'month'                  => $month,
                        'start_date'             => $start->format('Y-m-d'),
                        'end_date'               => $end->format('Y-m-d'),
                    ]);
                }
            }
        }
    }
}
