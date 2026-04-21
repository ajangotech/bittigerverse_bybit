<?php

namespace App\Http\Controllers;

use App\Models\Ads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class AdsController extends Controller
{
    public function manageAds()
    {
        $ads = Ads::latest()->get();

        return view('ads.manageads', [
            'apiUrl' => env('API_URL'),
            'ads' => $ads
        ]);
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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {

            $validated = $request->validate([
                'pair' => 'required|string|max:20',
                'priceType' => 'required|in:0,1',
                'price' => 'nullable|numeric',
                'premium' => 'nullable|numeric',
                'minAmount' => 'nullable|numeric',
                'maxAmount' => 'nullable|numeric',
                'quantity' => 'nullable|integer|min:1',
                'paymentPeriod' => 'nullable|integer|min:1',
            ]);

            // BUSINESS RULE
            if ($request->minAmount && $request->maxAmount && $request->minAmount > $request->maxAmount) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Min amount cannot be greater than max amount'
                ], 422);
            }

            // DEFAULT PREFS
            $defaultPreferences = [
                "hasUnPostAd" => 0,
                "isKyc" => 0,
                "isEmail" => 0,
                "isMobile" => 0,
                "hasRegisterTime" => 0,
                "registerTimeThreshold" => 0,
                "orderFinishNumberDay30" => 0,
                "completeRateDay30" => "",
                "nationalLimit" => "",
                "hasOrderFinishNumberDay30" => 0,
                "hasCompleteRateDay30" => 0,
                "hasNationalLimit" => 0
            ];

            $preferences = array_merge(
                $defaultPreferences,
                $request->input('tradingPreferenceSet', [])
            );

            // PAYMENT IDS CLEAN
            $paymentIds = collect($request->paymentIds)
                ->filter()
                ->map(fn($id) => (string)$id)
                ->values()
                ->toArray();

            $ad = Ads::updateOrCreate(
                ['id' => $request->id],
                [
                    'user_id' => auth()->id(),
                    'ads_id' => $request->adsID ?? 'ADS_' . now()->timestamp . rand(100,999),

                    'pair' => $request->pair,
                    'price_type' => $request->priceType,
                    'price' => $request->price,
                    'premium' => $request->premium,
                    'min_amount' => $request->minAmount,
                    'max_amount' => $request->maxAmount,
                    'remark' => $request->remark,
                    'action_type' => $request->actionType ?? 'CREATE',
                    'quantity' => $request->quantity,
                    'payment_period' => $request->paymentPeriod,

                    'payment_methods' => $paymentIds,
                    'trading_preference_set' => $preferences,
                ]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Ad saved successfully',
                'data' => $ad
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {

            DB::rollBack();

            Log::error('Ad Save Error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
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
