<?php

use App\Enums\RoleEnum;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $this->get(route('guide'))->assertRedirect(route('login'));
});

test('every role can open the user guide', function (RoleEnum $role) {
    $this->actingAs(userWithRole($role))
        ->get(route('guide'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('guide'));
})->with(RoleEnum::cases());
