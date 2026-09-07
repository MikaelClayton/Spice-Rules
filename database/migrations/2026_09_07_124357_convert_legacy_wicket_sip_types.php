<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('wicket_fines')
            ->whereIn('type', ['one_sip', 'two_sips', 'three_sips', 'four_sips'])
            ->update(['type' => 'sips']);
    }

    public function down(): void
    {
        foreach ([1 => 'one_sip', 2 => 'two_sips', 3 => 'three_sips', 4 => 'four_sips'] as $sips => $type) {
            DB::table('wicket_fines')
                ->where('type', 'sips')
                ->where('sips_owed', $sips)
                ->update(['type' => $type]);
        }
    }
};
