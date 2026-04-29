<?php

namespace App\Services;

use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiKey;
    private string $username;
    private string $senderId;
    private bool   $enabled;

    public function __construct()
    {
        $this->apiKey   = config('services.africastalking.api_key', '');
        $this->username = config('services.africastalking.username', 'sandbox');
        $this->senderId = config('services.africastalking.sender_id', '');
        $this->enabled  = config('services.africastalking.enabled', false);
    }

    /**
     * Send a single SMS to one or more recipients.
     *
     * @param  string|array  $to    Phone number(s) in international format (+2607...)
     * @param  string        $message
     * @param  int|null      $loanId  For logging purposes
     * @param  int|null      $borrowerId
     * @return bool
     */
    public function send(string|array $to, string $message, ?int $loanId = null, ?int $borrowerId = null, ?int $companyId = null): bool
    {
        $recipients = \is_array($to) ? implode(',', $to) : $to;

        // Always log the attempt regardless of enabled state
        $log = SmsLog::create([
            'company_id'   => $companyId,
            'loan_id'      => $loanId,
            'borrower_id'  => $borrowerId,
            'phone'        => $recipients,
            'message'      => $message,
            'status'       => 'pending',
        ]);

        if (!$this->enabled || empty($this->apiKey)) {
            $log->update(['status' => 'failed', 'response' => 'SMS disabled or API key not set.']);
            Log::info("[SMS] Skipped — disabled or no API key.", ['to' => $recipients]);
            return false;
        }

        try {
            $isSandbox = $this->username === 'sandbox';
            $baseUrl   = $isSandbox
                ? 'https://api.sandbox.africastalking.com/version1/messaging'
                : 'https://api.africastalking.com/version1/messaging';

            $payload = [
                'username' => $this->username,
                'to'       => $recipients,
                'message'  => $message,
            ];

            if (!empty($this->senderId) && !$isSandbox) {
                $payload['from'] = $this->senderId;
            }

            $response = Http::withHeaders([
                'apiKey' => $this->apiKey,
                'Accept' => 'application/json',
            ])->asForm()->post($baseUrl, $payload);

            $body = $response->json();

            if ($response->successful()) {
                $log->update(['status' => 'sent', 'response' => json_encode($body)]);
                return true;
            }

            $log->update(['status' => 'failed', 'response' => json_encode($body)]);
            Log::warning("[SMS] Failed to send.", ['response' => $body]);
            return false;
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'response' => $e->getMessage()]);
            Log::error("[SMS] Exception: " . $e->getMessage());
            return false;
        }
    }
}
