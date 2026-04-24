<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_professional_checks_in_successfully(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $profissional = Profissional::factory()->for($tenant)->create();
        $paciente = Paciente::factory()->for($tenant)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => $profissional->id,
            'paciente_id' => $paciente->id,
            'latitude' => -29.91869,
            'longitude' => -51.18094,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.profissional.id', $profissional->id)
            ->assertJsonPath('data.paciente.id', $paciente->id)
            ->assertJsonPath('data.latitude', -29.91869)
            ->assertJsonPath('data.longitude', -51.18094)
            ->assertJsonMissingPath('data.tenant_id');

        $this->assertDatabaseHas('checkins', [
            'tenant_id' => $tenant->id,
            'profissional_id' => $profissional->id,
            'paciente_id' => $paciente->id,
        ]);
    }

    public function test_cannot_check_in_paciente_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $user = User::factory()->for($tenantA)->create();
        $profissional = Profissional::factory()->for($tenantA)->create();
        $pacienteFromOtherTenant = Paciente::factory()->for($tenantB)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => $profissional->id,
            'paciente_id' => $pacienteFromOtherTenant->id,
            'latitude' => -29.91869,
            'longitude' => -51.18094,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('paciente_id');

        $this->assertDatabaseCount('checkins', 0);
    }

    public function test_cannot_check_in_with_profissional_from_another_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $user = User::factory()->for($tenantA)->create();
        $profissionalFromOtherTenant = Profissional::factory()->for($tenantB)->create();
        $paciente = Paciente::factory()->for($tenantA)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => $profissionalFromOtherTenant->id,
            'paciente_id' => $paciente->id,
            'latitude' => -29.91869,
            'longitude' => -51.18094,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('profissional_id');

        $this->assertDatabaseCount('checkins', 0);
    }

    public function test_inactive_profissional_cannot_check_in(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $profissional = Profissional::factory()->for($tenant)->inativo()->create();
        $paciente = Paciente::factory()->for($tenant)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => $profissional->id,
            'paciente_id' => $paciente->id,
            'latitude' => -29.91869,
            'longitude' => -51.18094,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('profissional_id');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => 1,
            'paciente_id' => 1,
            'latitude' => 0,
            'longitude' => 0,
        ]);

        $response->assertStatus(401);
    }

    public function test_coordinates_out_of_range_return_422(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();
        $profissional = Profissional::factory()->for($tenant)->create();
        $paciente = Paciente::factory()->for($tenant)->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/checkin', [
            'profissional_id' => $profissional->id,
            'paciente_id' => $paciente->id,
            'latitude' => 95,
            'longitude' => -200,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
