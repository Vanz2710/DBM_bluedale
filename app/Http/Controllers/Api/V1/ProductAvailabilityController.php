<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdvertisingProduct;
use App\Models\AdvertisingProductBooking;
use App\Support\SimplePdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
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
        ]);

        $product->update($validated);

        return response()->json(['status' => 'success', 'data' => $product->fresh('bookings')]);
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
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'exists:advertising_products,id'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'attention' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'integer', 'min:1', 'max:36'],
        ]);

        $products = AdvertisingProduct::whereIn('id', $validated['product_ids'])
            ->orderBy('product_type')
            ->orderBy('site_name')
            ->get();

        $pdf = $this->buildProposalPdf(
            $products,
            $validated['client_name'] ?? '',
            $validated['attention'] ?? '',
            (int) ($validated['duration'] ?? 1)
        );

        $filename = 'proposal-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
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

    private function buildProposalPdf($products, string $clientName, string $attention, int $duration): string
    {
        $pdf = new SimplePdf();
        $pdf->lineWidth(1);

        $pdf->fillColor(0.15);
        $pdf->rect(42, 762, 510, 46, true);
        $pdf->fillColor(1);
        $pdf->text(58, 786, 'Bluedale Integrated (M) Sdn. Bhd.', 16, true);
        $pdf->text(58, 772, 'No.31-1, Block F2, Level 2, Jalan PJU 1/42A, Dataran Prima, 47301 Petaling Jaya', 8);
        $pdf->fillColor(0);
        $pdf->text(430, 744, 'Tel: +(6)03 - 7886 9219', 9, true);
        $pdf->text(430, 732, 'Email: enquiry@bluedale.com.my', 9, true);

        $proposalDate = now()->format('jS F Y');
        $reference = 'Ref: AEMC/PROPOSAL/' . now()->format('m-y/His');
        $mainProduct = $products->pluck('product_type')->unique()->count() === 1
            ? strtoupper($products->first()->product_type)
            : 'ADVERTISING PRODUCTS';

        $pdf->text(42, 716, $proposalDate, 10, true);
        $pdf->text(390, 716, $reference, 10);
        $pdf->text(42, 690, $clientName ?: 'Client Name: _______________________________', 10, true);
        $pdf->text(42, 676, $attention ?: 'Attn: _______________________________', 10, true);
        $pdf->text(42, 652, "RE: PROPOSAL PACKAGE FOR {$mainProduct} ({$duration} MONTH" . ($duration > 1 ? 'S' : '') . ')', 11, true);
        $pdf->line(42, 647, 552, 647);
        $pdf->text(42, 632, 'Referring to the subject matter, we would like to offer your esteemed organization the following site as below:', 9);

        $headers = ['NO', 'LOCATIONS', 'QUANTITY', 'PRICE', 'CONFIRMED (/)'];
        $widths = [36, 190, 100, 100, 84];
        $x = 42;
        $y = 594;
        $rowHeight = 44;

        $pdf->rect($x, $y, array_sum($widths), 30);
        $cellX = $x;
        foreach ($headers as $index => $header) {
            $pdf->text($cellX + 8, $y + 11, $header, 9, true);
            if ($index > 0) {
                $pdf->line($cellX, $y, $cellX, $y + 30);
            }
            $cellX += $widths[$index];
        }

        $y -= $rowHeight;
        $grandTotal = 0;
        foreach ($products as $index => $product) {
            $price = $this->proposalPrice($product, $duration);
            if ($price !== null) {
                $grandTotal += $price;
            }

            $pdf->rect($x, $y, array_sum($widths), $rowHeight);
            $cellX = $x;
            foreach ($widths as $cellWidth) {
                if ($cellX !== $x) {
                    $pdf->line($cellX, $y, $cellX, $y + $rowHeight);
                }
                $cellX += $cellWidth;
            }

            $pdf->text($x + 13, $y + 18, (string) ($index + 1), 10, true);
            $pdf->wrappedText($x + 44, $y + 28, $this->proposalLocation($product), 170, 8, true, 10);
            $pdf->wrappedText($x + 236, $y + 27, $this->proposalQuantity($product, $duration), 82, 8, true, 10);
            $pdf->text($x + 334, $y + 18, $price === null ? 'TBC' : 'RM ' . number_format($price), 10, true);

            $y -= $rowHeight;
        }

        $pdf->rect($x, $y, array_sum($widths), 42);
        $pdf->line($x + 326, $y, $x + 326, $y + 42);
        $pdf->text($x + 8, $y + 24, 'GRAND TOTAL - ' . $products->count() . ' LOCATION(S) x ' . $duration . ' MONTH(S)', 13, true);
        $pdf->text($x + 8, $y + 10, '(All cost-inclusive production, installation, authority arrangement, dismantle & SST)', 8, true);
        $pdf->text($x + 346, $y + 16, $grandTotal > 0 ? 'RM ' . number_format($grandTotal) . ' + SST' : 'TBC', 14, true);

        $termsY = $y - 22;
        $pdf->text(42, $termsY, 'Term & Condition:', 10, true);
        $terms = [
            'Payment Term: 100% Pre-Payment from the Investment Cost 1 week upon confirmation.',
            'Contract duration: ' . $duration . ' month(s).',
            'Material must be submitted 14 working days before the in-charge date.',
            'The site is subject to availability, authority arrangement, and safety regulations.',
            'Any delay in artwork will not affect the in-charge date.',
            'There is no cancellation upon proposal confirmation. Any cancellation will be charged in full.',
            'Other Terms & Conditions apply.',
        ];

        $termsY -= 16;
        foreach ($terms as $term) {
            $pdf->text(58, $termsY, '- ' . $term, 8);
            $termsY -= 13;
        }

        $signatureY = 84;
        $pdf->text(42, $signatureY + 46, 'Thank you,', 9);
        $pdf->text(42, $signatureY + 32, 'For and on behalf of Bluedale Integrated (M) Sdn Bhd', 9, true);
        $pdf->line(42, $signatureY, 224, $signatureY);
        $pdf->text(42, $signatureY - 14, 'Authorized Signature', 9);

        $pdf->text(350, $signatureY + 46, 'I, agree and accept the above,', 9);
        $pdf->line(350, $signatureY, 532, $signatureY);
        $pdf->text(350, $signatureY - 14, 'Name:', 9);
        $pdf->text(350, $signatureY - 28, 'Designation:', 9);
        $pdf->text(350, $signatureY - 42, '(Co. Rubber Stamp)', 9);

        return $pdf->output();
    }

    private function proposalLocation(AdvertisingProduct $product): string
    {
        $parts = explode(': ', $product->site_name, 2);

        return $parts[1] ?? $product->site_name;
    }

    private function proposalQuantity(AdvertisingProduct $product, int $duration): string
    {
        if ($product->product_type === 'Lamp Post Bunting') {
            return '10 pcs (' . $duration . ' month' . ($duration > 1 ? 's' : '') . ')';
        }

        return '1 site (' . $duration . ' month' . ($duration > 1 ? 's' : '') . ')';
    }

    private function proposalPrice(AdvertisingProduct $product, int $duration): ?int
    {
        return match ($product->product_type) {
            'Lamp Post Bunting' => 1200 * $duration,
            'Temp Board' => 4800 * $duration,
            'Billboard' => 4800 * $duration,
            default => null,
        };
    }
}
