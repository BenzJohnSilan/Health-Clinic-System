<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegisterAgeLimitTest extends TestCase
{
    public function test_register_page_limits_birthdate_to_adults(): void
    {
        $max = now()->subYearsNoOverflow(18)->toDateString();

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('max="' . $max . '"', false)
            ->assertSee('You must be 18 years old or above to register.');
    }

    public function test_under_18_birthdate_is_rejected(): void
    {
        $birthdate = now()->subYearsNoOverflow(18)->addDay()->toDateString();

        $this->from(route('register'))
            ->post(route('register.store'), ['birthdate' => $birthdate])
            ->assertSessionHasErrors(['birthdate' => 'You must be at least 18 years old to register.']);
    }

    public function test_exactly_18_birthdate_passes_the_age_rule(): void
    {
        $birthdate = now()->subYearsNoOverflow(18)->toDateString();

        $this->from(route('register'))
            ->post(route('register.store'), ['birthdate' => $birthdate])
            ->assertSessionDoesntHaveErrors('birthdate');
    }
}