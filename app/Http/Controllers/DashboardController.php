<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $items = [];

        if ($user->hasActiveGeoguessrProfile()) {
            $items[] = [
                'route' => 'geoguessr.index',
                'name' => 'GeoGuessr',
                'description' => "See how everyone did on today's round.",
                'available' => true,
            ];
        }

        if ($user->hasFitIshProfile()) {
            $items[] = [
                'route' => 'fit-ish.index',
                'name' => 'Fit-Ish',
                'description' => 'Lionheart points, heart-rate zones, and who showed up.',
                'available' => true,
            ];
        }

        $items[] = [
            'route' => 'wickets.index',
            'name' => 'Wickets',
            'description' => 'Hand out fines, then drink them down.',
            'available' => true,
        ];

        $items[] = [
            'route' => 'pub-golf.index',
            'name' => 'Pub Golf',
            'description' => 'Start a crawl, log drinks, and see who lasted.',
            'available' => true,
        ];

        return view('dashboard', [
            'items' => $items,
        ]);
    }
}
