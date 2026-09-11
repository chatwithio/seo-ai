<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
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

    public function test_registration_page_can_be_rendered_and_contains_phone_input(): void
    {
        $response = $this->get('/users/create');

        $response->assertStatus(200);
        $response->assertSee('Phone Number');
        $response->assertSee('name="phone"', false);
        $response->assertSee('type="tel"', false);
        $response->assertSee('required', false);
    }

    public function test_registration_requires_phone_number(): void
    {
        $response = $this->from('/users/create')->post('/users/create', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/users/create');
        $response->assertSessionHasErrors(['phone']);
        $this->assertGuest();
    }

    public function test_registration_rejects_invalid_phone_format(): void
    {
        $response = $this->from('/users/create')->post('/users/create', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '123',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/users/create');
        $response->assertSessionHasErrors(['phone']);
        $this->assertGuest();
    }

    public function test_registration_rejects_alphanumeric_or_invalid_characters_in_phone(): void
    {
        $response = $this->from('/users/create')->post('/users/create', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+1-555-CALL-NOW',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/users/create');
        $response->assertSessionHasErrors(['phone']);
        $this->assertGuest();
    }

    public function test_registration_preserves_old_phone_input_on_validation_failure(): void
    {
        $response = $this->from('/users/create')->post('/users/create', [
            'name' => 'Jane Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '+1 555-987-6543',
            'password' => 'Password123!',
            'password_confirmation' => 'Mismatch123!',
        ]);

        $response->assertRedirect('/users/create');
        $response->assertSessionHasErrors(['password']);
        $response->assertSessionHas('_old_input.phone', '+1 555-987-6543');
        $this->assertGuest();
    }

    public function test_user_can_register_successfully_with_mandatory_phone(): void
    {
        $email = 'registered_' . uniqid() . '@example.com';
        $phone = '+1 (555) 234-5678';

        $response = $this->post('/users/create', [
            'name' => 'John Valid',
            'email' => $email,
            'phone' => $phone,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticated();

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user);
        $this->assertEquals($phone, $user->phone);
        $this->assertEquals($phone, $user->phone_number);
    }
}
