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
                    'name' => 'Trivia',
                    'description' => "See how everyone did on today's questions.",
                    'available' => false,
                ],
                [
                    'name' => 'Word Rush',
                    'description' => "See how everyone did on today's words.",
                    'available' => false,
                ],
            ],
        ]);
    }
}
