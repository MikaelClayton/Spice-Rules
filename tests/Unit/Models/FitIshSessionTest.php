<?php

namespace Tests\Unit\Models;

use App\Models\FitIshSession;
use App\Models\FitIshSessionGraphPoint;
use App\Models\FitIshSessionZone;
use Illuminate\Support\Collection;
use Tests\TestCase;

class FitIshSessionTest extends TestCase
{
    public function test_heartrate_chart_is_absent_when_the_session_has_no_graph(): void
    {
        $session = $this->fitIshSession();
        $session->setRelation('graphPoints', new Collection);
        $session->setRelation('zones', new Collection);

        $this->assertNull($session->heartrateChart());
    }

    public function test_heartrate_chart_colors_each_minute_by_the_zone_of_peak_bpm(): void
    {
        $session = $this->fitIshSession();
        $session->setRelation('zones', Collection::make([
            new FitIshSessionZone([
                'zone_number' => 1,
                'name' => 'Very light / Recovery',
                'color_hex' => '#326EC8',
                'min_bpm' => 0,
                'max_bpm' => 145,
            ]),
            new FitIshSessionZone([
                'zone_number' => 4,
                'name' => 'High',
                'color_hex' => '#F2911B',
                'min_bpm' => 168,
                'max_bpm' => 178,
            ]),
        ]));
        $session->setRelation('graphPoints', Collection::make([
            new FitIshSessionGraphPoint([
                'minute' => 1,
                'type' => 'noData',
            ]),
            new FitIshSessionGraphPoint([
                'minute' => 4,
                'type' => 'recordedBpm',
                'bpm_min' => 100,
                'bpm_max' => 119,
            ]),
            new FitIshSessionGraphPoint([
                'minute' => 41,
                'type' => 'recordedBpm',
                'bpm_min' => 147,
                'bpm_max' => 173,
            ]),
        ]));

        $chart = $session->heartrateChart();

        $this->assertNotNull($chart);
        $this->assertSame(66, $chart['floor']);
        $this->assertSame(190, $chart['ceiling']);
        $this->assertSame(133, $chart['average']);
        $this->assertSame(45, $chart['endMinute']);
        $this->assertSame([
            [
                'minute' => 1,
                'recorded' => false,
                'color' => '#9CA3AF',
                'label' => 'Minute 1: no reading',
            ],
            [
                'minute' => 4,
                'recorded' => true,
                'color' => '#326EC8',
                'label' => 'Minute 4: 100–119 bpm · Recovery',
            ],
            [
                'minute' => 41,
                'recorded' => true,
                'color' => '#F2911B',
                'label' => 'Minute 41: 147–173 bpm · High',
            ],
        ], array_map(fn (array $column): array => [
            'minute' => $column['minute'],
            'recorded' => $column['recorded'],
            'color' => $column['color'],
            'label' => $column['label'],
        ], $chart['columns']));
    }

    private function fitIshSession(): FitIshSession
    {
        return new FitIshSession([
            'average_heartrate' => 133,
            'max_heartrate' => 173,
            'max_hr_value' => 190,
            'resting_hr_value' => 76,
            'duration_in_minutes' => 45,
        ]);
    }
}
