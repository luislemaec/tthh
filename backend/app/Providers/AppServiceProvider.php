<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

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
	Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        RateLimiter::for('sit-login', fn (Request $request) => [
            Limit::perMinute(30)->by('sit-ip:'.$request->ip()),
            Limit::perMinute(5)->by('sit-user:'.hash('sha256', trim((string) $request->input('identificacion')))),
        ]);
    
    }
}
