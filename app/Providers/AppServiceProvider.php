<?php

namespace App\Providers;

use App\Models\IdRecord;
use App\Models\User;
use App\Policies\IdRecordPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::policy(IdRecord::class, IdRecordPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        // Strictly administrator-only actions (user management, trashed records, etc.)
        Gate::define('admin-only', function (User $user) {
            return $user->isAdmin() && $user->isActive();
        });

        // Actions available to both Administrator and ID Staff
        Gate::define('staff-or-admin', function (User $user) {
            return $user->isActive() && ($user->isAdmin() || $user->isIdStaff());
        });
    }
}
