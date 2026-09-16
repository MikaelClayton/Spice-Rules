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

    public function test_heartrate_chart_paints_candlesticks_with_the_zones_in_each_minute(): void
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
            new FitIshSessionGraphPoint([
                'minute' => 45,
                'type' => 'noData',
            ]),
        ]));

        $chart = $session->heartrateChart();

        $this->assertNotNull($chart);
        $this->assertSame(76, $chart['floor']);
        $this->assertSame(175, $chart['ceiling']);
        $this->assertSame(126, $chart['midpoint']);
        $this->assertSame([175, 126, 76], $chart['yTicks']);
        $this->assertSame(133, $chart['average']);
        $this->assertSame(45, $chart['endMinute']);
        $this->assertSame([15, 30, 45], $chart['xTicks']);
        $this->assertSame([15, 30], $chart['gridMinutes']);
        $this->assertSame([
            [
                'minute' => 1,
                'recorded' => false,
                'trailingEmpty' => false,
                'color' => '#4C6FE8',
                'segments' => [],
                'label' => 'Minute 1: no reading',
            ],
            [
                'minute' => 4,
                'recorded' => true,
                'trailingEmpty' => false,
                'color' => '#326EC8',
                'segments' => ['#326EC8'],
                'label' => 'Minute 4: 100–119 bpm · Recovery',
            ],
            [
                'minute' => 41,
                'recorded' => true,
                'trailingEmpty' => false,
                'color' => '#F2911B',
                'segments' => ['#326EC8', '#F2911B'],
                'label' => 'Minute 41: 147–173 bpm · High',
            ],
            [
                'minute' => 45,
                'recorded' => false,
                'trailingEmpty' => true,
                'color' => '#4C6FE8',
                'segments' => [],
                'label' => 'Minute 45: no reading',
            ],
        ], array_map(fn (array $column): array => [
            'minute' => $column['minute'],
            'recorded' => $column['recorded'],
            'trailingEmpty' => $column['trailingEmpty'],
            'color' => $column['color'],
            'segments' => array_column($column['segments'], 'color'),
            'label' => $column['label'],
        ], $chart['columns']));
    }

    public function test_heartrate_chart_falls_back_to_blue_when_the_session_has_no_zones(): void
    {
        $session = $this->fitIshSession();
        $session->setRelation('zones', new Collection);
        $session->setRelation('graphPoints', Collection::make([
            new FitIshSessionGraphPoint([
                'minute' => 4,
                'type' => 'recordedBpm',
                'bpm_min' => 100,
                'bpm_max' => 119,
            ]),
        ]));

        $chart = $session->heartrateChart();

        $this->assertNotNull($chart);
        $this->assertSame('#4C6FE8', $chart['columns'][0]['color']);
        $this->assertSame(['#4C6FE8'], array_column($chart['columns'][0]['segments'], 'color'));
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
