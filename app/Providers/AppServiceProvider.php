<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $locale = (string) config('app.locale', 'ru');
        Carbon::setLocale($locale);
        CarbonImmutable::setLocale($locale);

        $previewMimes = config('livewire.temporary_file_upload.preview_mimes', []);

        if (is_array($previewMimes) && ! in_array('pdf', $previewMimes, true)) {
            $previewMimes[] = 'pdf';
            config(['livewire.temporary_file_upload.preview_mimes' => $previewMimes]);
        }
    }
}
