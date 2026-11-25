<?php

namespace App\Support;

use App\Models\User;

final class PersonalVault
{
    public const SESSION_KEY = 'vault.unlocked';

    public const MAX_ATTEMPTS = 3;

    public static function unlock(): void
    {
        $user = self::user();

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'vault_failed_attempts' => 0,
            'vault_blocked_at' => null,
        ])->save();

        session([self::SESSION_KEY => (int) $user->id]);
    }

    public static function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function isUnlocked(): bool
    {
        $user = self::user();

        return $user !== null
            && ! self::isBlocked($user)
            && (int) session(self::SESSION_KEY) === (int) $user->id;
    }

    public static function isBlocked(?User $user = null): bool
    {
        $user ??= self::user();

        return $user !== null && $user->vault_blocked_at !== null;
    }

    public static function attempts(?User $user = null): int
    {
        $user ??= self::user();

        return $user === null ? 0 : max(0, (int) $user->vault_failed_attempts);
    }

    public static function remainingAttempts(?User $user = null): int
    {
        return max(0, self::MAX_ATTEMPTS - self::attempts($user));
    }

    public static function registerFailure(): void
    {
        $user = self::user();

        if ($user === null) {
            return;
        }

        $attempts = self::attempts($user) + 1;
        $fields = ['vault_failed_attempts' => $attempts];

        if ($attempts >= self::MAX_ATTEMPTS) {
            $fields['vault_blocked_at'] = now();
            self::lock();
        }

        $user->forceFill($fields)->save();
    }

    public static function clearBlock(?User $user = null): void
    {
        $user ??= self::user();

        if ($user === null) {
            return;
        }

        $user->forceFill([
            'vault_failed_attempts' => 0,
            'vault_blocked_at' => null,
        ])->save();
    }

    public static function clearFailures(): void
    {
        self::clearBlock();
    }

    private static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
