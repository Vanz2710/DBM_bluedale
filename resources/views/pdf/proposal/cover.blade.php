@php
    // has_bunting = true for bunting-only OR mixed bunting+billboard proposals
    $is_bunting = isset($has_bunting) ? $has_bunting : (isset($product_type) && $product_type === 'Lamp Post Bunting');
@endphp
{{-- Portrait cover / quote page.
     Layout rules proven empirically against dompdf 3.x at 96dpi:
       • A plain block up to ~1060px tall stays on ONE portrait page.
       • DO NOT wrap the page in a <table height:Npx> with a height:100% spacer row —
         dompdf inflates that across multiple physical pages (caused the 4-page bug).
       • To pin the signature to the page bottom, use position:absolute; bottom:0 —
         it is out of flow so it never forces an extra page. --}}
<div class="page">

    <!-- ── LETTERHEAD ─────────────────────────────────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <!-- Logo cell — white bg, right-padded to sit against the dark band -->
            <td width="100" style="background:#fff; vertical-align:middle; padding:5px 10px 5px 5px;">
                @if(!empty($logo_data))
                    <img src="{{ $logo_data }}" width="86" height="86" style="display:block;">
                @else
                    <div style="width:86px; height:86px; background:#0d4f8a; color:#fff; text-align:center;
                                font-weight:bold; font-size:30px; line-height:86px;">B</div>
                @endif
            </td>
            <!-- Company name + address strip -->
            <td style="background:#0d4f8a; color:#fff; padding:8px 12px; vertical-align:middle;">
                <div style="font-size:12.5px; font-weight:bold; letter-spacing:0.4px;">{{ $company['name'] }}
                    <span style="font-size:8px; font-weight:normal; color:#8fb8db;">&nbsp;{{ $company['reg_no'] }}</span>
                </div>
                <div style="font-size:7.5px; color:#9ec3e0; margin-top:3px; line-height:1.4;">{{ $company['address'] }}</div>
            </td>
            <!-- Contact info right column -->
            <td width="185" style="background:#0d4f8a; color:#9ec3e0; padding:8px 10px 8px 0; vertical-align:middle; text-align:right; font-size:7.5px; line-height:1.6;">
                <div style="color:#ddeaf7; font-size:7px; margin-bottom:4px;">{{ $company['group_label'] }}</div>
                <div>Tel: {{ $company['tel'] }}</div>
                <div>Email: {{ $company['email'] }}</div>
                <div>Web: {{ $company['website'] }}</div>
            </td>
        </tr>
    </table>

    <!-- ── BLUE ACCENT LINE ──────────────────────────────────────── -->
    <div style="height:3px; background:#1a5ca8; margin-bottom:9px;"></div>

    <!-- ── DATE + REFERENCE ─────────────────────────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:9px;">
        <tr>
            <td style="font-size:9px; color:#555;">
                <strong style="color:#0d4f8a;">Ref:</strong> {{ $reference }}
            </td>
            <td style="text-align:right;">
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        @if($normal_price)
                        <td style="padding-right:10px;">
                            <div style="background:#f4f8ff; border:1px solid #1a5ca8; color:#0d4f8a; padding:4px 10px; font-size:8.5px; font-weight:bold; text-align:center; white-space:nowrap;">
                                Normal Price: RM{{ number_format($normal_price) }}/{{ $normal_price_unit }}
                            </div>
                        </td>
                        @endif
                        <td>
                            <div style="font-size:9px; color:#555; white-space:nowrap;">{{ $date }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ── RECIPIENT ────────────────────────────────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px; font-size:10px;">
        <tr>
            <td>
                <div style="font-weight:bold; font-size:10.5px;">{{ $client_name ?: '_______________________________' }}</div>
                <div style="color:#444; font-size:9.5px; margin-top:1px;">
                    Attn: {{ $attention ?: '_________________________' }}@if($attention_phone) &nbsp;({{ $attention_phone }})@endif
                </div>
            </td>
        </tr>
    </table>

    <!-- ── RE LINE ──────────────────────────────────────────────── -->
    <div style="font-size:10px; font-weight:bold; text-decoration:underline; color:#0d4f8a; margin-bottom:4px;">RE: {{ $re_line }}</div>
    <div style="font-size:9.5px; color:#333; margin-bottom:10px;">
        We are pleased to offer your esteemed organization the following advertising site(s):
    </div>

    <!-- ── PRICING TABLE ────────────────────────────────────────── -->
    <table width="100%" cellpadding="5" cellspacing="0" border="1"
           style="border-collapse:collapse; font-size:9px; border-color:#c0c8d8;">
        <tr style="background:#0d4f8a; color:#fff; font-weight:bold; text-align:center;">
            <td width="26" style="border-color:#1a5ca8;">NO</td>
            <td width="{{ $is_bunting ? '38%' : '52%' }}" style="border-color:#1a5ca8; text-align:left; padding-left:7px;">LOCATION</td>
            @if($is_bunting)
            <td width="22%" style="border-color:#1a5ca8;">
                QUANTITY @if($quantity_size)<br><span style="font-size:7px; font-weight:normal;">({{ $quantity_size }})</span>@endif
            </td>
            @endif
            <td width="{{ $is_bunting ? '20%' : '28%' }}" style="border-color:#1a5ca8;">
                PRICE @if($price_label)<br><span style="font-size:7px; font-weight:normal;">({{ $price_label }})</span>@endif
            </td>
            <td style="border-color:#1a5ca8;">CONFIRMED (/)</td>
        </tr>
        @foreach ($rows as $i => $row)
        <tr style="{{ $i % 2 === 0 ? 'background:#ffffff;' : 'background:#f4f7fc;' }}">
            <td style="text-align:center; font-weight:bold; color:#0d4f8a;">{{ $i + 1 }}</td>
            <td style="padding-left:7px;">{{ $row['location'] }}</td>
            @if($is_bunting)
            <td style="text-align:center;">
                {{ $row['quantity'] }}
                @if(!empty($row['quantity_detail']))<br><span style="font-size:7px; color:#666;">({{ $row['quantity_detail'] }})</span>@endif
            </td>
            @endif
            <td style="text-align:center; font-weight:bold;">
                RM {{ number_format($row['price'], 0) }}
                @if(!empty($row['sst']))<br><span style="font-size:7px; font-weight:normal; color:#666;">(+SST RM{{ number_format($row['sst'], 0) }})</span>@endif
            </td>
            <td style="text-align:center;">{{ $row['confirmed'] ?? '' }}</td>
        </tr>
        @endforeach
    </table>

    <!-- ── GRAND TOTAL ───────────────────────────────────────────── -->
    <table width="100%" cellpadding="6" cellspacing="0" border="1"
           style="border-collapse:collapse; border-top:none; border-color:#c0c8d8; font-size:9px; margin-bottom:10px;">
        <tr style="background:#0d4f8a; color:#fff;">
            <td style="font-weight:bold; font-size:9.5px;">
                GRAND TOTAL &mdash; {{ $grand_total_pcs }} &times; {{ $duration }}
                <div style="font-size:7px; font-weight:normal; color:#9ec3e0; margin-top:2px;">
                    (Inclusive of production, installation, authority arrangement, dismantle &amp; SST)
                </div>
            </td>
            <td width="30%" style="text-align:center; font-weight:bold; font-size:11.5px; white-space:nowrap;">
                RM {{ number_format($grand_total, 0) }}@if($sst_total)
                <br><span style="font-size:8px; font-weight:normal;">+ RM{{ number_format($sst_total, 0) }} SST</span>@endif
            </td>
        </tr>
    </table>

    <!-- ── TERMS & CONDITIONS ────────────────────────────────────── -->
    <div style="font-size:8.5px; font-weight:bold; color:#0d4f8a; margin-bottom:3px;">Terms &amp; Conditions:</div>
    <ol style="margin:0 0 0 14px; padding:0; font-size:7.5px; line-height:1.45; color:#333;">
        @foreach ($terms as $term)
        <li style="margin-bottom:2px;">{{ $term }}</li>
        @endforeach
    </ol>

    <!-- ── SIGNATURE BLOCK (pinned to page bottom, out of flow) ───── -->
    <div style="position:absolute; left:0; right:0; bottom:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:9px;">
            <tr>
                <!-- Company signatory -->
                <td width="48%" style="vertical-align:top; padding-right:10px; padding-top:8px; border-top:1px solid #dde4ee;">
                    <div style="color:#444; margin-bottom:2px;">Thank you,</div>
                    <div style="font-weight:bold; margin-bottom:6px; color:#0d4f8a;">For and on behalf of {{ $company['name'] }}</div>
                    @if(!empty($signatory['signature_img']))
                        <img src="{{ $signatory['signature_img'] }}" style="max-height:52px; max-width:200px; display:block; margin-bottom:2px;" alt="Signature">
                    @else
                        <div style="font-style:italic; color:#1a5ca8; font-size:14px; margin-bottom:3px; margin-top:16px;">{{ $signatory['signature_label'] }}</div>
                    @endif
                    <div style="border-top:1px solid #333; width:88%; margin-bottom:4px;"></div>
                    <div style="font-weight:bold; color:#0d0d0d;">{{ $signatory['name'] }}</div>
                    <div style="font-style:italic; color:#555;">{{ $signatory['title'] }}</div>
                    <div style="color:#333;">Mobile: {{ $signatory['mobile'] }}</div>
                </td>
                <!-- Divider -->
                <td width="4%" style="border-left:1px solid #dde4ee; border-top:1px solid #dde4ee;">&nbsp;</td>
                <!-- Client signatory -->
                <td width="48%" style="vertical-align:top; padding-left:10px; padding-top:8px; border-top:1px solid #dde4ee;">
                    <div style="color:#444; font-style:italic; margin-bottom:24px;">I, agree and accept the above,</div>
                    <div style="border-top:1px solid #333; width:88%; margin-bottom:4px;"></div>
                    <table cellpadding="0" cellspacing="0" border="0" style="font-size:9px;">
                        <tr>
                            <td style="color:#1a5ca8; white-space:nowrap; padding-bottom:3px;">Name</td>
                            <td style="color:#1a5ca8; padding:0 6px 3px;">:</td>
                            <td style="color:#0d0d0d; font-weight:{{ !empty($attention) ? 'bold' : 'normal' }};">{{ $attention ?? '' }}</td>
                        </tr>
                        <tr>
                            <td style="color:#1a5ca8; white-space:nowrap;">Designation</td>
                            <td style="color:#1a5ca8; padding:0 6px;">:</td>
                            <td style="color:#0d0d0d;">{{ $client_designation ?? '' }}</td>
                        </tr>
                    </table>
                    <div style="color:#888; font-size:7.5px; margin-top:4px;">(Co. Rubber Stamp)</div>
                </td>
            </tr>
        </table>
    </div>

</div>
