<?php

namespace App\Services\Chat;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParseChatMentions
{
    /**
     * @param  Collection<int, User>  $participants
     * @return Collection<int, User>
     */
    public function handle(?string $body, Collection $participants): Collection
    {
        $tokens = $this->tokens($body);

        if ($tokens === []) {
            return collect();
        }

        return $participants
            ->filter(function (User $user) use ($tokens): bool {
                $handle = $this->handleFor($user);

                return $handle !== '' && in_array($handle, $tokens, true);
            })
            ->unique('id')
            ->values();
    }

    public function handleFor(User $user): string
    {
        $first = Str::of($user->name)->trim()->before(' ')->lower()->toString();

        return (string) preg_replace('/[^a-z0-9]/', '', $first);
    }

    /**
     * @param  Collection<int, User>  $participants
     * @return list<array{id: int, handle: string, name: string, color: string}>
     */
    public function mentionable(Collection $participants, User $viewer): array
    {
        return $participants
            ->reject(fn (User $user): bool => $user->is($viewer))
            ->map(function (User $user): ?array {
                $handle = $this->handleFor($user);

                if ($handle === '') {
                    return null;
                }

                return [
                    'id' => $user->id,
                    'handle' => $handle,
                    'name' => $user->name,
                    'color' => $user->boardColor(),
                ];
            })
            ->filter()
            ->sortBy([
                ['name', 'asc'],
                ['id', 'asc'],
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function tokens(?string $body): array
    {
        if (! is_string($body) || $body === '') {
            return [];
        }

        preg_match_all('/(?:^|[^A-Za-z0-9])@([A-Za-z0-9]+)/', $body, $matches);

        return array_values(array_unique(array_map(
            fn (string $token): string => Str::lower($token),
            $matches[1] ?? [],
        )));
    }
}
