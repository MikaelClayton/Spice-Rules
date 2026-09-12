<?php

namespace App\Services\PubGolf;

use App\Models\PubGolfCustomDrink;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RemovePubGolfCustomDrink
{
    public function handle(User $user, PubGolfCustomDrink $drink): void
    {
        if ($drink->user_id !== $user->id || $drink->removed_at !== null) {
            throw ValidationException::withMessages([
                'drink' => 'You can only remove a drink you added.',
            ]);
        }

        $photoPath = $drink->photo_path;

        $drink->update([
            'removed_at' => now(),
            'photo_path' => null,
        ]);

        if (is_string($photoPath) && $photoPath !== '') {
            Storage::disk((string) config('pub-golf.photo_disk'))->delete($photoPath);
        }
    }
}
