<div class="page">

    <!-- Bluedale brand top-left -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
        <tr>
            <td width="140" style="vertical-align:top;">
                <table cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="30" style="background:#0d4f8a; color:#fff; text-align:center; font-weight:bold; font-size:14px; padding:6px 0;">B</td>
                        <td style="padding-left:6px; font-size:9px;">
                            <div style="font-weight:bold;">BLUEDALE</div>
                            <div style="color:#666;">MEDIA (M) Sdn. Bhd.</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="text-align:center; font-size:14px; font-weight:bold; color:#b00; vertical-align:middle;">
                {{ $product['product_type_label'] }}
            </td>
            <td width="140">&nbsp;</td>
        </tr>
    </table>

    <!-- Site details (left) + Site photo (right) -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
        <tr>
            <td width="50%" style="vertical-align:top; padding-right:6px;">
                <table width="100%" cellpadding="4" cellspacing="0" border="1" style="border-collapse:collapse; font-size:9.5px;">
                    <tr>
                        <td width="80" style="background:#f0f0f0; font-weight:bold;">Site</td>
                        <td>{{ $product['site_code'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="background:#f0f0f0; font-weight:bold;">Size</td>
                        <td>{{ $product['size'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="background:#f0f0f0; font-weight:bold;">Location</td>
                        <td style="font-weight:bold; color:#b00;">{{ $product['location'] }}</td>
                    </tr>
                    <tr>
                        <td style="background:#f0f0f0; font-weight:bold;">State &amp; City</td>
                        <td>{{ $product['state_city'] ?: '—' }}</td>
                    </tr>
                    <tr>
                        <td style="background:#f0f0f0; font-weight:bold;">Coordinate</td>
                        <td style="text-decoration:underline; color:#0a3d7a;">{{ $product['coordinate'] ?: '—' }}</td>
                    </tr>
                </table>
            </td>
            <td width="50%" style="vertical-align:top; padding-left:6px; text-align:center;">
                @if(!empty($product['site_photo_data']))
                    <img src="{{ $product['site_photo_data'] }}" width="270" style="border:1px solid #ccc;">
                @else
                    <div style="height:200px; background:#f8f8f8; border:1px solid #ccc; padding-top:90px; text-align:center; color:#999; font-size:10px;">[ Site photo not uploaded ]</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Landmarks (left) + Map (right) -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:8px;">
        <tr>
            <td width="50%" style="vertical-align:top; padding-right:6px;">
                <table width="100%" cellpadding="4" cellspacing="0" border="1" style="border-collapse:collapse; font-size:9px;">
                    <tr>
                        <td colspan="2" style="background:#ffd64a; font-weight:bold; text-align:center;">Nearest Landmarks</td>
                    </tr>
                    @if(!empty($product['landmarks']))
                        @foreach ($product['landmarks'] as $landmark)
                        <tr>
                            <td width="42%" style="background:#ffd64a; font-weight:bold;">{{ $landmark['category'] ?? '' }}</td>
                            <td>@if(!empty($landmark['distance'])){{ $landmark['distance'] }} to @endif{{ $landmark['place'] ?? '' }}</td>
                        </tr>
                        @endforeach
                    @else
                        <tr><td colspan="2" style="color:#888; text-align:center; padding:8px;">No landmarks recorded.</td></tr>
                    @endif
                </table>
            </td>
            <td width="50%" style="vertical-align:top; padding-left:6px; text-align:center;">
                @if(!empty($product['site_map_photo_data']))
                    <img src="{{ $product['site_map_photo_data'] }}" width="270" style="border:1px solid #ccc;">
                @else
                    <div style="height:200px; background:#f8f8f8; border:1px solid #ccc; padding-top:90px; text-align:center; color:#999; font-size:10px;">[ Map not uploaded ]</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Remark footer -->
    <div style="font-size:7.5px; color:#444; line-height:1.4; margin-top:6px;">
        <span style="font-weight:bold;">REMARK:</span>
        The site is subject to availability, authority approval, and safety regulations. In the event that the proposed sites are unavailable on the installation day — whether due to changes in local council regulations, presence of existing boards from other parties, or safety-related issues — Bluedale will proceed to install the board at a nearby location as close as possible to the original site, or suggest an alternative. Photo evidence will be provided as proof of installation. Replacement of missing boards is only applicable at no extra charge for clients who purchase the current promotion with a minimum 3-month contract or longer. If a new skin is required, an additional fee of RM500 will apply.
    </div>

</div>
