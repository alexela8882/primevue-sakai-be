<?php

namespace App\Jobs\Pricebook;

use App\Models\Product\Price;
use App\Models\Product\Pricebook;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CancelComputePriceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Pricebook $pricebook, public User $user)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $pricebook = $this->pricebook->load([
            'prices' => fn ($query) => $query->where('temporary_price', '!=', null),
        ]);

        $pricebook
            ->prices
            ->each(fn (Price $price) => $price->update(['temporary_price' => null]));

        $pricebook->update(['isComputingPrice' => false]);
    }
}
