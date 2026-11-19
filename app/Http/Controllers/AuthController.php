<?php

namespace App\Http\Controllers;

use App\Setup\SignIn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Login page. Active role sessions are kept
     * so another role can sign in from the same browser.
     */
    public function showLogin(SignIn $signIn): View
    {
        return $signIn->showLogin();
    }

    /**
     * Registration page, only for bootstrap, before any officer or coordinator exists.
     */
    public function showRegister(SignIn $signIn): View|RedirectResponse
    {
        return $signIn->showRegister();
    }

    /**
     * Save the first bootstrap account (always an officer). Later accounts use the Users menu.
     */
    public function register(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->register($request);
    }

    /**
     * Authenticate name and password into the guard for that role.
     * Other role guards in the same browser stay signed in.
     */
    public function login(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->login($request);
    }

    /**
     * Go to the home page of a role already active in this browser.
     */
    public function activate(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->activate($request);
    }

    /**
     * Sign out of one role, or of all roles when guard=all or the guard is invalid.
     */
    public function logout(Request $request, SignIn $signIn): RedirectResponse
    {
        return $signIn->logout($request);
    }
}
