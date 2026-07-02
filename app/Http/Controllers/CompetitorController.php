<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use Illuminate\Http\Request;

class CompetitorController extends Controller
{
    public function competitor()
    {
        $competitors = Competitor::first();
        return view('payments.competitor', compact('competitors'));
    }

    public function store(Request $request)
    {
        $competitor = Competitor::updateOrCreate(
            [
                'merchant_id' => $request->merchant_id
            ],
            [
                'username' => $request->username,
                'price' => $request->price
            ]
        );

        return response()->json([
            'status' => true,
            'data' => $competitor
        ]);
    }

    public function updatePrice(Request $request)
    {
        Competitor::where(
            'merchant_id',
            $request->merchant_id
        )->update([
            'price' => $request->price
        ]);

        return response()->json([
            'status' => true
        ]);
    }
}
