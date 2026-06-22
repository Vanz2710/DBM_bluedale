<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdvertisingProduct;
use App\Models\AdvertisingProductBooking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use setasign\Fpdi\Fpdi;

class SiteAvailabilityController extends Controller
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
            'site_code'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'size'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'illumination'=> ['sometimes', 'nullable', 'string', 'max:100'],
            'facing'      => ['sometimes', 'nullable', 'string', 'max:100'],
            'state_city'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'coordinate'  => ['sometimes', 'nullable', 'string', 'max:255'],
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

    public function createProduct(Request $request)
    {
        $validated = $request->validate([
            'site_name'        => ['required', 'string', 'max:500'],
            'product_type'     => ['required', Rule::in(self::PRODUCTS)],
            'status'           => ['required', Rule::in(self::STATUSES)],
            'type'             => ['required', Rule::in(self::TYPES)],
            'illumination'     => ['nullable', 'string', 'max:100'],
            'facing'           => ['nullable', 'string', 'max:100'],
            'state_city'       => ['nullable', 'string', 'max:255'],
            'coordinate'       => ['nullable', 'string', 'max:255'],
            'nearest_landmarks'              => ['nullable', 'array'],
            'nearest_landmarks.*.category'   => ['required_with:nearest_landmarks', 'string', 'max:100'],
            'nearest_landmarks.*.place'      => ['nullable', 'string', 'max:255'],
            'is_pending'       => ['nullable', 'boolean'],
        ]);

        $product = AdvertisingProduct::create([
            'site_name'         => $validated['site_name'],
            'product_type'      => $validated['product_type'],
            'status'            => $validated['status'],
            'type'              => $validated['type'],
            'illumination'      => $validated['illumination'] ?? null,
            'facing'            => $validated['facing'] ?? null,
            'state_city'        => $validated['state_city'] ?? null,
            'coordinate'        => $validated['coordinate'] ?? null,
            'nearest_landmarks' => $validated['nearest_landmarks'] ?? [],
            'is_pending'        => (bool) ($validated['is_pending'] ?? false),
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => $product->fresh('bookings'),
        ], 201);
    }

    public function confirmProduct(AdvertisingProduct $product)
    {
        $product->update(['is_pending' => false]);

        return response()->json([
            'status' => 'success',
            'data'   => $product->fresh('bookings'),
        ]);
    }

    public function discardProduct(AdvertisingProduct $product)
    {
        abort_if(! $product->is_pending, 422, 'Only pending products can be discarded.');

        foreach (['site_photo', 'site_map_photo'] as $kind) {
            if ($product->$kind && Storage::disk('public')->exists($product->$kind)) {
                Storage::disk('public')->delete($product->$kind);
            }
        }

        $product->delete();

        return response()->json(['status' => 'success']);
    }

    public function resolveMapsUrl(Request $request)
    {
        $request->validate(['url' => ['required', 'string', 'max:2048']]);

        $url = $request->input('url');

        $finalUrl = $url;
        $body     = '';

        try {
            $response = Http::withOptions([
                'allow_redirects' => [
                    'max'             => 15,
                    'strict'          => false,
                    'referer'         => true,
                    'track_redirects' => true,
                ],
            ])
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])
            ->get($url);

            $finalUrl = (string) ($response->effectiveUri() ?? $url);
            $body     = $response->body();
        } catch (\Exception) {
            // fall through with original URL
        }

        $patterns = [
            '/@(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/',
            '/[?&]q=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/',
            '/[?&]ll=(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/',
            '/!3d(-?\d{1,3}\.\d+)!4d(-?\d{1,3}\.\d+)/',
            '/\/search\/(-?\d{1,3}\.\d+),(-?\d{1,3}\.\d+)/',
            '/\/(-?\d{1,3}\.\d{4,}),(-?\d{1,3}\.\d{4,})/',
        ];

        // Normalise URL-encoded spaces after commas (e.g. Google redirects "lat,+lng")
        $normalise = fn (string $u): string => str_replace([',+', ',+', ', '], ',', $u);

        // 1. Try the final URL after HTTP redirects
        $normFinal = $normalise($finalUrl);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normFinal, $m)) {
                return response()->json(['lat' => $m[1], 'lng' => $m[2]]);
            }
        }

        // 2. Firebase Dynamic Links (maps.app.goo.gl) may respond with HTML containing
        //    a Google Maps URL instead of an HTTP redirect. Search the body.
        if ($body) {
            // Extract any Google Maps URLs embedded in the HTML and run patterns on them
            if (preg_match_all(
                '/https?:\/\/[^\s"\'<>\\\\]*(?:maps\.google\.com|google\.com\/maps|goo\.gl\/maps)[^\s"\'<>\\\\]*/',
                $body,
                $found,
            )) {
                foreach ($found[0] as $mapUrl) {
                    $decoded = $normalise(html_entity_decode($mapUrl));
                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $decoded, $m)) {
                            return response()->json(['lat' => $m[1], 'lng' => $m[2]]);
                        }
                    }
                }
            }

            // Also apply patterns directly to the raw body (catches !3d!4d in script/data blobs)
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $body, $m)) {
                    return response()->json(['lat' => $m[1], 'lng' => $m[2]]);
                }
            }
        }

        return response()->json(['error' => 'Could not extract coordinates from this link.'], 422);
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
            'include_site_sheets'   => ['nullable', 'boolean'],
            'include_proposal_page' => ['nullable', 'boolean'],
            'billboard_composites'  => ['nullable', 'array'],
            'billboard_composites.*'=> ['nullable', 'string'],
            'rows'             => ['nullable', 'array'],
            'rows.*.product_id'      => ['required_with:rows', 'integer'],
            'rows.*.location'        => ['required_with:rows', 'string', 'max:255'],
            'rows.*.quantity'        => ['nullable', 'string', 'max:100'],
            'rows.*.quantity_detail' => ['nullable', 'string', 'max:255'],
            'rows.*.price'           => ['required_with:rows', 'numeric', 'min:0'],
            'rows.*.sst'             => ['nullable', 'numeric', 'min:0'],
            'rows.*.confirmed'       => ['nullable', 'string', 'max:10'],
            'signatory_name'         => ['nullable', 'string', 'max:255'],
            'signatory_title'        => ['nullable', 'string', 'max:255'],
            'signatory_mobile'       => ['nullable', 'string', 'max:50'],
            'signatory_label'        => ['nullable', 'string', 'max:100'],
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
        $hasBunting = $products->contains('product_type', 'Lamp Post Bunting');

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
        $grandTotalPcs   = $this->formatGrandTotalPcs($rows);

        // Terms — interpolate :duration and :promo_until
        $promoUntil = $validated['promo_until'] ?? now()->addMonths(2)->format('j/n/Y');
        $terms = array_map(
            fn ($t) => strtr($t, [':duration' => $durationLabel, ':promo_until' => $promoUntil]),
            config('proposal.terms', [])
        );

        $includeSheets = (bool) ($validated['include_site_sheets'] ?? true);
        $includeCover  = (bool) ($validated['include_proposal_page'] ?? true);

        $defaultSignatory = config('proposal.signatory');
        $signatory = [
            'name'            => $validated['signatory_name']   ?? $defaultSignatory['name'],
            'title'           => $validated['signatory_title']  ?? $defaultSignatory['title'],
            'mobile'          => $validated['signatory_mobile'] ?? $defaultSignatory['mobile'],
            'signature_label' => $validated['signatory_label']  ?? $defaultSignatory['signature_label'],
        ];

        $data = [
            'company'         => config('proposal.company'),
            'signatory'       => $signatory,
            'logo_data'       => $this->logoDataUri(),
            'date'            => now()->format('jS F Y'),
            'reference'       => $validated['reference'] ?? ('AEMC/PROPOSAL/' . now()->format('m-y/His')),
            'client_name'     => $validated['client_name'] ?? '',
            'attention'       => $validated['attention'] ?? '',
            'attention_phone' => $validated['attention_phone'] ?? '',
            're_line'         => $reLine,
            'normal_price'    => $normalPrice,
            'normal_price_unit' => $perUnitLabel,
            'product_type'    => $mainProductType,
            'has_bunting'     => $hasBunting,
            'rows'            => $rows,
            'price_label'     => $pricePerUnit ? ('RM' . number_format($pricePerUnit, 0) . '/' . $perUnitLabel) : '',
            'quantity_size'   => $validated['quantity_size'] ?? '',
            'grand_total_pcs' => $grandTotalPcs,
            'duration'        => $durationLabel,
            'grand_total'     => $grandTotal,
            'sst_total'       => $sstTotal,
            'terms'           => $terms,
            'products'        => $includeSheets ? $this->preparedSiteSheets($products, $validated['billboard_composites'] ?? []) : [],
            'include_cover'   => $includeCover,
        ];

        $filename = 'proposal-' . now()->format('Ymd-His') . '.pdf';

        // No site sheets — portrait cover only
        if (!$includeSheets || empty($data['products'])) {
            return Pdf::loadView('pdf.proposal.index', $data)
                ->setPaper('A4', 'portrait')
                ->download($filename);
        }

        // Site sheets only — landscape only
        if (!$includeCover) {
            return Pdf::loadView('pdf.proposal.index', $data)
                ->setPaper('A4', 'landscape')
                ->download($filename);
        }

        // Both cover + sheets: generate two PDFs and merge (portrait + landscape)
        $coverBytes  = Pdf::loadView('pdf.proposal.index', array_merge($data, ['products' => [], 'include_cover' => true]))
            ->setPaper('A4', 'portrait')->output();
        $sheetsBytes = Pdf::loadView('pdf.proposal.index', array_merge($data, ['include_cover' => false]))
            ->setPaper('A4', 'landscape')->output();

        $fpdi = new Fpdi('P', 'mm', 'A4');

        $n = $fpdi->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($coverBytes));
        for ($i = 1; $i <= $n; $i++) {
            $tpl = $fpdi->importPage($i);
            $fpdi->AddPage('P', [210, 297]);
            $fpdi->useTemplate($tpl, 0, 0, 210, 297);
        }

        $n = $fpdi->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($sheetsBytes));
        for ($i = 1; $i <= $n; $i++) {
            $tpl = $fpdi->importPage($i);
            $fpdi->AddPage('L', [297, 210]);
            $fpdi->useTemplate($tpl, 0, 0, 297, 210);
        }

        return response($fpdi->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function defaultRows($products, int $duration, float $pricePerUnit, float $sstRate): array
    {
        return $products->map(function ($product) use ($duration, $pricePerUnit, $sstRate) {
            [$units, $quantityDetail] = $this->unitsFor($product, $duration);
            $rowPrice = $units * $pricePerUnit;

            return [
                'product_id'      => $product->id,
                'location'        => $this->shortLocation($product),
                'quantity'        => $product->product_type === 'Lamp Post Bunting' ? ($units . ' pcs') : '',
                'quantity_detail' => $product->product_type === 'Lamp Post Bunting' ? $quantityDetail : '',
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

    private function formatGrandTotalPcs(array $rows): string
    {
        $totalPcs  = 0;
        $siteCount = 0;
        foreach ($rows as $row) {
            $qty = (string) ($row['quantity'] ?? '');
            if ($qty !== '' && preg_match('/(\d+)/', $qty, $matches)) {
                $totalPcs += (int) $matches[1];
            } else {
                $siteCount++;
            }
        }
        if ($totalPcs > 0 && $siteCount > 0) {
            return $totalPcs . ' PCS + ' . $siteCount . ' SITE(S)';
        }
        if ($totalPcs > 0) {
            return $totalPcs . ' PCS';
        }
        return $siteCount . ' SITE(S)';
    }

    private function shortLocation(AdvertisingProduct $product): string
    {
        $parts = explode(': ', $product->site_name, 2);
        return $parts[1] ?? $product->site_name;
    }

    private function preparedSiteSheets($products, array $composites = []): array
    {
        // Pre-size both photos to the same dimensions so they render at equal height in the PDF.
        // Height matches the `height:287px` in site_sheet.blade.php (see that file's header comment
        // for the measured landscape geometry — 287px leaves a clean gap above the remark block).
        $photoW = 640;
        $photoH = 287;

        return $products->map(function ($product) use ($composites, $photoW, $photoH) {
            $composite = $composites[(string) $product->id] ?? null;
            return [
                'id'                 => $product->id,
                'product_type'       => $product->product_type,
                'product_type_label' => $this->productTypeLabel($product->product_type),
                'site_code'          => $product->site_code,
                'size'               => $product->size,
                'illumination'       => $product->illumination,
                'facing'             => $product->facing,
                'location'           => $this->shortLocation($product),
                'state_city'         => $product->state_city,
                'coordinate'         => $product->coordinate,
                'landmarks'          => $product->nearest_landmarks ?: [],
                'site_photo_data'    => $this->resizeForPdfFrame(
                    $composite ?? $this->photoDataUri($product->site_photo), $photoW, $photoH
                ),
                'site_map_photo_data'=> $this->resizeForPdfFrame(
                    $this->photoDataUri($product->site_map_photo), $photoW, $photoH
                ),
            ];
        })->all();
    }

    /**
     * Resize/crop a base64 data URI image to exact pixel dimensions for PDF embedding.
     * Uses cover-fill strategy (scale to fill, center-crop). Returns JPEG data URI.
     * Falls back to original if GD cannot decode the image.
     */
    private function resizeForPdfFrame(?string $dataUri, int $targetW, int $targetH): ?string
    {
        if (!$dataUri) return null;

        $binary = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $dataUri));
        $src = @imagecreatefromstring($binary);
        if (!$src) return $dataUri;

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $scale = max($targetW / $srcW, $targetH / $srcH);
        $fitW  = (int) round($srcW * $scale);
        $fitH  = (int) round($srcH * $scale);

        $tmp = imagecreatetruecolor($fitW, $fitH);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $fitW, $fitH, $srcW, $srcH);
        imagedestroy($src);

        $dst   = imagecreatetruecolor($targetW, $targetH);
        $cropX = (int) (($fitW - $targetW) / 2);
        $cropY = (int) (($fitH - $targetH) / 2);
        imagecopy($dst, $tmp, 0, 0, $cropX, $cropY, $targetW, $targetH);
        imagedestroy($tmp);

        ob_start();
        imagejpeg($dst, null, 90);
        imagedestroy($dst);

        return 'data:image/jpeg;base64,' . base64_encode(ob_get_clean());
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

    private function logoDataUri(): ?string
    {
        foreach (['image.png', 'bluedale-logo.png'] as $filename) {
            $path = public_path('images/' . $filename);
            if (file_exists($path)) {
                return 'data:image/png;base64,' . base64_encode(file_get_contents($path));
            }
        }
        return null;
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
