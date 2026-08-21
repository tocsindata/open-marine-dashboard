<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomeTest extends TestCase
{
    public function test_the_foundation_page_is_available_and_contains_the_safety_notice(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Your vessel records and voyage work')
            ->assertSee('supplementary planning and recordkeeping tool')
            ->assertSee('West coast Canada');
    }

    public function test_web_responses_include_baseline_security_headers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; form-action 'self'")
            ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_the_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
