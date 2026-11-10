<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TopUpProviderNeutralMigrationTest extends TestCase
{
    public function test_existing_top_ups_keep_their_provider_price_when_migrated(): void
    {
        $migration = require __DIR__.'/../../database/migrations/2026_11_04_000001_make_top_ups_provider_neutral.php';

        $migration->down(); // back to the single-provider shape

        $this->assertTrue(Schema::hasColumns('subkit_top_ups', ['provider', 'provider_price_id']));
        $this->assertFalse(Schema::hasTable('subkit_top_up_provider_prices'));

        DB::table('subkit_top_ups')->insert([
            'code' => 'legacy', 'name' => 'Legacy', 'provider' => 'stripe', 'provider_price_id' => 'price_legacy',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('subkit_top_ups', 'provider_price_id'));
        $row = DB::table('subkit_top_up_provider_prices')->first();
        $this->assertSame('stripe', $row->provider);
        $this->assertSame('price_legacy', $row->provider_price_id);
    }
}
