<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\Workspace;
use App\Services\Quota;
use App\Sms\PhoneNumber;
use App\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Send SMS from your own application and check delivery. */
class SmsController extends Controller
{
    public function store(Request $request, SmsService $sms): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|string|max:32',
            'message' => 'required|string|max:1600',
            'sender' => 'nullable|string|max:32',
            'provider' => 'nullable|string',
        ]);
        $workspace = Workspace::current();
        $phone = PhoneNumber::normalize($data['to'], $workspace->sms_country);
        if (! $phone) {
            return response()->json(['message' => 'The phone number is not valid.', 'errors' => ['to' => ['Use international format, e.g. +254712345678.']]], 422);
        }
        if (Quota::smsRemaining($workspace) === 0) {
            return response()->json(['message' => 'The monthly SMS limit for this client has been reached.'], 429);
        }
        $provider = filled($data['provider'] ?? null)
            ? SmsProvider::availableTo($workspace)->where('uid', $data['provider'])->where('status', 'active')->first()
            : SmsProvider::defaultFor($workspace);
        if (! $provider) {
            return response()->json(['message' => 'No active SMS provider is available.'], 422);
        }

        $message = $sms->queue($workspace, $phone, $data['message'], 'api', null, $provider, $data['sender'] ?? null);

        return response()->json(['data' => $this->present($message->refresh())], 202);
    }

    public function show(string $uid): JsonResponse
    {
        $message = SmsMessage::where('workspace_id', Workspace::current()->id)->where('uid', $uid)->firstOrFail();

        return response()->json(['data' => $this->present($message)]);
    }

    /** @return array<string, mixed> */
    protected function present(SmsMessage $message): array
    {
        return [
            'id' => $message->uid, 'to' => $message->phone, 'status' => $message->status, 'parts' => $message->parts,
            'error' => $message->error, 'sent_at' => $message->sent_at?->toIso8601String(), 'delivered_at' => $message->delivered_at?->toIso8601String(),
        ];
    }
}
