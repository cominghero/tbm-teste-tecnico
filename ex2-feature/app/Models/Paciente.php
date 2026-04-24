<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PacienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paciente extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'pacientes';

    protected $fillable = [
        'nome',
    ];

    protected static function newFactory(): PacienteFactory
    {
        return PacienteFactory::new();
    }
}
