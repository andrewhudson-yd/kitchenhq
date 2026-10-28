<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm()
    {
        return view('auth', ['register' => false]);
    }

    public function registerForm()
    {
        return view('auth', ['register' => true]);
    }

    public function register(Request $r, HouseholdSetup $setup)
    {
        $r->merge(['email' => mb_strtolower((string) $r->input('email'))]);
        $data = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => ['required', 'confirmed', Password::min(10)], 'household_name' => 'nullable|string|max:100', 'join_code' => 'nullable|string|size:32']);
        $user = DB::transaction(function () use ($data, $setup) {
            if (! empty($data['join_code'])) {
                $h = Household::where('join_code', hash('sha256', $data['join_code']))->first();
                if (! $h) {
                    throw ValidationException::withMessages(['join_code' => 'That invitation code is invalid or has been replaced.']);
                }
            } else {
                $h = Household::create(['name' => ($data['household_name'] ?? null) ?: $data['name'].'’s kitchen', 'join_code' => hash('sha256', Str::random(32))]);
                $setup->seed($h);
            }

            return User::create(['name' => $data['name'], 'email' => mb_strtolower($data['email']), 'password' => $data['password'], 'household_id' => $h->id]);
        });
        Auth::login($user);
        $r->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $data['email'] = mb_strtolower($data['email']);
        if (! Auth::attempt($data, $r->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Those details did not match an account.']);
        }
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        // Signing out also signs the phone app's offline screens out.
        DB::table('app_tokens')->where('user_id', $r->user()->id)->delete();
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
