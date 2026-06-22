@php
$manifest = json_decode(file_get_contents(public_path('build/.vite/manifest.json')), true);
$entry    = $manifest['resources/js/app.js'];
$jsFile   = '/build/' . $entry['file'];
$cssFiles = $entry['css'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bluedale CRM</title>
    @foreach ($cssFiles as $css)
    <link rel="stylesheet" href="/build/{{ $css }}">
    @endforeach
    <script defer src="{{ $jsFile }}"></script>
</head>
<body>
    <div id="app"></div>
</body>
</html>
