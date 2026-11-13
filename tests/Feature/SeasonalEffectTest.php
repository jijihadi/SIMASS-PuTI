<?php

use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => 'web']);
});

test('auto mode follows the month', function (int $month, ?string $expected) {
    expect(Setting::seasonalEffect(Carbon::create(2026, $month, 15)))->toBe($expected);
})->with([
    [12, 'snow'], [9, 'leaves'], [10, 'leaves'],
    [11, 'rain'], [1, 'rain'], [3, 'rain'], [4, null], [8, null],
]);

test('manual mode overrides the month and off disables it', function () {
    $june = Carbon::create(2026, 6, 15);

    Setting::create(['key' => 'seasonal_effect', 'value' => 'snow']);
    expect(Setting::seasonalEffect($june))->toBe('snow');

    Setting::where('key', 'seasonal_effect')->update(['value' => 'off']);
    expect(Setting::seasonalEffect(Carbon::create(2026, 12, 15)))->toBeNull();
});

test('admin can change the seasonal effect', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-roles');

    $this->actingAs($admin)
        ->post(route('settings.seasonal'), ['seasonal_effect' => 'leaves'])
        ->assertRedirect();

    expect(Setting::where('key', 'seasonal_effect')->value('value'))->toBe('leaves');

    $this->actingAs($admin)
        ->post(route('settings.seasonal'), ['seasonal_effect' => 'fireworks'])
        ->assertSessionHasErrors('seasonal_effect');
});

test('effect renders on normal pages but not on the camera check-in page', function () {
    Setting::create(['key' => 'seasonal_effect', 'value' => 'snow']);

    $render = function (string $routeName) {
        $request = Request::create('/');
        $request->setRouteResolver(fn () => (new Route('GET', '/', []))->name($routeName));
        $this->app->instance('request', $request);

        return view('partials.seasonal-effect')->render();
    };

    expect($render('settings'))->toContain('id="seasonal-fx"')
        ->and($render('presence.index'))->not->toContain('seasonal-fx');
});

test('non-admin cannot change the seasonal effect', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('staff');

    $this->actingAs($user)
        ->post(route('settings.seasonal'), ['seasonal_effect' => 'off'])
        ->assertForbidden();

    expect(Setting::where('key', 'seasonal_effect')->exists())->toBeFalse();
});
