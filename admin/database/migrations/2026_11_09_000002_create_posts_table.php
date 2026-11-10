<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->string('cover_image')->nullable();
            // null = borrador; fecha futura = programado; fecha pasada = publicado
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();

            $table->fullText(['title', 'excerpt', 'body']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
