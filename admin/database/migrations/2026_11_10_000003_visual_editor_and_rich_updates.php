<?php

use App\Support\Html;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Editor visual:
 *  - El contenido pasa de Markdown a HTML (se convierte lo que ya existe).
 *  - Las actualizaciones se vuelven mini-posts: título y portada propios.
 *  - La búsqueda usa una columna de texto plano (sin etiquetas HTML), que
 *    incluye también el texto de las actualizaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_updates', function (Blueprint $table) {
            $table->string('title', 200)->default('')->after('post_id');
            $table->string('cover_image')->nullable()->after('body');
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->longText('search_text')->nullable()->after('body');
        });

        foreach (DB::table('post_updates')->get() as $u) {
            DB::table('post_updates')->where('id', $u->id)->update([
                'title' => $u->title ?: 'Actualización',
                'body' => Html::fromMarkdown($u->body),
            ]);
        }

        foreach (DB::table('posts')->get() as $p) {
            $body = Html::fromMarkdown($p->body);
            $updates = DB::table('post_updates')->where('post_id', $p->id)->get();
            DB::table('posts')->where('id', $p->id)->update([
                'body' => $body,
                'search_text' => Html::text($body.' '.$updates->map(fn ($u) => $u->title.' '.$u->body)->implode(' ')),
            ]);
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropFullText(['title', 'excerpt', 'body']);
            $table->fullText(['title', 'excerpt', 'search_text']);
        });
    }

    public function down(): void
    {
        // El HTML no se vuelve a convertir a Markdown.
        Schema::table('posts', function (Blueprint $table) {
            $table->dropFullText(['title', 'excerpt', 'search_text']);
            $table->dropColumn('search_text');
            $table->fullText(['title', 'excerpt', 'body']);
        });
        Schema::table('post_updates', function (Blueprint $table) {
            $table->dropColumn(['title', 'cover_image']);
        });
    }
};
