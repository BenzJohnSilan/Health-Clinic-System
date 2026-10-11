<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class IProgSmsService
{
    public function send(string $phoneNumber, string $message): array
    {
        $response = Http::timeout(15)
            ->acceptJson()
            ->post(config('services.iprog_sms.url'), [
                'api_token' => config('services.iprog_sms.token'),
                'phone_number' => $phoneNumber,
                'message' => $message,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'iProgSMS request failed: ' . $response->body()
            );
        }

        return $response->json();
    }
}