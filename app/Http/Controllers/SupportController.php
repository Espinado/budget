<?php

namespace App\Http\Controllers;

use App\Mail\SupportRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function help(): View
    {
        return view('help', ['title' => __('Помощь — Family Money')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('support', [
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['nullable', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
        ], [
            'email.required' => __('Укажите почту.'),
            'email.email' => __('Почта выглядит неверно.'),
            'message.required' => __('Напишите сообщение.'),
        ]);

        Mail::to((string) config('mail.support_address'))->send(new SupportRequest(
            senderEmail: $data['email'],
            topic: trim((string) ($data['subject'] ?? '')),
            text: $data['message'],
        ));

        return back()->with('support_sent', __('Сообщение отправлено. Мы ответим на эту почту.'));
    }
}
