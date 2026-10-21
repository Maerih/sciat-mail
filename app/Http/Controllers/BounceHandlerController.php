<?php

namespace App\Http\Controllers;

use App\Models\BounceHandler;
use App\Models\Workspace;
use App\Services\Bounces\BounceProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BounceHandlerController extends Controller
{
    public function index(): View
    {
        return view('sending.bounce-handlers', [
            'handlers' => BounceHandler::forWorkspace()->orderBy('name')->get(),
            'servers' => Workspace::current()->availableServers()->get()->filter->usesSmtp(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'host' => 'required|string|max:190',
            'port' => 'required|integer|between:1,65535',
            'encryption' => 'required|in:ssl,tls,none',
            'username' => 'required|string|max:190',
            'password' => 'required|string|max:255',
            'folder' => 'nullable|string|max:120',
        ]);

        BounceHandler::create($data + [
            'workspace_id' => Workspace::current()->id,
            'folder' => ($data['folder'] ?? null) ?: 'INBOX',
            'delete_processed' => $request->boolean('delete_processed'),
        ]);

        return back()->with('success', 'Bounce handler added. It is checked every 5 minutes — use “Check now” to test the connection.');
    }

    public function check(BounceHandler $bounceHandler, BounceProcessor $processor): RedirectResponse
    {
        $stats = $processor->process($bounceHandler);

        return $bounceHandler->last_error
            ? back()->with('error', 'Could not read the mailbox: '.$bounceHandler->last_error)
            : back()->with('success', "Checked: {$stats['read']} message(s) read, {$stats['bounces']} bounce(s), {$stats['complaints']} complaint(s), {$stats['unmatched']} not matched.");
    }

    public function destroy(BounceHandler $bounceHandler): RedirectResponse
    {
        $bounceHandler->delete();

        return back()->with('success', 'Bounce handler removed.');
    }
}
