<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class SetCurrentWorkspace
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace ?? Workspace::orderBy('name')->first();

        if (! $workspace) {
            return redirect()->route('workspaces.create');
        }

        if ($user->current_workspace_id !== $workspace->id) {
            $user->forceFill(['current_workspace_id' => $workspace->id])->save();
        }

        Workspace::setCurrent($workspace);
        View::share('currentWorkspace', $workspace);
        View::share('allWorkspaces', Workspace::orderBy('name')->get(['id', 'uid', 'name']));

        return $next($request);
    }
}
