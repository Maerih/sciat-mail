<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class TeamController extends Controller
{
    public function index()
    {
        return view('team.index', ['users' => User::where('role', '!=', 'client')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(10)],
            'role' => 'required|in:admin,member',
        ]);
        User::create($data);

        return back()->with('success', 'Team member added.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'You cannot remove yourself.');
        $user->delete();

        return back()->with('success', 'Team member removed.');
    }
}
