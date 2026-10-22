<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Team-side management of a client's read-only portal logins. */
class PortalUserController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'portal_name' => 'required|string|max:120',
            'portal_email' => 'required|email|unique:users,email',
            'portal_password' => ['required', Password::min(10)],
        ], [], ['portal_name' => 'name', 'portal_email' => 'email', 'portal_password' => 'password']);
        $workspace = Workspace::current();

        User::create([
            'name' => $data['portal_name'], 'email' => $data['portal_email'], 'password' => $data['portal_password'],
            'role' => 'client', 'workspace_id' => $workspace->id, 'current_workspace_id' => $workspace->id,
        ]);

        return back()->with('success', "Portal access created for {$data['portal_email']}. Share the login page and password with them securely.");
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($user->isClient() && $user->workspace_id === Workspace::current()->id, 404);
        $user->delete();

        return back()->with('success', 'Portal access removed.');
    }
}
