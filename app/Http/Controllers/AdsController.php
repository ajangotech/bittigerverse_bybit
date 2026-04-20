<?php

namespace App\Http\Controllers;

use App\Models\Ads;
use Illuminate\Http\Request;

class AdsController extends Controller
{
    public function manageAds()
    {
        $ads = Ads::latest()->get();

        return view('manageads', compact('ads'));
    }

    public function updatePrice(Request $request)
    {
        $ad = Ads::where('id', $request->id)->first();

        if (!$ad) {
            return response()->json(['error' => 'Ad not found'], 404);
        }

        $ad->price = $request->price;
        $ad->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Price updated instantly'
        ]);
    }

    public function destroy($id)
    {
        $ad = Ads::findOrFail($id);
        $ad->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Ad deleted successfully'
        ]);
    }
}
