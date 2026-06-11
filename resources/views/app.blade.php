<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bluedale CRM</title>
    @if(file_exists(public_path('build/manifest.json')))
        @php
            $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
            $entry   = $manifest['resources/js/app.js'] ?? [];
            $jsFile  = $entry['file'] ?? null;
            $cssList = $entry['css'] ?? [];
        @endphp
        @foreach($cssList as $cssFile)
        <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}">
        @endforeach
        @if($jsFile)
        <script defer src="{{ asset('build/' . $jsFile) }}"></script>
        @endif
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
</head>
<body>
    <div id="app"></div>
</body>
</html>
