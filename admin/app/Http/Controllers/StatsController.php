<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request)
    {
        $days = in_array((int) $request->query('dias'), [7, 30, 90, 365], true) ? (int) $request->query('dias') : 30;
        $from = now()->startOfDay()->subDays($days - 1);
        $views = DB::table('page_views')->where('page_views.created_at', '>=', $from);

        $perDay = (clone $views)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as views, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('day')->pluck('views', 'day');
        $visitorsPerDay = (clone $views)
            ->selectRaw('DATE(created_at) as day, COUNT(DISTINCT visitor) as visitors')
            ->groupBy('day')->pluck('visitors', 'day');

        $series = collect(range(0, $days - 1))->map(function ($i) use ($from, $perDay, $visitorsPerDay) {
            $d = $from->copy()->addDays($i)->toDateString();

            return [
                'label' => Carbon::parse($d)->translatedFormat('j M'),
                'views' => (int) ($perDay[$d] ?? 0),
                'visitors' => (int) ($visitorsPerDay[$d] ?? 0),
            ];
        });

        return view('stats.index', [
            'days' => $days,
            'series' => $series,
            'totalViews' => (clone $views)->count(),
            'totalVisitors' => (clone $views)->selectRaw('COUNT(DISTINCT visitor, DATE(created_at)) as n')->value('n'),
            'topPosts' => (clone $views)->join('posts', 'posts.id', '=', 'page_views.post_id')
                ->selectRaw('posts.id, posts.title, posts.slug, COUNT(*) as views')
                ->groupBy('posts.id', 'posts.title', 'posts.slug')->orderByDesc('views')->limit(10)->get(),
            'referrers' => (clone $views)->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, COUNT(*) as views')->groupBy('referrer_host')->orderByDesc('views')->limit(10)->get(),
            'pending' => DB::table('comments')->where('status', 'pending')->count(),
        ]);
    }
}
