<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Proposal</title>
<style>
    @page { margin: 28px 28px 28px 28px; }
    * { box-sizing: border-box; font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; padding: 0; color: #000; font-size: 10px; line-height: 1.35; }
    .page { page-break-after: always; }
    .page:last-child { page-break-after: auto; }
</style>
</head>
<body>

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

@foreach ($products as $product)
    @include('pdf.proposal.site_sheet', [
        'product' => $product,
    ])
@endforeach

</body>
</html>
