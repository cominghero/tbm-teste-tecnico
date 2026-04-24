<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profissional' => [
                'id' => $this->profissional_id,
                'nome' => $this->whenLoaded('profissional', fn () => $this->profissional->nome),
            ],
            'paciente' => [
                'id' => $this->paciente_id,
                'nome' => $this->whenLoaded('paciente', fn () => $this->paciente->nome),
            ],
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
        ];
    }
}
