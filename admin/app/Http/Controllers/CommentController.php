<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('estado'), ['pending', 'approved', 'spam'], true) ? $request->query('estado') : 'pending';

        return view('comments.index', [
            'status' => $status,
            'comments' => Comment::with('post:id,title,slug')->where('status', $status)->latest()->latest('id')->paginate(30)->withQueryString(),
            'counts' => Comment::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function update(Request $request, Comment $comment)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['pending', 'approved', 'spam'])]]);
        $comment->forceFill(['status' => $data['status']])->save();

        $msg = ['approved' => 'Comentario aprobado: ya se ve en el blog.', 'spam' => 'Marcado como spam.', 'pending' => 'Devuelto a pendientes.'];

        return back()->with('status', $msg[$data['status']]);
    }

    public function destroy(Comment $comment)
    {
        $comment->delete();

        return back()->with('status', 'Comentario eliminado.');
    }

    public function destroySpam()
    {
        $n = Comment::where('status', 'spam')->delete();

        return back()->with('status', "Eliminados $n comentarios de spam.");
    }
}
