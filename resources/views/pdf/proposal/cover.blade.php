<div class="page">

    <!-- Header bar -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:6px;">
        <tr>
            <td width="60" style="vertical-align:top; padding:4px;">
                <div style="width:50px; height:50px; background:#0d4f8a; color:#fff; text-align:center; font-weight:bold; font-size:18px; padding-top:14px;">B</div>
            </td>
            <td style="background:#1c1c1c; color:#fff; padding:8px 12px; vertical-align:top;">
                <div style="font-size:13px; font-weight:bold;">{{ $company['name'] }} <span style="font-size:9px; font-weight:normal;">{{ $company['reg_no'] }}</span></div>
                <div style="font-size:8.5px; margin-top:2px;">{{ $company['address'] }}</div>
            </td>
            <td width="200" style="background:#1c1c1c; color:#fff; padding:8px 12px; vertical-align:top; font-size:8.5px; text-align:right;">
                <div style="font-size:7.5px; color:#bbb;">{{ $company['group_label'] }}</div>
                <div style="margin-top:4px;">Tel: {{ $company['tel'] }}</div>
                <div>Fax: {{ $company['fax'] }}</div>
                <div>Website: {{ $company['website'] }}</div>
                <div>Email: {{ $company['email'] }}</div>
            </td>
        </tr>
    </table>

    <!-- Reference -->
    <div style="text-align:right; font-size:9.5px; margin-bottom:8px;">Ref: {{ $reference }}</div>

    <!-- Date + Normal price banner -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-bottom:14px;">
        <tr>
            <td style="font-size:11px; font-weight:bold; vertical-align:middle;">{{ $date }}</td>
            <td width="160" style="text-align:right; vertical-align:top;">
                @if($normal_price)
                <div style="background:#1c1c1c; color:#fff; padding:6px 14px; font-size:10px; font-weight:bold; text-align:center; display:block;">
                    Normal Price:<br>RM{{ number_format($normal_price) }}/{{ $normal_price_unit }}
                </div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Client + Attn -->
    <div style="font-size:10.5px; font-weight:bold;">{{ $client_name ?: '___________________________________' }}</div>
    <div style="font-size:10px;">Attn: {{ $attention ?: '___________________' }}@if($attention_phone) ({{ $attention_phone }})@endif</div>

    <!-- RE line -->
    <div style="margin-top:14px; font-size:10.5px; font-weight:bold; text-decoration:underline;">RE: {{ $re_line }}</div>

    <div style="margin-top:6px; font-size:10px;">Referring to the subject matter, we would like to offer your esteemed organization the following site as below:</div>

    <!-- Pricing table -->
    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; margin-top:10px; font-size:9.5px;">
        <tr style="background:#f0f0f0; font-weight:bold; text-align:center;">
            <td width="32">NO</td>
            <td width="40%">LOCATIONS</td>
            <td width="22%">QUANTITY @if($quantity_size)<br><span style="font-size:8.5px; font-weight:normal;">( Size: {{ $quantity_size }} )</span>@endif</td>
            <td width="20%">PRICE @if($price_label)<br><span style="font-size:8.5px; font-weight:normal;">({{ $price_label }})</span>@endif</td>
            <td>CONFIRMED (/)</td>
        </tr>
        @foreach ($rows as $i => $row)
        <tr>
            <td style="text-align:center; font-weight:bold;">{{ $i + 1 }}</td>
            <td>{{ $row['location'] }}</td>
            <td style="text-align:center;">
                {{ $row['quantity'] }}
                @if(!empty($row['quantity_detail']))<br><span style="font-size:8.5px;">({{ $row['quantity_detail'] }})</span>@endif
            </td>
            <td style="text-align:center;">
                RM {{ number_format($row['price'], 0) }}
                @if(!empty($row['sst']))<br><span style="font-size:8.5px;">(SST: RM{{ number_format($row['sst'], 0) }})</span>@endif
            </td>
            <td style="text-align:center;">{{ $row['confirmed'] ?? '' }}</td>
        </tr>
        @endforeach
    </table>

    <!-- Grand total -->
    <table width="100%" cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse; border-top:none; font-size:10px;">
        <tr>
            <td width="72%" style="font-weight:bold;">
                GRAND TOTAL -{{ $grand_total_pcs }} x {{ $duration }}
                <div style="font-size:8.5px; font-weight:normal; margin-top:2px;">(All cost-inclusive production, installation, authority arrangement, dismantle & SST)</div>
            </td>
            <td style="text-align:center; font-weight:bold; font-size:11px;">
                RM {{ number_format($grand_total, 0) }} @if($sst_total) + RM{{ number_format($sst_total, 0) }} of SST @endif
            </td>
        </tr>
    </table>

    <!-- Terms -->
    <div style="margin-top:14px; font-size:10px; font-weight:bold;">Term &amp; Condition:</div>
    <ul style="margin:4px 0 0 16px; padding:0; font-size:9px; line-height:1.45;">
        @foreach ($terms as $term)
        <li style="margin-bottom:3px;">{{ $term }}</li>
        @endforeach
    </ul>

    <!-- Signature block -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:30px; font-size:9.5px;">
        <tr>
            <td width="50%" style="vertical-align:top; padding-right:20px;">
                <div>Thank you,</div>
                <div style="font-weight:bold; margin-bottom:30px;">For and on behalf of {{ $company['name'] }}</div>
                <div style="font-style:italic; color:#0a3d7a; font-size:13px; margin-bottom:2px;">{{ $signatory['signature_label'] }}</div>
                <div style="border-top:1px solid #000; width:80%;">&nbsp;</div>
                <div style="font-weight:bold; margin-top:2px;">{{ $signatory['name'] }}</div>
                <div style="font-style:italic;">{{ $signatory['title'] }}</div>
                <div>Mobile: {{ $signatory['mobile'] }}</div>
            </td>
            <td width="50%" style="vertical-align:top;">
                <div style="margin-bottom:46px;">I, agree and accept the above,</div>
                <div style="border-top:1px solid #000; width:80%;">&nbsp;</div>
                <div style="margin-top:4px;">Name :</div>
                <div>Designation :</div>
                <div>(Co. Rubber Stamp)</div>
            </td>
        </tr>
    </table>

</div>
