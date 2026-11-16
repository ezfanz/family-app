<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class HttpsBehindProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        URL::forceHttps(false);
        parent::tearDown();
    }

    public function test_links_use_https_when_the_proxy_says_the_visitor_used_https(): void
    {
        config(['app.url' => 'http://trip.example.com']);

        $this->withServerVariables(['REMOTE_ADDR' => '172.70.1.1', 'HTTP_X_FORWARDED_PROTO' => 'https', 'HTTP_X_FORWARDED_HOST' => 'trip.example.com'])
            ->get('/login')
            ->assertOk()
            ->assertSee('action="https://trip.example.com/login"', false);
    }

    public function test_production_always_generates_https_links(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        (new AppServiceProvider($this->app))->boot();

        $this->assertStringStartsWith('https://', url('/trips'));
    }

    public function test_outside_production_a_plain_http_request_stays_http(): void
    {
        (new AppServiceProvider($this->app))->boot();

        $this->get('http://localhost/login')
            ->assertOk()
            ->assertSee('action="http://localhost/login"', false);
    }
}
