<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CheckinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Checkin extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'profissional_id',
        'paciente_id',
        'latitude',
        'longitude',
        'checked_in_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'checked_in_at' => 'datetime',
    ];

    public function profissional(): BelongsTo
    {
        return $this->belongsTo(Profissional::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    protected static function newFactory(): CheckinFactory
    {
        return CheckinFactory::new();
    }
}
