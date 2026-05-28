<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdvertisingProduct;
use App\Models\AdvertisingProductBooking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductAvailabilityController extends Controller
{
    private const PRODUCTS = ['Billboard', 'Temp Board', 'Lamp Post Bunting'];
    private const STATUSES = ['Existing', 'Raw New'];
    private const TYPES = ['A1', 'A2', 'ongoing', 'reject'];

    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $query = AdvertisingProduct::with(['bookings' => function ($q) use ($year) {
            $q->where('year', $year)->orderBy('month');
        }])->orderBy('product_type')->orderBy('site_name');

        if ($product = $request->input('product_type')) {
            $query->where('product_type', $product);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('site_name', 'like', "%{$search}%")
                    ->orWhereHas('bookings', function ($booking) use ($search) {
                        $booking->where('company_name', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'data' => $query->get(),
            'year' => $year,
            'products' => self::PRODUCTS,
            'statuses' => self::STATUSES,
            'types' => self::TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules($request));

        $product = AdvertisingProduct::firstOrCreate(
            [
                'site_name' => $validated['site_name'],
                'product_type' => $validated['product_type'],
            ],
            [
                'status' => $validated['status'],
                'type' => $validated['type'],
            ]
        );

        $product->update([
            'status' => $validated['status'],
            'type' => $validated['type'],
        ]);

        $bookingMonths = $this->bookingMonths($validated);

        foreach ($bookingMonths as $bookingMonth) {
            AdvertisingProductBooking::updateOrCreate(
                [
                    'advertising_product_id' => $product->id,
                    'year' => $bookingMonth['year'],
                    'month' => $bookingMonth['month'],
                ],
                [
                    'contact_id' => $validated['contact_id'] ?? null,
                    'company_name' => $validated['company_name'],
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                ]
            );
        }

        $responseYear = $bookingMonths[0]['year'];

        return response()->json([
            'status' => 'success',
            'data' => $product->fresh(['bookings' => fn ($q) => $q->where('year', $responseYear)->orderBy('month')]),
        ], 201);
    }

    public function updateProduct(Request $request, AdvertisingProduct $product)
    {
        $validated = $request->validate([
            'site_name' => ['sometimes', 'required', 'string', 'max:500'],
            'status' => ['sometimes', 'required', Rule::in(self::STATUSES)],
            'type' => ['sometimes', 'required', Rule::in(self::TYPES)],
            'product_type' => ['sometimes', 'required', Rule::in(self::PRODUCTS)],
            'site_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'size' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state_city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'coordinate' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nearest_landmarks' => ['sometimes', 'nullable', 'array'],
            'nearest_landmarks.*.category' => ['required_with:nearest_landmarks', 'string', 'max:100'],
            'nearest_landmarks.*.place' => ['nullable', 'string', 'max:255'],
            'nearest_landmarks.*.distance' => ['nullable', 'string', 'max:50'],
        ]);

        $product->update($validated);

        return response()->json(['status' => 'success', 'data' => $product->fresh('bookings')]);
    }

    public function uploadPhoto(Request $request, AdvertisingProduct $product)
    {
        $request->validate([
            'kind'  => ['required', Rule::in(['site_photo', 'site_map_photo'])],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $kind = $request->input('kind');

        // Delete old file if any
        if ($product->$kind && Storage::disk('public')->exists($product->$kind)) {
            Storage::disk('public')->delete($product->$kind);
        }

        $path = $request->file('photo')->store("advertising_products/{$product->id}", 'public');
        $product->update([$kind => $path]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'path' => $path,
                'url'  => Storage::url($path),
            ],
        ]);
    }

    public function deletePhoto(Request $request, AdvertisingProduct $product)
    {
        $request->validate([
            'kind' => ['required', Rule::in(['site_photo', 'site_map_photo'])],
        ]);

        $kind = $request->input('kind');

        if ($product->$kind && Storage::disk('public')->exists($product->$kind)) {
            Storage::disk('public')->delete($product->$kind);
        }

        $product->update([$kind => null]);

        return response()->json(['status' => 'success']);
    }

    public function updateBooking(Request $request, AdvertisingProductBooking $booking)
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', Rule::when($request->filled('start_date'), ['after_or_equal:start_date'])],
            'year' => ['sometimes', 'required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
        ]);

        $booking->update($validated);

        return response()->json(['status' => 'success', 'data' => $booking->fresh()]);
    }

    public function destroyBooking(AdvertisingProductBooking $booking)
    {
        $booking->delete();

        return response()->json(['status' => 'success']);
    }

    public function proposal(Request $request)
    {
        $validated = $request->validate([
            'product_ids'      => ['required', 'array', 'min:1'],
            'product_ids.*'    => ['integer', 'exists:advertising_products,id'],
            'client_name'      => ['nullable', 'string', 'max:255'],
            'attention'        => ['nullable', 'string', 'max:255'],
            'attention_phone'  => ['nullable', 'string', 'max:50'],
            'reference'        => ['nullable', 'string', 'max:100'],
            'duration'         => ['nullable', 'integer', 'min:1', 'max:36'],
            'duration_label'   => ['nullable', 'string', 'max:100'],
            'normal_price'     => ['nullable', 'numeric', 'min:0'],
            'price_per_unit'   => ['nullable', 'numeric', 'min:0'],
            'quantity_size'    => ['nullable', 'string', 'max:100'],
            'sst_rate'         => ['nullable', 'numeric', 'min:0', 'max:1'],
            're_line'          => ['nullable', 'string', 'max:500'],
            'promo_until'      => ['nullable', 'string', 'max:50'],
            'include_site_sheets' => ['nullable', 'boolean'],
            'rows'             => ['nullable', 'array'],
            'rows.*.product_id'      => ['required_with:rows', 'integer'],
            'rows.*.location'        => ['required_with:rows', 'string', 'max:255'],
            'rows.*.quantity'        => ['required_with:rows', 'string', 'max:100'],
            'rows.*.quantity_detail' => ['nullable', 'string', 'max:255'],
            'rows.*.price'           => ['required_with:rows', 'numeric', 'min:0'],
            'rows.*.sst'             => ['nullable', 'numeric', 'min:0'],
            'rows.*.confirmed'       => ['nullable', 'string', 'max:10'],
        ]);

        $products = AdvertisingProduct::whereIn('id', $validated['product_ids'])
            ->orderBy('product_type')
            ->orderBy('site_name')
            ->get();

        $duration       = (int) ($validated['duration'] ?? 1);
        $durationLabel  = $validated['duration_label']
            ?? ($duration . ' MONTH' . ($duration > 1 ? 'S' : ''));
        $mainProductType = $products->pluck('product_type')->unique()->count() === 1
            ? $products->first()->product_type
            : 'Advertising Products';

        $reLineDefault = strtoupper("PROPOSAL PACKAGE FOR {$mainProductType} (Without Frame- {$durationLabel})");
        $reLine        = $validated['re_line'] ?? $reLineDefault;

        // Pricing
        $pricingConfig = config('proposal.pricing.' . $mainProductType, [
            'normal_price'   => 0,
            'promo_price'    => 0,
            'per_unit_label' => 'site',
            'sst_rate'       => 0.08,
        ]);
        $normalPrice   = $validated['normal_price']   ?? $pricingConfig['normal_price'];
        $pricePerUnit  = $validated['price_per_unit'] ?? $pricingConfig['promo_price'];
        $sstRate       = $validated['sst_rate']       ?? $pricingConfig['sst_rate'];
        $perUnitLabel  = $pricingConfig['per_unit_label'];

        // Rows — either provided explicitly, or derived from products
        $rows = $validated['rows']
            ?? $this->defaultRows($products, $duration, $pricePerUnit, $sstRate);

        $grandTotal      = array_sum(array_column($rows, 'price'));
        $sstTotal        = array_sum(array_map(fn ($r) => $r['sst'] ?? 0, $rows));
        $grandTotalPcs   = $this->formatGrandTotalPcs($rows, $perUnitLabel);

        // Terms — interpolate :duration and :promo_until
        $promoUntil = $validated['promo_until'] ?? now()->addMonths(2)->format('j/n/Y');
        $terms = array_map(
            fn ($t) => strtr($t, [':duration' => $durationLabel, ':promo_until' => $promoUntil]),
            config('proposal.terms', [])
        );

        $includeSheets = (bool) ($validated['include_site_sheets'] ?? true);

        $data = [
            'company'         => config('proposal.company'),
            'signatory'       => config('proposal.signatory'),
            'date'            => now()->format('jS F Y'),
            'reference'       => $validated['reference'] ?? ('AEMC/PROPOSAL/' . now()->format('m-y/His')),
            'client_name'     => $validated['client_name'] ?? '',
            'attention'       => $validated['attention'] ?? '',
            'attention_phone' => $validated['attention_phone'] ?? '',
            're_line'         => $reLine,
            'normal_price'    => $normalPrice,
            'normal_price_unit' => $perUnitLabel,
            'rows'            => $rows,
            'price_label'     => $pricePerUnit ? ('RM' . number_format($pricePerUnit, 0) . '/' . $perUnitLabel) : '',
            'quantity_size'   => $validated['quantity_size'] ?? '',
            'grand_total_pcs' => $grandTotalPcs,
            'duration'        => $durationLabel,
            'grand_total'     => $grandTotal,
            'sst_total'       => $sstTotal,
            'terms'           => $terms,
            'products'        => $includeSheets ? $this->preparedSiteSheets($products) : [],
        ];

        $pdf = Pdf::loadView('pdf.proposal.index', $data)
            ->setPaper('A4', 'portrait');

        $filename = 'proposal-' . now()->format('Ymd-His') . '.pdf';

        return $pdf->download($filename);
    }

    private function defaultRows($products, int $duration, float $pricePerUnit, float $sstRate): array
    {
        return $products->map(function ($product) use ($duration, $pricePerUnit, $sstRate) {
            [$units, $quantityDetail] = $this->unitsFor($product, $duration);
            $rowPrice = $units * $pricePerUnit;

            return [
                'product_id'      => $product->id,
                'location'        => $this->shortLocation($product),
                'quantity'        => $units . ' ' . ($product->product_type === 'Lamp Post Bunting' ? 'pcs' : 'site'),
                'quantity_detail' => $quantityDetail,
                'price'           => $rowPrice,
                'sst'             => round($rowPrice * $sstRate, 2),
                'confirmed'       => '/',
            ];
        })->all();
    }

    private function unitsFor(AdvertisingProduct $product, int $duration): array
    {
        if ($product->product_type === 'Lamp Post Bunting') {
            return [10, '2 sided- 5 poles'];
        }

        return [1, $duration . ' month' . ($duration > 1 ? 's' : '')];
    }

    private function formatGrandTotalPcs(array $rows, string $unitLabel): string
    {
        $totalUnits = 0;
        foreach ($rows as $row) {
            preg_match('/(\d+)/', (string) $row['quantity'], $matches);
            $totalUnits += (int) ($matches[1] ?? 0);
        }

        return $totalUnits . ' ' . ($unitLabel === 'pcs' ? 'PCS' : 'SITE(S)');
    }

    private function shortLocation(AdvertisingProduct $product): string
    {
        $parts = explode(': ', $product->site_name, 2);
        return $parts[1] ?? $product->site_name;
    }

    private function preparedSiteSheets($products): array
    {
        return $products->map(function ($product) {
            return [
                'id'                 => $product->id,
                'product_type'       => $product->product_type,
                'product_type_label' => $this->productTypeLabel($product->product_type),
                'site_code'          => $product->site_code,
                'size'               => $product->size,
                'location'           => $this->shortLocation($product),
                'state_city'         => $product->state_city,
                'coordinate'         => $product->coordinate,
                'landmarks'          => $product->nearest_landmarks ?: [],
                'site_photo_data'    => $this->photoDataUri($product->site_photo),
                'site_map_photo_data'=> $this->photoDataUri($product->site_map_photo),
            ];
        })->all();
    }

    private function productTypeLabel(string $type): string
    {
        return match ($type) {
            'Lamp Post Bunting' => 'Lamp Post Bunting',
            'Temp Board'        => 'Mini Billboard (Without Light)',
            'Billboard'         => 'Billboard',
            default             => $type,
        };
    }

    private function photoDataUri(?string $path): ?string
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return null;
        }

        $content = Storage::disk('public')->get($path);
        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function rules(Request $request): array
    {
        return [
            'site_name' => ['required', 'string', 'max:500'],
            'status' => ['required', Rule::in(self::STATUSES)],
            'type' => ['required', Rule::in(self::TYPES)],
            'product_type' => ['required', Rule::in(self::PRODUCTS)],
            'company_name' => ['required', 'string', 'max:255'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', Rule::when($request->filled('start_date'), ['after_or_equal:start_date'])],
        ];
    }

    private function bookingMonths(array $validated): array
    {
        if (empty($validated['start_date']) || empty($validated['end_date'])) {
            return [[
                'year' => (int) $validated['year'],
                'month' => (int) $validated['month'],
            ]];
        }

        $months = [];
        $cursor = CarbonImmutable::parse($validated['start_date'])->startOfMonth();
        $end = CarbonImmutable::parse($validated['end_date'])->startOfMonth();

        while ($cursor->lessThanOrEqualTo($end)) {
            $months[] = [
                'year' => $cursor->year,
                'month' => $cursor->month,
            ];

            $cursor = $cursor->addMonth();
        }

        return $months;
    }
}
