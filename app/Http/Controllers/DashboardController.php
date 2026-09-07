<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'items' => [
                [
                    'route' => 'geoguessr.index',
                    'name' => 'GeoGuessr',
                    'description' => "See how everyone did on today's round.",
                    'available' => true,
                ],
                [
                    'route' => 'wickets.index',
                    'name' => 'Wickets',
                    'description' => 'Hand out fines, then drink them down.',
                    'available' => true,
                ],
            ],
        ]);
    }
}
