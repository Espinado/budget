<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PersonalVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password', ['title' => __('Восстановление пароля — Family Money')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => __('Укажите почту.'),
            'email.email' => __('Почта выглядит неверно.'),
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => __('Подождите минуту и попробуйте снова.'),
            ]);
        }

        return back()->with('status', __('Если эта почта зарегистрирована, мы отправили ссылку для нового пароля.'));
    }

    public function edit(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.reset-password', [
            'title' => __('Новый пароль — Family Money'),
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => __('Укажите почту.'),
            'email.email' => __('Почта выглядит неверно.'),
            'password.required' => __('Придумайте пароль.'),
            'password.min' => __('Пароль не короче 8 символов.'),
            'password.confirmed' => __('Пароли не совпадают.'),
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill(['password' => $password])->save();
                PersonalVault::clearBlock($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('Ссылка устарела или уже использована. Запросите новую.'),
            ]);
        }

        $user = User::query()->where('email', $request->string('email'))->first();

        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            PersonalVault::lock();
        }

        return redirect()->route('dashboard');
    }
}
