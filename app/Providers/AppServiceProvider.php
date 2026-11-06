<?php

namespace App\Providers;

use App\Support\CatalogCache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

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
        View::composer(
            [
                'admin.finalproject.form',
                'admin.import.form',
                'admin.visitor.form',
                'admin.submission.form',
                'admin.membership.form',
            ],
            function ($view) {
                $view->with('studyPrograms', CatalogCache::studyPrograms());
            }
        );

        View::composer(
            ['admin.navbar.sidebars', 'admin.submission.form', 'admin.import.form'],
            function ($view) {
                $view->with('finalProjectTypes', CatalogCache::finalProjectTypes());
            }
        );
    }
}
