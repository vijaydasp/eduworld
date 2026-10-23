<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());
        Gate::define('access-admin', fn (User $u) => $u->is_active && in_array($u->role, ['super_admin', 'admin', 'content_manager'], true));
        foreach (['manage-applications', 'manage-enquiries', 'manage-payments', 'view-reports'] as $ability) {
            Gate::define($ability, fn (User $u) => $u->is_active && in_array($u->role, ['super_admin', 'admin'], true));
        }
        Gate::define('manage-content', fn (User $u) => $u->is_active && in_array($u->role, ['super_admin', 'content_manager'], true));
        Gate::define('manage-settings', fn (User $u) => $u->is_active && $u->role === 'super_admin');
        Gate::define('manage-users', fn (User $u) => $u->is_active && $u->role === 'super_admin');
        foreach (['applications' => 5, 'contact' => 5, 'payments' => 5] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $r) => Limit::perMinute($attempts)->by($r->ip()));
        }
        RateLimiter::for('login', fn (Request $r) => [Limit::perMinute(20)->by($r->ip()), Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip())]);
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            URL::forceRootUrl(config('app.url'));
        }
    }
}
