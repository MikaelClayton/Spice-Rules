<?php

namespace Tests\Feature;

use App\Enums\PubGolfDrinkCategory;
use App\Models\PubGolfCrawl;
use App\Models\PubGolfCustomDrink;
use App\Models\PubGolfParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PubGolfCustomDrinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_can_add_a_missing_drink_with_a_photo_and_category(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'Windhoek Light',
                'category' => PubGolfDrinkCategory::Beer->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Windhoek Light added to the list.');

        $custom = PubGolfCustomDrink::query()->first();

        $this->assertNotNull($custom);
        $this->assertSame('Windhoek Light', $custom->name);
        $this->assertSame(PubGolfDrinkCategory::Beer, $custom->category);
        $this->assertNotNull($custom->photo_path);
        Storage::disk('public')->assertExists($custom->photo_path);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Windhoek Light')
            ->assertSee($custom->photoUrl());

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $custom->id,
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Windhoek Light logged.');

        $this->assertDatabaseHas('pub_golf_drink_logs', [
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $user->id,
            'drink_id' => $custom->id,
        ]);

        $this->actingAs($user)
            ->post(route('pub-golf.leave.store', $crawl))
            ->assertRedirect(route('pub-golf.recap.show', $crawl));

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('Windhoek Light');
    }

    public function test_a_blank_custom_drink_name_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => '   ',
                'category' => PubGolfDrinkCategory::Cider->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['name' => 'Give the drink a name.']);

        $this->assertDatabaseCount('pub_golf_custom_drinks', 0);
    }

    public function test_a_missing_photo_is_rejected(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'House pour',
                'category' => PubGolfDrinkCategory::Spirit->value,
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['photo' => 'Add a photo of the drink.']);

        $this->assertDatabaseCount('pub_golf_custom_drinks', 0);
    }

    public function test_heic_photos_are_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'House pour',
                'category' => PubGolfDrinkCategory::Spirit->value,
                'photo' => UploadedFile::fake()->create('pint.heic', 200, 'image/heic'),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['photo' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.']);

        $this->assertDatabaseCount('pub_golf_custom_drinks', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_existing_custom_drink_cannot_be_added_again(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        PubGolfCustomDrink::factory()->create([
            'user_id' => $user->id,
            'name' => 'Windhoek Light',
        ]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'windhoek light',
                'category' => PubGolfDrinkCategory::Beer->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['name' => 'That drink is already on the list.']);

        $this->assertDatabaseCount('pub_golf_custom_drinks', 1);
    }

    public function test_an_invalid_category_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'House pour',
                'category' => 'soft',
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['category' => 'Pick a category.']);

        $this->assertDatabaseCount('pub_golf_custom_drinks', 0);
    }

    public function test_dangerous_drink_names_are_escaped_on_the_board(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => '<script>alert(1)</script>',
                'category' => PubGolfDrinkCategory::Beer->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl));

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_outsiders_cannot_add_drinks(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($outsider)
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'House pour',
                'category' => PubGolfDrinkCategory::Spirit->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.index'));

        $this->assertDatabaseCount('pub_golf_custom_drinks', 0);
    }

    public function test_the_creator_can_remove_their_drink_from_the_list(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'Jungle Juice',
                'category' => PubGolfDrinkCategory::Rtd->value,
                'photo' => $this->drinkPhoto(),
            ]);

        $drink = PubGolfCustomDrink::query()->first();

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Jungle Juice')
            ->assertSee('Remove Jungle Juice', false)
            ->assertSee('Log this drink?')
            ->assertSee('Log it')
            ->assertSee('Remove this drink?')
            ->assertSee('Remove drink');

        $photoPath = $drink->photo_path;

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->delete(route('pub-golf.custom-drinks.destroy', [$crawl, $drink]))
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Jungle Juice removed from the list.');

        $removed = $drink->fresh();

        $this->assertNotNull($removed);
        $this->assertNotNull($removed->removed_at);
        $this->assertNull($removed->photo_path);
        $this->assertSame('Jungle Juice', $removed->name);
        Storage::disk('public')->assertMissing($photoPath);

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('No drinks yet. Add one below.')
            ->assertDontSee('Remove Jungle Juice', false);
    }

    public function test_a_removed_drink_name_can_be_added_again(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        PubGolfCustomDrink::factory()->inactive()->create([
            'user_id' => $user->id,
            'name' => 'Jungle Juice',
        ]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'Jungle Juice',
                'category' => PubGolfDrinkCategory::Rtd->value,
                'photo' => $this->drinkPhoto(),
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHas('status', 'Jungle Juice added to the list.');

        $this->assertSame(2, PubGolfCustomDrink::query()->count());
        $this->assertSame(1, PubGolfCustomDrink::query()->active()->count());
    }

    public function test_removed_drinks_do_not_appear_as_log_shortcuts(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'Jungle Juice',
                'category' => PubGolfDrinkCategory::Rtd->value,
                'photo' => $this->drinkPhoto(),
            ]);

        $drink = PubGolfCustomDrink::query()->first();

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ]);

        $this->actingAs($user)
            ->delete(route('pub-golf.custom-drinks.destroy', [$crawl, $drink]));

        $this->actingAs($user)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Undo')
            ->assertDontSee('Same again')
            ->assertDontSee('data-drink-key="'.$drink->id.'"', false)
            ->assertSee('Jungle Juice');
    }

    public function test_removed_drinks_still_show_on_the_recap(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('pub-golf.custom-drinks.store', $crawl), [
                'name' => 'Mimosa',
                'category' => PubGolfDrinkCategory::Wine->value,
                'photo' => $this->drinkPhoto(),
            ]);

        $drink = PubGolfCustomDrink::query()->first();

        $this->actingAs($user)
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ]);

        $this->actingAs($user)
            ->delete(route('pub-golf.custom-drinks.destroy', [$crawl, $drink]));

        $this->actingAs($user)
            ->post(route('pub-golf.leave.store', $crawl));

        $this->actingAs($user)
            ->get(route('pub-golf.recap.show', $crawl))
            ->assertOk()
            ->assertSee('Mimosa');
    }

    public function test_other_players_cannot_remove_a_drink_they_did_not_add(): void
    {
        $host = User::factory()->create();
        $friend = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $host->id]);
        PubGolfParticipant::factory()->create([
            'pub_golf_crawl_id' => $crawl->id,
            'user_id' => $friend->id,
        ]);
        $drink = PubGolfCustomDrink::factory()->create([
            'user_id' => $host->id,
            'name' => 'Jungle Juice',
        ]);

        $this->actingAs($friend)
            ->get(route('pub-golf.show', $crawl))
            ->assertOk()
            ->assertSee('Jungle Juice')
            ->assertDontSee('Remove Jungle Juice', false);

        $this->actingAs($friend)
            ->delete(route('pub-golf.custom-drinks.destroy', [$crawl, $drink]))
            ->assertForbidden();

        $this->assertNull($drink->fresh()->removed_at);
    }

    public function test_a_removed_drink_cannot_be_logged(): void
    {
        $user = User::factory()->create();
        $crawl = PubGolfCrawl::factory()->create(['user_id' => $user->id]);
        $drink = PubGolfCustomDrink::factory()->inactive()->create([
            'user_id' => $user->id,
            'name' => 'Mimosa',
        ]);

        $this->actingAs($user)
            ->from(route('pub-golf.show', $crawl))
            ->post(route('pub-golf.drinks.store', $crawl), [
                'drink' => $drink->id,
            ])
            ->assertRedirect(route('pub-golf.show', $crawl))
            ->assertSessionHasErrors(['drink' => 'Pick a drink from the list.']);
    }

    private function drinkPhoto(): UploadedFile
    {
        return UploadedFile::fake()->image('drink.jpg', 800, 600);
    }
}
