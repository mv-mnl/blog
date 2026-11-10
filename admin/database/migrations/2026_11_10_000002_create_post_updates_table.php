<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actualizaciones de un post: entradas que se añaden con el tiempo
     * sin modificar el texto original ("antes pensaba esto, ahora esto").
     */
    public function up(): void
    {
        Schema::create('post_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_updates');
    }
};
