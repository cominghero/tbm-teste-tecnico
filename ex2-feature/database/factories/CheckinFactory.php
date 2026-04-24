<?php

namespace Database\Factories;

use App\Models\Checkin;
use App\Models\Paciente;
use App\Models\Profissional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class CheckinFactory extends Factory
{
    protected $model = Checkin::class;

    public function definition(): array
    {
        $tenant = Tenant::factory();

        return [
            'tenant_id' => $tenant,
            'profissional_id' => Profissional::factory()->for($tenant),
            'paciente_id' => Paciente::factory()->for($tenant),
            'latitude' => $this->faker->latitude(),
            'longitude' => $this->faker->longitude(),
            'checked_in_at' => now(),
        ];
    }
}
