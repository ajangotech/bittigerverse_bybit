<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DigitalOceanController extends Controller
{
    public function __construct()
    {
        if (auth()->user()->role !== 'admin') {
            return redirect()->route('dashboard');
        }
    }

    private function client()
    {
        return Http::withToken(config('services.digitalocean.token'))
            ->acceptJson()
            ->baseUrl('https://api.digitalocean.com/v2');
    }

    public function index()
    {
        $response = $this->client()
            ->get('/droplets', [
                'per_page' => 100,
            ]);

        if (!$response->successful()) {
            return view('users.droplets', [
                'droplets' => [],
                'error' => $response->json('message', 'Unable to retrieve droplets.')
            ]);
        }

        return view('users.droplets', [
            'droplets' => $response->json('droplets', []),
            'error' => null
        ]);
    }

    public function restart($id)
    {
        $response = $this->client()
            ->post("/droplets/{$id}/actions", [
                'type' => 'reboot',
            ]);

        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $response->json('message', 'Failed to restart droplet.')
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Droplet restart initiated successfully.',
            'action' => $response->json('action')
        ]);
    }

    public function powerCycle($id)
    {
        $response = $this->client()
            ->post("/droplets/{$id}/actions", [
                'type' => 'power_cycle',
            ]);

        if (!$response->successful()) {
            return response()->json([
                'status' => 'error',
                'message' => $response->json('message', 'Failed to power cycle droplet.')
            ], $response->status());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Droplet power cycle initiated successfully.',
            'action' => $response->json('action')
        ]);
    }
}