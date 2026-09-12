<?php

use App\Enums\PubGolfDrink;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->foreignId('drink_id')
                ->nullable()
                ->after('user_id')
                ->constrained('pub_golf_custom_drinks')
                ->restrictOnDelete();
        });

        $this->backfillDrinkIds();

        DB::table('pub_golf_drink_logs')->whereNull('drink_id')->delete();

        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('drink_id')->nullable(false)->change();
            $table->dropColumn('drink');
        });
    }

    public function down(): void
    {
        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->string('drink')->nullable()->after('user_id');
        });

        foreach (DB::table('pub_golf_drink_logs')->orderBy('id')->get() as $log) {
            DB::table('pub_golf_drink_logs')->where('id', $log->id)->update([
                'drink' => 'custom:'.$log->drink_id,
            ]);
        }

        Schema::table('pub_golf_drink_logs', function (Blueprint $table) {
            $table->dropForeign(['drink_id']);
            $table->dropColumn('drink_id');
            $table->string('drink')->nullable(false)->change();
        });
    }

    private function backfillDrinkIds(): void
    {
        $now = now();

        foreach (DB::table('pub_golf_drink_logs')->orderBy('id')->get() as $log) {
            $drinkId = $this->resolveDrinkId($log, $now);

            if ($drinkId === null) {
                continue;
            }

            DB::table('pub_golf_drink_logs')->where('id', $log->id)->update([
                'drink_id' => $drinkId,
            ]);
        }
    }

    private function resolveDrinkId(object $log, Carbon $now): ?int
    {
        $value = (string) $log->drink;

        if (str_starts_with($value, 'custom:')) {
            $id = (int) substr($value, strlen('custom:'));

            if ($id > 0 && DB::table('pub_golf_custom_drinks')->where('id', $id)->exists()) {
                return $id;
            }

            return null;
        }

        $catalog = PubGolfDrink::tryFrom($value);

        if ($catalog === null) {
            return null;
        }

        $existing = DB::table('pub_golf_custom_drinks')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($catalog->label())])
            ->orderBy('id')
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('pub_golf_custom_drinks')->insertGetId([
            'user_id' => $log->user_id,
            'name' => $catalog->label(),
            'category' => $catalog->category()->value,
            'photo_path' => null,
            'removed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
