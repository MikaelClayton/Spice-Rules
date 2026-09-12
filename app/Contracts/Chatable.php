<?php

namespace App\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface Chatable
{
    /**
     * @return Collection<int, User>
     */
    public function chatParticipants(): Collection;

    public function canViewChat(?User $user): bool;

    public function canSendChat(?User $user): bool;

    public function chatTitle(): string;

    public function chatUrl(): string;

    public function chatDeniedMessage(): string;

    public function chatClosedMessage(): string;
}
