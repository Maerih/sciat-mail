<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'current_password' => 'required_with:password|current_password',
            'password' => ['nullable', 'confirmed', Password::min(10)],
        ]);

        $user->fill(collect($data)->only(['name', 'email'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('success', 'Profile updated.');
    }
}
