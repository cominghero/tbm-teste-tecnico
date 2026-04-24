<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('profissional_id')->constrained('profissionais')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('pacientes')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('checked_in_at');
            $table->timestamps();

            $table->index(['tenant_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkins');
    }
};
