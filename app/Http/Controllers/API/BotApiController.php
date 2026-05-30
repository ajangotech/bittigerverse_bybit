<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BotApiController extends Controller
{
    public function online(Request $request)
    {
        $request->validate([
            'tokenId'   => 'required',
            'currencyId'=> 'required',
            'side'      => 'required'
        ]);

        $user = auth('api')->user();

        // 🔐 decrypt your stored API keys if encrypted
        $apiKey = $user->bybit_api_key;
        $secret = $user->bybit_api_secret;

        // ⚠️ replace with your Bybit signing logic
        $response = Http::post('https://api.bybit.com/v5/p2p/item/online', [
            'tokenId'    => $request->tokenId,
            'currencyId' => $request->currencyId,
            'side'       => $request->side,
        ]);

        return response()->json([
            'result' => $response->json()
        ]);
    }
}