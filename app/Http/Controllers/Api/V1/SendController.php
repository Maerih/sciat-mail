<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Blacklist;
use App\Models\Template;
use App\Models\Workspace;
use App\Sending\MergeTags;
use App\Sending\SendFailed;
use App\Services\SystemMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Transactional email (receipts, password resets, alerts) from your own application. */
class SendController extends Controller
{
    public function __invoke(Request $request, SystemMailer $mailer): JsonResponse
    {
        $data = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'html' => 'required_without_all:template,text|nullable|string',
            'text' => 'nullable|string',
            'template' => 'nullable|string',
            'data' => 'sometimes|array',
            'from_email' => 'nullable|email',
            'from_name' => 'nullable|string|max:190',
        ]);
        $workspace = Workspace::current();

        if (Blacklist::isBlocked($data['to'], $workspace->id)) {
            return response()->json(['message' => 'The recipient is on the blacklist.'], 422);
        }

        $html = $data['html'] ?? null;
        if (! empty($data['template'])) {
            $html = Template::availableTo($workspace)->where('uid', $data['template'])->firstOrFail()->html;
        }
        $html ??= '<pre style="font-family:inherit;white-space:pre-wrap">'.e($data['text']).'</pre>';

        $values = collect($data['data'] ?? [])->mapWithKeys(fn ($v, $k) => [strtoupper((string) $k) => is_scalar($v) ? (string) $v : ''])->all();
        $html = MergeTags::render($html, $values);
        $subject = MergeTags::render($data['subject'], $values, false);

        $fromEmail = $data['from_email'] ?? $workspace->contact_email;
        if (! $fromEmail) {
            return response()->json(['message' => 'Pass from_email or set a contact email in the client settings.'], 422);
        }

        try {
            $delivery = $mailer->send($workspace, $data['to'], $subject, $html, $fromEmail, $data['from_name'] ?? ($workspace->company ?: $workspace->name), kind: 'transactional');
        } catch (SendFailed $e) {
            return response()->json(['message' => 'Sending failed: '.$e->getMessage()], 502);
        }

        return response()->json(['id' => $delivery->uid, 'status' => $delivery->status], 202);
    }
}
