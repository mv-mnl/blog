<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Comentarios con antispam sin captchas:
 *  - campo trampa invisible ("website") que solo rellenan los bots,
 *  - tiempo mínimo entre cargar la página y enviar (los bots envían al instante),
 *  - máximo 3 comentarios cada 10 minutos por visitante.
 * Todo comentario queda pendiente hasta que se apruebe en el admin.
 */
class CommentController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $post = Post::published()->where('slug', $slug)->firstOrFail();
        $back = route('posts.show', $post->slug);
        $ipHash = substr(hash('sha256', config('app.key').'|'.$request->ip()), 0, 16);

        // Bots: se les responde como si todo fuera bien, pero no se guarda nada.
        if (filled($request->input('website')) || ! $this->humanTiming($request->input('t'))) {
            return redirect($back.'?comentario=enviado#comentarios');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'min:2', 'max:3000'],
        ], [], ['name' => 'nombre', 'body' => 'comentario']);

        if ($validator->fails()) {
            return app(BlogController::class)->show($request, $slug, $validator->errors(), $request->only('name', 'body'));
        }

        if (! RateLimiter::attempt('comment:'.$ipHash, 3, fn () => true, 600)) {
            $errors = $validator->errors()->add('body', 'Has enviado varios comentarios seguidos. Espera unos minutos.');

            return app(BlogController::class)->show($request, $slug, $errors, $request->only('name', 'body'));
        }

        Comment::create([
            'post_id' => $post->id,
            'name' => trim($request->input('name')),
            'body' => trim($request->input('body')),
            'ip_hash' => $ipHash,
        ]);

        return redirect($back.'?comentario=enviado#comentarios');
    }

    private function humanTiming(?string $token): bool
    {
        try {
            $elapsed = time() - (int) Crypt::decryptString((string) $token);

            return $elapsed >= 3 && $elapsed <= 86400;
        } catch (\Throwable) {
            return false;
        }
    }
}
