<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProfissionalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profissional extends Model
{
    use BelongsToTenant;
    use HasFactory;

    // Laravel's default pluralizer gives "profissionals".
    protected $table = 'profissionais';

    protected $fillable = [
        'nome',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    protected static function newFactory(): ProfissionalFactory
    {
        return ProfissionalFactory::new();
    }
}
