<?php

namespace App\Services;

class BybitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl = "https://api.bybit.com";

    public function __construct()
    {
        $this->apiKey = env('BYBIT_API_KEY');
        $this->apiSecret = env('BYBIT_API_SECRET');
    }

    private function sign(array $params, string $timestamp, string $recvWindow = "5000"): string
    {
        $queryString = http_build_query($params);

        $payload = $timestamp . $this->apiKey . $recvWindow . $queryString;

        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    private function request(string $endpoint, array $body = [])
    {
        $timestamp = round(microtime(true) * 1000);
        $recvWindow = "5000";

        $signature = $this->sign($body, $timestamp, $recvWindow);

        $headers = [
            "X-BAPI-API-KEY: {$this->apiKey}",
            "X-BAPI-TIMESTAMP: {$timestamp}",
            "X-BAPI-RECV-WINDOW: {$recvWindow}",
            "X-BAPI-SIGN: {$signature}",
            "Content-Type: application/json",
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new \Exception(curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    // ======================
    // BYBIT METHODS
    // ======================

    public function createAd(array $data)
    {
        return $this->request("/v5/p2p/item/create", $data);
    }

    public function updateAd(array $data)
    {
        return $this->request("/v5/p2p/item/update", $data);
    }

    public function cancelAd(string $itemId)
    {
        return $this->request("/v5/p2p/item/cancel", [
            "itemId" => $itemId
        ]);
    }

    public function listAds()
    {
        return $this->request("/v5/p2p/item/personal/list", []);
    }

    public function getAdInfo(string $itemId)
    {
        return $this->request("/v5/p2p/item/info", [
            "itemId" => $itemId
        ]);
    }

    public function getUserInfo()
    {
        return $this->request("/v5/p2p/user/personal/info", []);
    }
}