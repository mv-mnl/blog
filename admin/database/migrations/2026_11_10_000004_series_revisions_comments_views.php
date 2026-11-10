<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Series: agrupar posts como "Parte 1, 2, 3…" (el orden es el de publicación).
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('slug', 160)->unique();
            $table->timestamps();
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->foreignId('series_id')->nullable()->after('cover_image')->constrained('series')->nullOnDelete();
        });

        // Historial: copia de cómo era el post antes de cada cambio.
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('excerpt', 500)->nullable();
            $table->longText('body');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['post_id', 'created_at']);
        });

        // Comentarios: los escribe la web (solo INSERT) y quedan pendientes hasta aprobarlos.
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->text('body');
            $table->string('status', 10)->default('pending')->index(); // pending | approved | spam
            $table->char('ip_hash', 16)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['post_id', 'status']);
        });

        // Visitas: sin cookies; el visitante es un hash diario anónimo.
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 255);
            $table->string('referrer_host', 120)->nullable();
            $table->char('visitor', 16);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_revisions');
        Schema::table('posts', fn (Blueprint $t) => $t->dropConstrainedForeignId('series_id'));
        Schema::dropIfExists('series');
    }
};
