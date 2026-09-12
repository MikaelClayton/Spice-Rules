<?php

namespace App\Services\PubGolf;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCustomDrink;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class AddPubGolfCustomDrink
{
    public function __construct(private StorePubGolfDrinkPhoto $storePubGolfDrinkPhoto) {}

    public function handle(User $user, string $name, PubGolfDrinkCategory $category, UploadedFile $photo): PubGolfCustomDrink
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Give the drink a name.',
            ]);
        }

        $alreadyAdded = PubGolfCustomDrink::query()
            ->active()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();

        if ($alreadyAdded) {
            throw ValidationException::withMessages([
                'name' => 'That drink is already on the list.',
            ]);
        }

        return PubGolfCustomDrink::query()->create([
            'user_id' => $user->id,
            'name' => $name,
            'category' => $category,
            'photo_path' => $this->storePubGolfDrinkPhoto->handle($photo),
        ]);
    }
}
