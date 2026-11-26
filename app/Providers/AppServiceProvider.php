<?php

namespace App\Providers;

use App\Models\Document;
use App\Models\Folder;
use App\Policies\DocumentPolicy;
use App\Policies\FolderPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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
        Paginator::useBootstrapFive();

        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(Document::class, DocumentPolicy::class);
    }
}
