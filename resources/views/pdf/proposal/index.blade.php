<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Proposal</title>
<style>
    {{-- IMPORTANT: do NOT set `size` here. dompdf lets a CSS `@page { size }`
         declaration OVERRIDE the programmatic ->setPaper() call, and named @page
         rules (@page sheet / `page: sheet`) are ignored entirely. The controller
         renders the cover and the site sheets as SEPARATE PDFs with the correct
         ->setPaper('A4','portrait'|'landscape') each, then merges them with FPDI.
         Setting size here forces every page to one orientation and breaks the
         landscape site sheets. Only the page margin belongs here. --}}
    @page { margin: 28px; }
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; padding: 0; color: #000; font-size: 10px; line-height: 1.35; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
</style>
</head>
<body>

@if($include_cover)
@include('pdf.proposal.cover', [
    'company'        => $company,
    'signatory'      => $signatory,
    'date'           => $date,
    'reference'      => $reference,
    'client_name'    => $client_name,
    'attention'      => $attention,
    'attention_phone'=> $attention_phone,
    're_line'        => $re_line,
    'normal_price'   => $normal_price,
    'normal_price_unit' => $normal_price_unit,
    'rows'           => $rows,
    'price_label'    => $price_label,
    'quantity_size'  => $quantity_size,
    'grand_total_pcs'=> $grand_total_pcs,
    'duration'       => $duration,
    'grand_total'    => $grand_total,
    'sst_total'      => $sst_total,
    'terms'          => $terms,
])
@endif

@foreach ($products as $product)
    @if(($sheet_orientation ?? 'landscape') === 'portrait')
        @include('pdf.proposal.site_sheet_portrait', [
            'product'       => $product,
            'logo_data'     => $logo_data ?? null,
            'company'       => $company,
            'signatory'     => $signatory,
            'portrait_meta' => $portrait_meta ?? [],
        ])
    @else
        @include('pdf.proposal.site_sheet', [
            'product'   => $product,
            'logo_data' => $logo_data ?? null,
        ])
    @endif
@endforeach

</body>
</html>
