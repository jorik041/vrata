<?php

namespace App\Providers;

use App\Http\Request;
use App\Routing\RouteRegistry;
use App\Services\DNSRegistry;
use App\Services\ServiceRegistryContract;
use Dusterio\LumenPassport\LumenPassport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\File\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton(RouteRegistry::class, function() {
            return RouteRegistry::initFromFile('routes.json');
        });

        $this->app->singleton(Request::class, function () {
            return $this->prepareRequest(Request::capture());
        });

        $this->app->bind(ServiceRegistryContract::class, DNSRegistry::class);

        $this->app->singleton(Client::class, function() {
            return new Client([
                'timeout' => Config::get('gateway.global.timeout'),
                'connect_timeout' => Config::get('gateway.global.connect_timeout', Config::get('gateway.global.timeout'))
            ]);
        });

        $this->app->alias(Request::class, 'request');

        $this->registerRoutes();

        Passport::tokensExpireIn(Carbon::now()->addDays(15));
        Passport::refreshTokensExpireIn(Carbon::now()->addDays(30));
        LumenPassport::allowMultipleTokens();
    }

    /**
     * Prepare the given request instance for use with the application.
     *
     * @param   Request $request
     * @return  Request
     */
    protected function prepareRequest(Request $request)
    {
        $request->setUserResolver(function () {
            return $this->app->make('auth')->user();
        })->setRouteResolver(function () {
            return $this->app->currentRoute;
        })->setTrustedProxies([
            '10.7.0.0/16'
        ]);

        return $request;
    }

    /**
     * @return void
     */
    protected function registerRoutes()
    {
        $registry = $this->app->make(RouteRegistry::class);

        if ($registry->isEmpty()) {
            Log::info('Not adding any service routes - route file is missing');
            return;
        }

        $registry->bind(app());
    }
}
