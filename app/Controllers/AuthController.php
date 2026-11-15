<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Agus\Controller;
use Agus\Http\Request;
use Agus\Http\Response;
use Agus\Support\Auth;
use Agus\Support\Session;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return $this->view('auth/login', ['title' => 'Sign in']);
    }

    public function login(Request $request): Response
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($data['email'], $data['password'])) {
            return Response::back()
                ->withInput($request->only(['email']))
                ->withErrors(['email' => ['Those details don\'t match an account.']]);
        }

        $to = Session::pull('intended', (string) config('app.auth.home_path', '/dashboard'));
        return $this->redirect($to)->with('success', 'Welcome back, ' . Auth::user()['name'] . '!');
    }

    public function showRegister(): Response
    {
        return $this->view('auth/register', ['title' => 'Create account']);
    }

    public function register(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|max:80',
            'email' => 'required|email|max:190|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Auth::hash($data['password']),
            'role' => 'customer',
        ]);

        Auth::login($user->toArray());
        return $this->redirect(route('home'))->with('success', 'Account created. Welcome to Replate, ' . $user->name . '!');
    }

    public function logout(): Response
    {
        Auth::logout();
        return $this->redirect('/')->with('success', 'You are signed out.');
    }
}
