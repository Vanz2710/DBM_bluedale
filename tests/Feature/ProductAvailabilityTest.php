<?php

namespace Tests\Feature;

use App\Models\AdvertisingProductBooking;
use App\Models\AdvertisingProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_date_range_is_saved_for_each_overlapping_month(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/v1/product-availability', [
            'site_name' => 'KL - Bangsar: Jalan Maarof',
            'status' => 'Existing',
            'type' => 'reject',
            'product_type' => 'Temp Board',
            'company_name' => 'Bangkok Thai Massage',
            'year' => 2026,
            'month' => 4,
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.bookings.0.month', 4);
        $response->assertJsonPath('data.bookings.1.month', 5);
        $response->assertJsonPath('data.bookings.2.month', 6);

        $this->assertSame(3, AdvertisingProductBooking::count());

        foreach ([4, 5, 6] as $month) {
            $this->assertDatabaseHas('advertising_product_bookings', [
                'company_name' => 'Bangkok Thai Massage',
                'year' => 2026,
                'month' => $month,
                'start_date' => '2026-04-01',
                'end_date' => '2026-06-30',
            ]);
        }
    }

    public function test_selected_products_can_generate_proposal_pdf(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $product = AdvertisingProduct::create([
            'site_name' => 'KL - Bukit Jalil: Persiaran Puncak Jalil 1, Bukit Jalil',
            'status' => 'Existing',
            'type' => 'A1',
            'product_type' => 'Temp Board',
            'site_code' => 'TB-WPKL-0001-DBKL-A',
            'size' => '15 feet x 10 feet',
        ]);

        $response = $this->postJson('/api/v1/product-availability/proposal', [
            'product_ids' => [$product->id],
            'client_name' => 'ACC Evesuite Medical Centre',
            'attention' => 'Attn: Amira',
            'duration' => 1,
        ]);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }
}
