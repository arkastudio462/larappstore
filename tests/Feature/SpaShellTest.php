<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaShellTest extends TestCase
{
    public function test_home_returns_the_spa_shell(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewIs('app');
        $response->assertSee('id="app"', false);
        $response->assertSee('build/assets/', false);
    }

    public function test_unknown_paths_fall_back_to_the_spa_shell(): void
    {
        $response = $this->get('/kategori/produk');

        $response->assertOk();
        $response->assertViewIs('app');
    }

    public function test_shell_bootstraps_the_design_theme(): void
    {
        $response = $this->get('/');

        $response->assertSee('data-theme="dark"', false);
        $response->assertSee("localStorage.getItem('appfeed-theme')", false);
        $response->assertSee('viewport-fit=cover', false);
        $response->assertSee('bg-page font-sans text-sm text-tx antialiased', false);
    }
}
