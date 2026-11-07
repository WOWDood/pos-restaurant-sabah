<?php

namespace App\Providers;

use App\Models\BusinessSetting;
use App\Models\StaffRole;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        Gate::define('manage-users', fn (User $user): bool => $user->isAdministrator());
        Gate::define('manage-shop', fn (User $user): bool => $user->isAdministrator());
        foreach (array_keys(StaffRole::PERMISSIONS) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }
        View::composer('*', function (\Illuminate\View\View $view): void {
            if (! str_ends_with(str_replace('\\', '/', $view->getPath()), '/resources/views/layouts/app/sidebar.blade.php')) {
                return;
            }
            $settings = BusinessSetting::settings();
            $view->with(['shopSettings' => $settings, 'shopLogoUrl' => BusinessSetting::logoUrl($settings)]);
        });
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
