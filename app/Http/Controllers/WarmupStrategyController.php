<?php

namespace App\Http\Controllers;

use App\Models\WarmupStrategy;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarmupStrategyController extends Controller
{
    public function index(): View
    {
        $strategies = WarmupStrategy::availableTo(Workspace::current())
            ->withCount('servers')->orderByRaw('workspace_id is null desc')->orderBy('name')->get();
        $servers = Workspace::current()->availableServers()->whereNotNull('warmup_strategy_id')->with('warmupStrategy')->get();

        return view('sending.warmup.index', compact('strategies', 'servers'));
    }

    public function create(): View
    {
        return view('sending.warmup.form', ['strategy' => new WarmupStrategy(['curve' => 'exponential', 'initial_daily' => 100, 'target_daily' => 20000, 'days' => 21])]);
    }

    public function store(Request $request): RedirectResponse
    {
        WarmupStrategy::create($this->validated($request) + ['workspace_id' => Workspace::current()->id]);

        return redirect()->route('sending.warmup.index')->with('success', 'Warmup strategy saved.');
    }

    public function edit(WarmupStrategy $warmup): View|RedirectResponse
    {
        abort_if($warmup->isPreset(), 403, 'Built-in presets cannot be edited — create your own instead.');

        return view('sending.warmup.form', ['strategy' => $warmup]);
    }

    public function update(Request $request, WarmupStrategy $warmup): RedirectResponse
    {
        abort_if($warmup->isPreset(), 403);
        $warmup->update($this->validated($request));

        return redirect()->route('sending.warmup.index')->with('success', 'Warmup strategy saved.');
    }

    public function destroy(WarmupStrategy $warmup): RedirectResponse
    {
        abort_if($warmup->isPreset(), 403);
        $warmup->delete();

        return redirect()->route('sending.warmup.index')->with('success', 'Warmup strategy deleted.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'curve' => ['required', Rule::in(array_keys(WarmupStrategy::CURVES))],
            'initial_daily' => 'required|integer|min:1|max:1000000',
            'target_daily' => 'required|integer|gt:initial_daily|max:10000000',
            'days' => 'required|integer|min:2|max:120',
        ]);
    }
}
