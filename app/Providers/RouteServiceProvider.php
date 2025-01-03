<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map(Request $request)
    {

      $locale = $request->segment(1);
       $this->app->setLocale($locale);

        $this->mapApiRoutes($locale);

        $this->mapWebRoutes($locale);

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes($locale)
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->prefix($locale)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes($locale)
    {
        Route::middleware('api')
             ->namespace($this->namespace)
             ->prefix($locale.'/'.env('URL_API','api'))
             ->group(base_path('routes/api.php'));
    }
}
