<?php

namespace Tests\Feature;

use Tests\TestCase;

class FrontendArchitectureTest extends TestCase
{
    public function test_frontend_decision_uses_committed_static_assets_without_a_bundler(): void
    {
        $this->assertFileExists(base_path('FRONTEND.md'));
        $this->assertFileExists(public_path('css/marine.css'));
        $this->assertFileDoesNotExist(base_path('package.json'));
        $this->assertFileDoesNotExist(base_path('package-lock.json'));
        $this->assertFileDoesNotExist(base_path('vite.config.js'));
        $this->assertFileDoesNotExist(base_path('vite.config.ts'));

        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString("asset('css/marine.css')", $layout);
        $this->assertStringNotContainsString('@vite', $layout);
    }

    public function test_interactive_map_assets_are_pinned_and_source_status_is_accessible(): void
    {
        $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));
        $navigation = file_get_contents(resource_path('views/navigation.blade.php'));
        $mapPartial = file_get_contents(resource_path('views/partials/marine-map.blade.php'));
        $mapScript = file_get_contents(public_path('js/marine-map.js'));

        foreach ([$dashboard, $navigation] as $view) {
            $this->assertIsString($view);
            $this->assertStringContainsString('leaflet@1.9.4', $view);
            $this->assertStringContainsString('sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=', $view);
            $this->assertStringContainsString('sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=', $view);
        }

        $this->assertIsString($mapPartial);
        $this->assertIsString($mapScript);
        $this->assertStringContainsString('aria-live="polite"', $mapPartial);
        $this->assertStringContainsString('role="region"', $mapPartial);
        $this->assertStringContainsString('openstreetmap.org/copyright', $mapPartial);
        $this->assertStringContainsString('state.textContent = source.status', $mapScript);
        $this->assertStringNotContainsString('map.fitBounds(', $mapScript);
        $this->assertStringNotContainsString('map.setView([p.coords.latitude', $mapScript);
    }

    public function test_static_layout_retains_baseline_accessibility_features(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $stylesheet = file_get_contents(public_path('css/marine.css'));

        $this->assertIsString($layout);
        $this->assertIsString($stylesheet);
        $this->assertStringContainsString('href="#main"', $layout);
        $this->assertStringContainsString('aria-label="Primary"', $layout);
        $this->assertStringContainsString('role="status"', $layout);
        $this->assertStringContainsString(':focus-visible', $stylesheet);
        $this->assertStringContainsString('@media(prefers-reduced-motion:reduce)', $stylesheet);
    }
}
