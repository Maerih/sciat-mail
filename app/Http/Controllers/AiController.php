<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Workspace;
use App\Services\Ai\AiAssistant;
use App\Services\Ai\AiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AiController extends Controller
{
    public function subjects(Request $request, AiAssistant $ai): JsonResponse
    {
        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:200000',
            'audience' => 'nullable|string|max:255',
            'count' => 'nullable|integer|min:2|max:10',
        ]);
        $workspace = Workspace::current();

        return $this->respond(fn () => ['suggestions' => $ai->subjectLines($workspace, $data + ['brand' => $workspace->company ?: $workspace->name], (int) ($data['count'] ?? 6))]);
    }

    public function rewrite(Request $request, AiAssistant $ai): JsonResponse
    {
        $data = $request->validate([
            'text' => 'required|string|max:20000',
            'instruction' => 'required|string|max:300',
        ]);

        return $this->respond(fn () => ['text' => $ai->rewrite(Workspace::current(), $data['text'], $data['instruction'])]);
    }

    public function draft(Request $request, AiAssistant $ai): JsonResponse
    {
        $data = $request->validate([
            'brief' => 'required|string|min:10|max:4000',
            'tone' => 'nullable|in:friendly,professional,playful,urgent,inspirational',
            'length' => 'nullable|in:short,medium,long',
            'cta_text' => 'nullable|string|max:60',
            'cta_url' => 'nullable|url|max:500',
        ]);

        return $this->respond(fn () => $ai->draftEmail(Workspace::current(), $data));
    }

    public function settings(): View
    {
        return view('ai-settings', [
            'provider' => Setting::get('ai_provider'),
            'model' => Setting::get('ai_model'),
            'baseUrl' => Setting::get('ai_base_url'),
            'hasKey' => filled(Setting::get('ai_api_key')),
            'usage' => DB::table('ai_logs')->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('count(*) as total, sum(success = 0) as failed')->first(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ai_provider' => ['nullable', Rule::in(array_keys(AiAssistant::PROVIDERS))],
            'ai_api_key' => 'nullable|string|max:500',
            'ai_model' => [Rule::requiredIf(in_array($request->ai_provider, ['openai', 'ollama'], true)), 'nullable', 'string', 'max:100'],
            'ai_base_url' => [Rule::requiredIf($request->ai_provider === 'ollama'), 'nullable', 'url', 'max:255'],
        ]);

        $values = ['ai_provider' => $data['ai_provider'] ?? null, 'ai_model' => $data['ai_model'] ?? null, 'ai_base_url' => $data['ai_base_url'] ?? null];
        if (filled($data['ai_api_key'] ?? null)) {
            $values['ai_api_key'] = Crypt::encryptString($data['ai_api_key']); // blank keeps the stored key
        }
        Setting::put($values);

        return back()->with('success', 'AI assistant settings saved.');
    }

    protected function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
