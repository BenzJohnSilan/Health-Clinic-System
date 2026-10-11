<?php

namespace App\Console\Commands;

use App\Services\IProgSmsService;
use Illuminate\Console\Command;
use Throwable;

class TestIProgSms extends Command
{
    protected $signature = 'app:test-i-prog-sms {phone}';

    protected $description = 'Send a test SMS using iProgSMS';

    public function handle(IProgSmsService $smsService): int
    {
        $phone = $this->argument('phone');

        // Convert Philippine format: 09XXXXXXXXX → 639XXXXXXXXX
        if (preg_match('/^09\d{9}$/', $phone)) {
            $phone = '63' . substr($phone, 1);
        }

        try {
            $this->info("Sending test SMS to {$phone}...");

            $response = $smsService->send(
                $phone,
                'Test SMS from Health Clinic Record Management System.'
            );

            $this->info('SMS request sent successfully.');

            $this->line(json_encode($response, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('SMS sending failed.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}