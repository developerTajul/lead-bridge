<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\UserLoginRequest;
use Core\Auth\Application\Contracts\AuthMapperContract;
use Core\Auth\Application\Services\AuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuthMapperContract $mapper,
    ) {}

    /**
     * Shows the login form.
     */
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticates a user and starts their session.
     */
    public function login(UserLoginRequest $request): RedirectResponse
    {
        $userLoginDTO = $this->mapper->mapToLoginDTO($request->validated());


        $result = $this->authService->login($userLoginDTO);


        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        Auth::loginUsingId($result->getData(), $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', $result->getMessage());
    }

    /**
     * Shows the registration form.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Saves a new user registration.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $data = $this->mapper->mapToRegisterDTO($request->validated());

        $result = $this->authService->register($data);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('login')->with('success', $result->getMessage());
    }

    /**
     * Logs the user out.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }
}
