<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffRegistrationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_page_is_accessible(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Staff Registration')
            ->assertSeeLivewire('staff-registration');
    }
}
