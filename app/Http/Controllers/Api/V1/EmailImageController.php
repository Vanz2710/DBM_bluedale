<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EmailImageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|file|image|max:5120',
        ]);

        $path = $request->file('image')->store('email-images', 'public');

        $url = request()->getSchemeAndHttpHost()
            . request()->getBaseUrl()
            . '/storage/' . $path;

        return response()->json(['url' => $url]);
    }
}
