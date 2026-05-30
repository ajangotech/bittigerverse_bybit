<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Ads;
use Illuminate\Http\Request;

class AdsApiController extends Controller
{
    public function list()
    {
        $ads = Ads::where('user_id', auth('api')->id())->latest()->get();

        return response()->json([
            'data' => $ads
        ]);
    }

    public function updatePrice(Request $request)
    {
        $request->validate([
            'ad_id' => 'required',
            'price' => 'required|numeric'
        ]);

        $ad = Ads::find($request->ad_id);

        if (!$ad) {
            return response()->json(['error' => 'Ad not found'], 404);
        }

        $ad->price = $request->price;
        $ad->save();

        return response()->json([
            'message' => 'Updated',
            'ad' => $ad
        ]);
    }
}