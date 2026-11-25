<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use App\Support\DefaultCategories;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register', ['title' => __('Регистрация — Family Money')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => __('Напишите имя.'),
            'email.required' => __('Укажите почту.'),
            'email.email' => __('Почта выглядит неверно.'),
            'email.unique' => __('Эта почта уже зарегистрирована.'),
            'password.required' => __('Придумайте пароль.'),
            'password.min' => __('Пароль не короче 8 символов.'),
            'password.confirmed' => __('Пароли не совпадают.'),
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $household = Household::query()->create([
                'name' => __('Наша семья'),
                'currency' => 'EUR',
                'timezone' => 'Europe/Riga',
                'anchor_day' => 1,
            ]);

            $user->households()->attach($household->id, ['role' => 'owner']);

            foreach ([...Account::defaults(), Account::piggyBank()] as $account) {
                $household->accounts()->create([
                    'name' => $account['name'],
                    'kind' => $account['kind'],
                    'role' => $account['role'],
                    'balance_cents' => 0,
                    'sort' => $account['sort'],
                ]);
            }

            DefaultCategories::install($household);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('show_opening_balances', true);

        return redirect()->route('opening');
    }
}
