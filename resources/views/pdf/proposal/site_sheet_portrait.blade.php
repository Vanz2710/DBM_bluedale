@php
    $meta         = $portrait_meta ?? [];
    $pDate        = $meta['date']               ?? '';
    $pRef         = $meta['reference']          ?? '';
    $pClient      = $meta['client_name']        ?? '';
    $pAttn        = $meta['attention']          ?? '';
    $pAttnPhone   = $meta['attention_phone']    ?? '';
    $pReLine      = $meta['re_line']            ?? '';
    $pClientDesgn  = $meta['client_designation'] ?? '';
    $pAddlFee      = $meta['additional_fee']     ?? 'RM500';

    $contactName   = !empty($product['contact_name'])   ? $product['contact_name']   : ($signatory['name']   ?? '');
    $contactMobile = !empty($product['contact_mobile']) ? $product['contact_mobile'] : ($signatory['mobile'] ?? '');

    // Photo height used both in the <div> wrapper and the <img> attrs.
    // 320px keeps site details + photos + remark + terms all on one A4 portrait page
    // (usable canvas ≈ 738 × 1066px at 96 dpi, signature pinned out-of-flow at bottom).
    $photoH = 320;
@endphp
{{-- Portrait site-sheet — one page per site.
     Geometry at 96dpi, A4 portrait, 28px margins:
       • Usable canvas: 738px wide × 1066px tall.
       • Signature pinned position:absolute;bottom:0 (~65px, out of flow).
       • Photos are pre-sized server-side (resizeForPdfFrame):
           site photo target: 460 × 320 (62% column)
           map  photo target: 280 × 320 (38% column)
       • Both photo <div> wrappers use height:{{ $photoH }}px with overflow:hidden.
       • DO NOT set @page{size} here — the controller sets setPaper('A4','portrait').
--}}
<div class="page portrait-sheet">

    <!-- ── LETTERHEAD ──────────────────────────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td width="95" style="background:#fff; vertical-align:middle; padding:4px 8px 4px 4px;">
                @if(!empty($logo_data))
                    <img src="{{ $logo_data }}" width="70" height="70" style="display:block;">
                @else
                    <div style="width:70px;height:70px;background:#0d4f8a;color:#fff;text-align:center;font-weight:bold;font-size:22px;line-height:70px;">B</div>
                @endif
            </td>
            <td style="background:#0d4f8a; color:#fff; padding:7px 12px; vertical-align:middle;">
                <div style="font-size:12px; font-weight:bold; letter-spacing:0.4px;">
                    {{ $company['name'] }}
                    <span style="font-size:7.5px; font-weight:normal; color:#8fb8db;">&nbsp;{{ $company['reg_no'] }}</span>
                </div>
                <div style="font-size:7px; color:#9ec3e0; margin-top:3px; line-height:1.4;">{{ $company['address'] }}</div>
            </td>
            <td width="178" style="background:#0d4f8a; color:#9ec3e0; padding:7px 10px 7px 0; vertical-align:middle; text-align:right; font-size:7px; line-height:1.7;">
                <div style="color:#ddeaf7; font-size:6.5px; margin-bottom:3px;">{{ $company['group_label'] }}</div>
                <div>Tel: {{ $company['tel'] }}</div>
                @if(!empty($company['fax']))<div>Fax: {{ $company['fax'] }}</div>@endif
                <div>Website: {{ $company['website'] }}</div>
                <div>Email: {{ $company['email'] }}</div>
            </td>
        </tr>
    </table>

    <!-- ── BLUE ACCENT LINE ────────────────────────────────── -->
    <div style="height:3px; background:#1a5ca8; margin-bottom:7px;"></div>

    <!-- ── DATE / REF / CLIENT ─────────────────────────────── -->
    <div style="font-size:9px; color:#222; margin-bottom:2px;">{{ $pDate }}</div>
    @if($pRef)
        <div style="font-size:8.5px; color:#555; margin-bottom:3px;">{{ $pRef }}</div>
    @endif
    @if($pClient)
        <div style="font-size:10px; font-weight:bold; color:#0d0d0d; margin-bottom:1px;">{{ $pClient }}</div>
    @endif
    @if($pAttn || $pAttnPhone)
        <div style="font-size:9px; color:#444; margin-bottom:5px;">
            ({{ $pAttn }}@if($pAttn && $pAttnPhone)- @endif{{ $pAttnPhone }})
        </div>
    @endif

    <!-- ── RE LINE ─────────────────────────────────────────── -->
    @if($pReLine)
        <div style="font-size:9.5px; font-weight:bold; text-decoration:underline; color:#0d0d0d; margin-bottom:3px;">RE: {{ $pReLine }}</div>
    @endif
    <div style="font-size:8.5px; color:#333; margin-bottom:3px;">Referring to the subject matter, we would like to offer your esteemed organization the following site as below:</div>
    <div style="font-size:9.5px; font-weight:bold; color:#0d0d0d; margin-bottom:5px;">
        SITE: {{ $product['site_name'] ?? $product['location'] }}
    </div>

    <!-- ── DIVIDER ─────────────────────────────────────────── -->
    <div style="height:1px; background:#c8d4e8; margin-bottom:5px;"></div>

    <!-- ── PRODUCT TYPE HEADER (centred) ───────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="text-align:center; vertical-align:middle; padding:3px 0;">
                <span style="font-size:13px; font-weight:bold; color:#0d4f8a; letter-spacing:0.3px;">
                    {{ $product['product_type_label'] }}
                </span>
            </td>
        </tr>
    </table>

    <!-- ── DIVIDER ─────────────────────────────────────────── -->
    <div style="height:1px; background:#c8d4e8; margin-bottom:7px; margin-top:3px;"></div>

    <!-- ── SITE DETAILS (left 52%) | LANDMARKS (right 48%) ── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:7px;">
        <tr>

            <!-- LEFT: Site Details -->
            <td width="52%" style="vertical-align:top; padding-right:7px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #c8d4e8;">
                    <tr>
                        <td colspan="2" style="background:#0d4f8a; color:#fff; font-weight:bold;
                                               font-size:8px; padding:4px 7px; letter-spacing:0.3px;">
                            SITE DETAILS
                        </td>
                    </tr>
                    @if($product['site_code'])
                    <tr>
                        <td width="40%" style="font-weight:bold; color:#555; font-size:7.5px;
                                               padding:3px 7px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Site Number</td>
                        <td style="color:#0d0d0d; font-size:7.5px; padding:3px 7px; border-top:1px solid #e0e8f4;">{{ $product['site_code'] }}</td>
                    </tr>
                    @endif
                    @if($product['product_type'] === 'Lamp Post Bunting')
                        @if($product['size'])
                        <tr>
                            <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">Size</td>
                            <td style="color:#0d0d0d;font-size:7.5px;padding:3px 7px;border-top:1px solid #e0e8f4;">{{ $product['size'] }}</td>
                        </tr>
                        @endif
                    @else
                        @if($product['size'])
                        <tr>
                            <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">Size</td>
                            <td style="color:#0d0d0d;font-size:7.5px;padding:3px 7px;border-top:1px solid #e0e8f4;">{{ $product['size'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($product['illumination']))
                        <tr>
                            <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">Illumination</td>
                            <td style="color:#0d0d0d;font-size:7.5px;padding:3px 7px;border-top:1px solid #e0e8f4;">{{ $product['illumination'] }}</td>
                        </tr>
                        @endif
                    @endif
                    <tr>
                        <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">Location</td>
                        <td style="font-weight:bold;color:#b00;font-size:7.5px;padding:3px 7px;border-top:1px solid #e0e8f4;">{{ $product['location'] }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">State &amp; City</td>
                        <td style="color:#0d0d0d;font-size:7.5px;padding:3px 7px;border-top:1px solid #e0e8f4;">{{ $product['state_city'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold;color:#555;font-size:7.5px;padding:3px 7px;background:#f4f7fc;border-top:1px solid #e0e8f4;">Coordinate</td>
                        <td style="font-size:7px;padding:3px 7px;border-top:1px solid #e0e8f4;">
                            @if($product['coordinate'])
                                <a href="https://www.google.com/maps?q={{ urlencode($product['coordinate']) }}"
                                   style="color:#0a3d7a;text-decoration:underline;">{{ $product['coordinate'] }}</a>
                            @else —
                            @endif
                        </td>
                    </tr>
                </table>
            </td>

            <!-- RIGHT: Nearest Landmarks -->
            <td width="48%" style="vertical-align:top; padding-left:7px;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e0d080;">
                    <tr>
                        <td colspan="2" style="background:#e6a800; color:#fff; font-weight:bold;
                                               font-size:8px; padding:4px 7px; letter-spacing:0.3px;">
                            NEAREST LANDMARKS
                        </td>
                    </tr>
                    @if(!empty($product['landmarks']))
                        @foreach ($product['landmarks'] as $landmark)
                        <tr>
                            <td width="40%" style="background:#fffce6; font-weight:bold; color:#555;
                                                   font-size:7px; padding:3px 6px;
                                                   border-top:1px solid #e8dfa0; border-right:1px solid #e8dfa0;">
                                {{ $landmark['category'] ?? '' }}
                            </td>
                            <td style="font-size:7px; padding:3px 6px; color:#333; border-top:1px solid #e8dfa0;">
                                @if(!empty($landmark['distance'])){{ $landmark['distance'] }} to @endif{{ $landmark['place'] ?? '' }}
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="2" style="color:#999; font-size:7px; font-style:italic;
                                                   padding:8px; border-top:1px solid #e8dfa0;">
                                No landmarks recorded.
                            </td>
                        </tr>
                    @endif
                </table>
            </td>

        </tr>
    </table>

    <!-- ── PHOTOS (side by side: site 62% | map 38%) ────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:5px;">
        <tr>
            <!-- Site photo — wider left column -->
            <td width="62%" style="vertical-align:top; padding-right:5px;">
                @if(!empty($product['site_photo_data']))
                    <div style="width:100%; height:{{ $photoH }}px; overflow:hidden; border:1px solid #c8d4e8;">
                        <img src="{{ $product['site_photo_data'] }}"
                             width="460" height="{{ $photoH }}"
                             style="display:block; width:100%; height:{{ $photoH }}px;">
                    </div>
                @else
                    <div style="width:100%; height:{{ $photoH }}px; background:#f4f6fa; border:1px solid #c8d4e8;
                                text-align:center; padding-top:{{ intdiv($photoH, 2) - 8 }}px;
                                color:#bbb; font-size:9px; box-sizing:border-box;">
                        [ Site photo not uploaded ]
                    </div>
                @endif
            </td>
            <!-- Map photo — narrower right column -->
            <td width="38%" style="vertical-align:top; padding-left:5px;">
                @if(!empty($product['site_map_photo_data']))
                    <div style="width:100%; height:{{ $photoH }}px; overflow:hidden; border:1px solid #c8d4e8;">
                        <img src="{{ $product['site_map_photo_data'] }}"
                             width="280" height="{{ $photoH }}"
                             style="display:block; width:100%; height:{{ $photoH }}px;">
                    </div>
                @else
                    <div style="width:100%; height:{{ $photoH }}px; background:#f4f6fa; border:1px solid #c8d4e8;
                                text-align:center; padding-top:{{ intdiv($photoH, 2) - 8 }}px;
                                color:#bbb; font-size:9px; box-sizing:border-box;">
                        [ Map not uploaded ]
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- ── REMARK (caption below photos) + CONTACT BAR ──────── -->
    <div style="font-size:6.5px; color:#444; line-height:1.4; margin-bottom:3px;">
        <span style="font-weight:bold;">REMARK:</span>
        The site is subject to availability, authority approval, and safety regulations.
        In the event that the proposed sites are unavailable on the installation day &mdash;
        whether due to changes in local council regulations, presence of existing boards from
        other parties, or safety-related issues &mdash; Bluedale will proceed to install the
        board at a nearby location as close as possible to the original site, or suggest an
        alternative. Photo evidence will be provided as proof of installation. Replacement of
        missing boards is only applicable at no extra charge for clients who purchase the
        current promotion with a minimum 3-month contract or longer. If a new skin is required,
        an additional fee of {{ $pAddlFee }} will apply.
    </div>
    @if(!empty($company['tel']) || !empty($contactMobile) || !empty($contactName))
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px; background:#0d4f8a;">
        <tr>
            <td style="padding:4px 8px; color:#fff; font-size:7.5px; font-weight:bold;">
                Contact:
                @if(!empty($contactName)) {{ $contactName }} @endif
                @if(!empty($contactMobile)) {{ $contactMobile }} @endif
            </td>
            <td style="padding:4px 8px; color:#dde; font-size:7px; text-align:right;">
                @if(!empty($company['tel'])) Tel: {{ $company['tel'] }} @endif
                @if(!empty($company['email'])) &nbsp;|&nbsp; {{ $company['email'] }} @endif
            </td>
        </tr>
    </table>
    @endif

    <!-- ── SIGNATURE BLOCK (pinned to page bottom, out of flow) ─ -->
    <div style="position:absolute; left:0; right:0; bottom:0;">
        <table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:9px;">
            <tr>
                <!-- Company signatory -->
                <td width="48%" style="vertical-align:top; padding-right:10px; padding-top:8px; border-top:1px solid #dde4ee;">
                    <div style="color:#444; margin-bottom:2px;">Thank you,</div>
                    <div style="font-weight:bold; margin-bottom:5px; color:#0d4f8a;">For and on behalf of {{ $company['name'] }}</div>
                    @if(!empty($signatory['signature_img']))
                        <img src="{{ $signatory['signature_img'] }}" style="max-height:48px; max-width:180px; display:block; margin-bottom:2px;" alt="Signature">
                    @else
                        <div style="font-style:italic; color:#1a5ca8; font-size:14px; margin-bottom:3px; margin-top:14px;">{{ $signatory['signature_label'] ?? '' }}</div>
                    @endif
                    <div style="border-top:1px solid #333; width:86%; margin-bottom:3px;"></div>
                    <div style="font-weight:bold; color:#0d0d0d;">{{ $signatory['name'] }}</div>
                    <div style="font-style:italic; color:#555;">{{ $signatory['title'] }}</div>
                    <div style="color:#333;">{{ $signatory['mobile'] }}</div>
                </td>
                <!-- Divider -->
                <td width="4%" style="border-left:1px solid #dde4ee; border-top:1px solid #dde4ee;">&nbsp;</td>
                <!-- Client acceptance -->
                <td width="48%" style="vertical-align:top; padding-left:10px; padding-top:8px; border-top:1px solid #dde4ee;">
                    <div style="color:#444; font-style:italic; margin-bottom:24px;">I, agree and accept the above,</div>
                    <div style="border-top:1px solid #333; width:86%; margin-bottom:4px;"></div>
                    <table cellpadding="0" cellspacing="0" border="0" style="font-size:9px;">
                        <tr>
                            <td style="color:#1a5ca8; white-space:nowrap; padding-bottom:3px;">Name</td>
                            <td style="color:#1a5ca8; padding:0 6px 3px;">:</td>
                            <td style="color:#0d0d0d; font-weight:{{ !empty($pAttn) ? 'bold' : 'normal' }};">{{ $pAttn }}</td>
                        </tr>
                        <tr>
                            <td style="color:#1a5ca8; white-space:nowrap;">Designation</td>
                            <td style="color:#1a5ca8; padding:0 6px;">:</td>
                            <td style="color:#0d0d0d;">{{ $pClientDesgn }}</td>
                        </tr>
                    </table>
                    <div style="color:#888; font-size:7.5px; margin-top:4px;">(Co. Rubber Stamp)</div>
                </td>
            </tr>
        </table>
    </div>

</div>
