<?php

namespace Tests\Unit\Services\Chat;

use App\Models\User;
use App\Services\Chat\ParseChatMentions;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ParseChatMentionsTest extends TestCase
{
    public function test_it_mentions_a_player_by_first_name(): void
    {
        $ted = new User;
        $ted->id = 1;
        $ted->name = 'Ted Smith';
        $sam = new User;
        $sam->id = 2;
        $sam->name = 'Sam Fine';
        $parser = new ParseChatMentions;

        $mentioned = $parser->handle('Night is young @ted, see you at the next hole.', collect([$ted, $sam]));

        $this->assertSame([1], $mentioned->pluck('id')->all());
    }

    #[TestWith(['email me ted@example.com'])]
    #[TestWith(['no handles here'])]
    #[TestWith([null])]
    public function test_it_ignores_text_without_mentions(?string $body): void
    {
        $ted = new User;
        $ted->id = 1;
        $ted->name = 'Ted Smith';

        $this->assertSame([], (new ParseChatMentions)->handle($body, collect([$ted]))->all());
    }

    public function test_mentionable_handles_skip_the_viewer(): void
    {
        $ted = new User;
        $ted->id = 8;
        $ted->name = 'Ted Smith';
        $ted->color = '#D82820';
        $sam = new User;
        $sam->id = 9;
        $sam->name = 'Sam Fine';
        $sam->color = '#FEC523';

        $mentionable = (new ParseChatMentions)->mentionable(collect([$ted, $sam]), $ted);

        $this->assertSame([
            [
                'id' => 9,
                'handle' => 'sam',
                'name' => 'Sam Fine',
                'color' => '#FEC523',
            ],
        ], $mentionable);
    }
}
