@php
    $company   = config('proposal.company', []);
    $signatory = config('proposal.signatory', []);
@endphp
{{-- Landscape site-detail page.
     Layout rules measured empirically against dompdf 3.x at 96dpi, A4 landscape, 28px margins
     (rasterized at 96dpi → 1px raster = 1 CSS px, then pixel-measured):
       • Usable content canvas: 1067px wide × 738px tall (page minus 28px margins each side).
       • Fixed top overhead (header row + blue divider) = 74px.
       • Remark + contact bar (absolute; bottom:0) is a stable ~61px block; its top border line
         sits at content-y ≈ 671px. Photos must finish ABOVE that with clearance.
       • Photos: 60% column, TWO stacked, 6px gap. Height 287px each:
         74 (top) + 287 + 6 + 287 = 654px photo-2 bottom → ~17px clear gap before the 671px
         remark top. Measured overlap at 297px (≈1px clearance) is why this was reduced to 287.
       • Remark pinned with position:absolute; bottom:0 (out of flow, never forces a 2nd page).
       • DO NOT use <table height:Npx> + height:100% spacer — dompdf inflates across pages.
       • Images use width:100% with explicit height — pre-sized server-side so heights are equal. --}}
<div class="page landscape-sheet">

    <!-- ── HEADER ─────────────────────────────────────────────── -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px;">
        <tr>
            <td width="72" style="vertical-align:middle; padding-right:6px;">
                @if(!empty($logo_data))
                    <img src="{{ $logo_data }}" width="56" height="56" style="display:block;">
                @else
                    <div style="width:56px; height:56px; background:#0d4f8a; color:#fff;
                                font-weight:bold; font-size:20px; line-height:56px; text-align:center;">B</div>
                @endif
            </td>
            <td style="vertical-align:middle; padding-left:4px;">
                <div style="font-size:10.5px; font-weight:bold; color:#0d4f8a; line-height:1.25;">
                    {{ $product['location'] }}
                </div>
                <div style="font-size:7.5px; color:#666; margin-top:2px;">
                    {{ $product['state_city'] ?: '' }}@if($product['site_code'] && $product['state_city'])&nbsp;·&nbsp;@endif{{ $product['site_code'] ?: '' }}@if($product['size']
                        )&nbsp;·&nbsp;{{ $product['size'] }}@endif
                </div>
            </td>
            <td style="text-align:right; vertical-align:middle; padding-right:2px;">
                <div style="font-size:18px; font-weight:bold; color:#cc0000; letter-spacing:0.3px; line-height:1.2;">
                    {{ $product['product_type_label'] }}
                </div>
            </td>
        </tr>
    </table>

    <!-- ── BLUE DIVIDER ────────────────────────────────────────── -->
    <div style="height:3px; background:#1a5ca8; margin-bottom:8px;"></div>

    <!-- ── BODY: Photos LEFT (60%) | Info RIGHT (40%) ─────────────── -->
    {{-- Column widths match reference (60%/40%, 15px padding each side = 30px gap).
         Photos are pre-resized server-side (SiteAvailabilityController::resizeForPdfFrame) so the
         two are identical in size. 287px each leaves a clean ~17px gap above the bottom-pinned
         remark block (see header comment for the measured geometry). --}}
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:7px;">
        <tr>

            <!-- LEFT COLUMN: site photo stacked above map, equal height -->
            <td width="60%" style="vertical-align:top; padding-right:15px;">

                @if(!empty($product['site_photo_data']))
                    <img src="{{ $product['site_photo_data'] }}"
                         style="display:block; width:100%; height:287px; border:1px solid #c8d4e8; margin-bottom:6px;">
                @else
                    <div style="width:100%; height:287px; background:#f4f6fa; border:1px solid #c8d4e8;
                                text-align:center; padding-top:134px; color:#bbb; font-size:9px;
                                margin-bottom:6px; box-sizing:border-box;">
                        [ Site photo not uploaded ]
                    </div>
                @endif

                @if(!empty($product['site_map_photo_data']))
                    <img src="{{ $product['site_map_photo_data'] }}"
                         style="display:block; width:100%; height:287px; border:1px solid #c8d4e8;">
                @else
                    <div style="width:100%; height:287px; background:#f4f6fa; border:1px solid #c8d4e8;
                                text-align:center; padding-top:134px; color:#bbb; font-size:9px;
                                box-sizing:border-box;">
                        [ Map not uploaded ]
                    </div>
                @endif

            </td>

            <!-- RIGHT COLUMN: site details + landmarks -->
            <td width="40%" style="vertical-align:top; padding-left:15px;">

                <!-- Site Details — layout differs by product type -->
                {{-- Bunting: Site Code, Size, Location, State & City, Coordinate.
                     Billboard/Temp Board: adds Board Size label, Status, Site Type. --}}
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:9px; border:1px solid #c8d4e8;">
                    <tr>
                        <td colspan="2" style="background:#0d4f8a; color:#fff; font-weight:bold;
                                               font-size:7.5px; padding:5px 8px; letter-spacing:0.4px;">
                            SITE DETAILS
                        </td>
                    </tr>
                    @if($product['site_code'])
                    <tr>
                        <td width="40%" style="font-weight:bold; color:#555; font-size:8px;
                                               padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Site Code</td>
                        <td style="color:#0d0d0d; font-size:8px; padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['site_code'] }}</td>
                    </tr>
                    @endif
                    @if($product['product_type'] === 'Lamp Post Bunting')
                        {{-- Bunting: generic "Size" label --}}
                        @if($product['size'])
                        <tr>
                            <td style="font-weight:bold; color:#555; font-size:8px;
                                       padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Size</td>
                            <td style="color:#0d0d0d; font-size:8px; padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['size'] }}</td>
                        </tr>
                        @endif
                    @else
                        {{-- Billboard / Temp Board: Board Size, Illumination, Facing --}}
                        @if($product['size'])
                        <tr>
                            <td style="font-weight:bold; color:#555; font-size:8px;
                                       padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Board Size</td>
                            <td style="color:#0d0d0d; font-size:8px; padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['size'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($product['illumination']))
                        <tr>
                            <td style="font-weight:bold; color:#555; font-size:8px;
                                       padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Illumination</td>
                            <td style="color:#0d0d0d; font-size:8px; padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['illumination'] }}</td>
                        </tr>
                        @endif
                        @if(!empty($product['facing']))
                        <tr>
                            <td style="font-weight:bold; color:#555; font-size:8px;
                                       padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Facing</td>
                            <td style="color:#0d0d0d; font-size:8px; padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['facing'] }}</td>
                        </tr>
                        @endif
                    @endif
                    <tr>
                        <td style="font-weight:bold; color:#555; font-size:8px;
                                   padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Location</td>
                        <td style="font-weight:bold; color:#b00; font-size:8px;
                                   padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['location'] }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold; color:#555; font-size:8px;
                                   padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">State &amp; City</td>
                        <td style="color:#0d0d0d; font-size:8px;
                                   padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['state_city'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="font-weight:bold; color:#555; font-size:8px;
                                   padding:4px 8px; background:#f4f7fc; border-top:1px solid #e0e8f4;">Coordinate</td>
                        <td style="color:#0a3d7a; font-size:7.5px; text-decoration:underline;
                                   padding:4px 8px; border-top:1px solid #e0e8f4;">{{ $product['coordinate'] ?: '—' }}</td>
                    </tr>
                </table>

                <!-- Nearest Landmarks -->
                <table width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e0d080;">
                    <tr>
                        <td colspan="2" style="background:#e6a800; color:#fff; font-weight:bold;
                                               font-size:7.5px; padding:5px 8px; letter-spacing:0.4px;">
                            NEAREST LANDMARKS
                        </td>
                    </tr>
                    @if(!empty($product['landmarks']))
                        @foreach ($product['landmarks'] as $landmark)
                        <tr>
                            <td width="42%" style="background:#fffce6; font-weight:bold; color:#555;
                                                   font-size:7px; padding:3px 6px;
                                                   border-top:1px solid #e8dfa0; border-right:1px solid #e8dfa0;">
                                {{ $landmark['category'] ?? '' }}
                            </td>
                            <td style="font-size:7px; padding:3px 6px; color:#333;
                                       border-top:1px solid #e8dfa0;">
                                @if(!empty($landmark['distance'])){{ $landmark['distance'] }} to @endif{{ $landmark['place'] ?? '' }}
                            </td>
                        </tr>
                        @endforeach
                    @else
                        <tr>
                            <td colspan="2" style="color:#999; font-size:7.5px; font-style:italic;
                                                   padding:8px; border-top:1px solid #e8dfa0;">
                                No landmarks recorded.
                            </td>
                        </tr>
                    @endif
                </table>

            </td>
        </tr>
    </table>

    <!-- ── REMARK + CONTACT FOOTER (pinned to page bottom, out of flow) ──────── -->
    {{-- position:absolute; bottom:0 is proven in dompdf: the .page div has no
         positioned ancestor, so this is placed relative to the page content area
         (inside margins) — identical to how the cover page signature is pinned. --}}
    <div style="position:absolute; left:0; right:0; bottom:0;">
        <div style="padding-top:5px; border-top:1px solid #dde4ee;">
            <div style="font-size:7px; color:#444; line-height:1.45;">
                <span style="font-weight:bold;">REMARK:</span>
                The site is subject to availability, authority approval, and safety regulations.
                In the event that the proposed sites are unavailable on the installation day &mdash;
                whether due to changes in local council regulations, presence of existing boards from
                other parties, or safety-related issues &mdash; Bluedale will proceed to install the
                board at a nearby location as close as possible to the original site, or suggest an
                alternative. Photo evidence will be provided as proof of installation. Replacement of
                missing boards is only applicable at no extra charge for clients who purchase the
                current promotion with a minimum 3-month contract or longer. If a new skin is required,
                an additional fee of RM500 will apply.
            </div>
            @if(!empty($company['tel']) || !empty($signatory['mobile']))
            <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:5px; background:#0d4f8a;">
                <tr>
                    <td style="padding:5px 8px; color:#fff; font-size:7.5px; font-weight:bold;">
                        Contact:
                        @if(!empty($signatory['name'])) {{ $signatory['name'] }} @endif
                        @if(!empty($signatory['mobile'])) {{ $signatory['mobile'] }} @endif
                    </td>
                    <td style="padding:5px 8px; color:#dde; font-size:7px; text-align:right;">
                        @if(!empty($company['tel'])) Tel: {{ $company['tel'] }} @endif
                        @if(!empty($company['email'])) &nbsp;|&nbsp; {{ $company['email'] }} @endif
                    </td>
                </tr>
            </table>
            @endif
        </div>
    </div>

</div>
