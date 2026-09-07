<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function portalGuest(): bool
    {
        return Auth::guard('portal')->guest();
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_client_can_authenticate_by_full_name_ignoring_case(): void
    {
        $client = Client::create(['name' => 'LUIS SOSA LARIOS']);

        $response = $this->post('/login', ['email' => 'luis sosa larios']);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame($client->id, Auth::guard('portal')->id());
    }

    public function test_client_can_authenticate_by_rfc_ignoring_case(): void
    {
        $client = Client::create([
            'name' => 'ALBERTO SEGURA ARRIETA',
            'tax_id' => 'SEAA760112QF5',
        ]);

        $this->post('/login', ['email' => 'seaa760112qf5']);

        $this->assertSame($client->id, Auth::guard('portal')->id());
    }

    public function test_client_can_authenticate_by_contact_email(): void
    {
        $client = Client::create(['name' => 'Cliente Demo']);
        Contact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'email' => 'cliente@correo.com',
            'is_primary' => true,
        ]);

        $this->post('/login', ['email' => 'CLIENTE@CORREO.COM']);

        $this->assertSame($client->id, Auth::guard('portal')->id());
    }

    public function test_client_can_authenticate_by_contact_phone_ignoring_format(): void
    {
        $client = Client::create(['name' => 'Cliente Demo']);
        Contact::create([
            'contactable_type' => Client::class,
            'contactable_id' => $client->id,
            'phone' => '55 1234 5678',
            'is_primary' => true,
        ]);

        $this->post('/login', ['email' => '5512345678']);

        $this->assertSame($client->id, Auth::guard('portal')->id());
    }

    public function test_client_can_authenticate_by_contact_person_name(): void
    {
        $client = Client::create([
            'name' => 'LUIS SOSA LARIOS',
            'contact_person' => 'Alicia Larios Flores',
        ]);

        $this->post('/login', ['email' => 'alicia larios flores']);

        $this->assertSame($client->id, Auth::guard('portal')->id());
    }

    public function test_users_of_the_erp_cannot_log_into_the_portal(): void
    {
        $employee = User::factory()->create();

        $this->post('/login', ['email' => $employee->email]);

        $this->assertTrue($this->portalGuest());
    }

    public function test_unknown_identifier_is_rejected(): void
    {
        $this->post('/login', ['email' => 'nobody@example.com']);

        $this->assertTrue($this->portalGuest());
    }

    public function test_ambiguous_client_name_is_rejected(): void
    {
        Client::create(['name' => 'CLIENTE GENERICO']);
        Client::create(['name' => 'CLIENTE GENERICO']);

        $this->post('/login', ['email' => 'cliente generico']);

        $this->assertTrue($this->portalGuest());
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
    }

    public function test_authenticated_client_can_open_dashboard_and_services(): void
    {
        $client = Client::create(['name' => 'Cliente Demo']);

        $this->actingAs($client, 'portal')
            ->get('/dashboard')
            ->assertStatus(200);

        $this->actingAs($client, 'portal')
            ->get('/servicios')
            ->assertStatus(200);
    }

    public function test_logout_redirects_to_login(): void
    {
        $client = Client::create(['name' => 'Cliente Demo']);

        $response = $this->actingAs($client, 'portal')
            ->post('/logout');

        $response->assertRedirect(route('login', absolute: false));
        $this->assertTrue($this->portalGuest());
    }

    public function test_root_shows_loading_screen(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
