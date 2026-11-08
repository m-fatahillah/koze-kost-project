<?php

namespace App\Console\Commands;

use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireFeaturedListings extends Command
{
    protected $signature = 'featured-listings:expire';

    protected $description = 'Reset is_featured ke 0 untuk properti yang sudah lewat featured_until.';

    public function handle(): int
    {
        $now = Carbon::now();

        $affected = Property::query()
            ->where('is_featured', true)
            ->whereNotNull('featured_until')
            ->where('featured_until', '<=', $now)
            ->update([
                'is_featured' => false,
            ]);

        $this->info("Featured listing expired: {$affected} properti.");
        return self::SUCCESS;
    }
}
