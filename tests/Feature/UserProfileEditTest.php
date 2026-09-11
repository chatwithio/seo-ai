<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class UserProfileEditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        putenv('DB_CONNECTION=mysql');
        putenv('DB_DATABASE=chatwithseo_ai');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_ENV['DB_DATABASE'] = 'chatwithseo_ai';
        $_SERVER['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_DATABASE'] = 'chatwithseo_ai';

        parent::setUp();
    }

    public function test_unauthenticated_user_cannot_access_profile_page(): void
    {
        $response = $this->get('/admin/profile');

        $response->assertRedirect('/users/login');
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Profile User',
            'email' => 'profile_' . uniqid() . '@example.com',
            'phone' => '+1 (555) 777-8888',
        ]);

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertStatus(200);
        $response->assertSee('Profile User');
        $response->assertSee('+1 (555) 777-8888');
        $response->assertSee('admin/profile');
    }

    public function test_user_menu_dropdown_contains_name_and_profile_button(): void
    {
        $email = 'topbar_' . uniqid() . '@example.com';
        $user = User::factory()->create([
            'name' => 'Topbar Test User',
            'email' => $email,
            'phone' => '+1 (555) 123-9999',
        ]);

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertStatus(200);
        $response->assertSee('Topbar Test User');
        $response->assertSee($email);
        $response->assertSee(filament()->getProfileUrl());
    }

    public function test_user_can_update_profile_information_including_phone(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original_' . uniqid() . '@example.com',
            'phone' => '+1 (555) 111-2222',
        ]);

        $newPhone = '+1 (555) 333-4444';
        $newName = 'Updated Name';

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('data.name', $newName)
            ->set('data.phone', $newPhone)
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals($newName, $user->name);
        $this->assertEquals($newPhone, $user->phone);
    }

    public function test_profile_update_validates_phone_number(): void
    {
        $user = User::factory()->create([
            'name' => 'Validation User',
            'email' => 'val_' . uniqid() . '@example.com',
            'phone' => '+1 (555) 111-2222',
        ]);

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->set('data.phone', 'invalid-phone-num')
            ->call('save')
            ->assertHasErrors(['data.phone']);
    }
}
