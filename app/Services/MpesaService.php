<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MpesaService
{
    private function baseUrl(): string
    {
        return config('services.mpesa.env') === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    public function getAccessToken(): string
    {
        $response = Http::withBasicAuth(
            config('services.mpesa.key'),
            config('services.mpesa.secret')
        )->get($this->baseUrl() . '/oauth/v1/generate', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Could not get M-Pesa access token: ' . $response->body()
            );
        }

        return $response->json('access_token');
    }

    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254' . substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254' . $phone;
        }

        return $phone;
    }

    public function stkPush(string $phone, int $amount, string $reference): array
    {
        $shortcode = config('services.mpesa.shortcode');
        $passkey   = config('services.mpesa.passkey');
        $timestamp = now('Africa/Nairobi')->format('YmdHis');
        $phone     = $this->formatPhone($phone);

        $response = Http::withToken($this->getAccessToken())
            ->post($this->baseUrl() . '/mpesa/stkpush/v1/processrequest', [
                'BusinessShortCode' => $shortcode,
                'Password'          => base64_encode($shortcode . $passkey . $timestamp),
                'Timestamp'         => $timestamp,
                'TransactionType'   => 'CustomerPayBillOnline',
                'Amount'            => $amount,
                'PartyA'            => $phone,
                'PartyB'            => $shortcode,
                'PhoneNumber'       => $phone,
                'CallBackURL'       => config('services.mpesa.callback'),
                'AccountReference'  => $reference,
                'TransactionDesc'   => 'Payment',
            ]);

        return $response->json();
    }
}