<?php

namespace App\Providers;

use Anthropic\Client;
use App\Services\TaxRefund\ClaudeReceiptReader;
use App\Services\TaxRefund\ManualReceiptReader;
use App\Services\TaxRefund\ReceiptReader;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ReceiptReader::class, function (): ReceiptReader {
            if (! ClaudeReceiptReader::isConfigured()) {
                return new ManualReceiptReader;
            }

            return new ClaudeReceiptReader(
                new Client(apiKey: config('services.anthropic.key')),
                config('tax_refund.reader_models.quick'),
                config('tax_refund.reader_models.careful'),
                (float) config('tax_refund.reader_min_confidence'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every link, redirect and asset URL is https in production (the PWA, camera and
        // secure cookies all need it), even if the proxy talks to PHP over plain http.
        URL::forceHttps($this->app->isProduction());
    }
}
