<?php

namespace Tests\Fixtures;

class FitIshPayloads
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function session(array $overrides = []): array
    {
        $data = [
            'sessionId' => '2026-09-15_0600:studio:ojb7:serial:1352',
            'studio' => [
                'studioId' => 5061,
                'name' => 'F45 Faerie Glen',
                'code' => 'ojb7',
                'timezone' => 'Africa/Johannesburg',
                'isLoaner' => false,
            ],
            'workout' => [
                'name' => 'Phoenix',
                'displayName' => 'PHOENIX',
                'type' => [
                    'id' => 2,
                    'name' => 'resistance',
                ],
                'logo' => [
                    'url' => 'https://f45tv.cdn.f45.com/lionheart-logos/F45_Logos_Phoenix_600x600.png',
                ],
                'description' => null,
            ],
            'classInfo' => [
                'localizedDateTime' => 'Tue, Sep 15, 2026 | 6:00am',
                'date' => '2026-09-15',
                'time' => '06:00:00',
                'timestamp' => 1789444800,
                'timezone' => 'Africa/Johannesburg',
                'durationInMinutes' => 45,
            ],
            'summary' => [
                'points' => 48.3,
                'heartrate' => [
                    'average' => 133,
                    'max' => 173,
                ],
                'estimatedCalories' => 504,
                'trackedDurationInSeconds' => 2465,
            ],
            'heartrate' => [
                'calculationMethod' => [
                    'id' => 2,
                    'name' => 'karvonen',
                ],
                'inputs' => [
                    'maxHR' => [
                        'default' => 190,
                        'override' => 190,
                        'value' => 190,
                    ],
                    'restingHR' => [
                        'default' => 75,
                        'override' => 76,
                        'value' => 76,
                    ],
                ],
                'zones' => [
                    [
                        'zoneId' => 1,
                        'name' => 'Very light / Recovery',
                        'description' => 'Ideal for warm-ups.',
                        'colorHex' => '#326EC8',
                        'minPercentage' => 0,
                        'maxPercentage' => 60,
                        'minBpm' => 0,
                        'maxBpm' => 145,
                        'bpmLabel' => '<145 BPM',
                        'computedDuration' => [
                            'seconds' => 1536,
                            'label' => '25:36',
                        ],
                        'computedPercentage' => [
                            'value' => 62.3,
                            'label' => '62%',
                        ],
                    ],
                    [
                        'zoneId' => 4,
                        'name' => 'High',
                        'description' => 'Boosts speed.',
                        'colorHex' => '#F2911B',
                        'minPercentage' => 80,
                        'maxPercentage' => 90,
                        'minBpm' => 168,
                        'maxBpm' => 178,
                        'bpmLabel' => '168 - 178 BPM',
                        'computedDuration' => [
                            'seconds' => 101,
                            'label' => '01:41',
                        ],
                        'computedPercentage' => [
                            'value' => 4.1,
                            'label' => '4%',
                        ],
                    ],
                ],
            ],
            'graph' => [
                'type' => 'bpmCandlestick',
                'timeSeries' => [
                    [
                        'minute' => 1,
                        'type' => 'noData',
                    ],
                    [
                        'minute' => 4,
                        'type' => 'recordedBpm',
                        'bpm' => [
                            'min' => 100,
                            'max' => 119,
                        ],
                    ],
                    [
                        'minute' => 41,
                        'type' => 'recordedBpm',
                        'bpm' => [
                            'min' => 147,
                            'max' => 173,
                        ],
                    ],
                ],
            ],
        ];

        return [
            'status' => 200,
            'success' => true,
            'data' => array_replace_recursive($data, $overrides),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function summary(): array
    {
        $row = [
            'timeframe' => [
                'id' => 1,
                'name' => 'All Time',
                'numberOfDays' => null,
            ],
            'sessionCount' => 1,
            'averagePoints' => 48.3,
            'averageCalories' => 504,
            'maxPoints' => 48.3,
        ];

        return [
            'status' => 200,
            'success' => true,
            'data' => [
                'summary' => [
                    'allTime' => $row,
                    'year' => [
                        ...$row,
                        'timeframe' => [
                            'id' => 2,
                            'name' => 'This Year',
                            'numberOfDays' => 365,
                        ],
                    ],
                    'week' => [
                        ...$row,
                        'timeframe' => [
                            'id' => 6,
                            'name' => 'This Week',
                            'numberOfDays' => 7,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function sessionList(string $sessionId = '2026-09-15_0600:studio:ojb7:serial:1352'): array
    {
        return [
            'status' => 200,
            'success' => true,
            'data' => [
                [
                    'sessionId' => $sessionId,
                ],
            ],
        ];
    }
}
