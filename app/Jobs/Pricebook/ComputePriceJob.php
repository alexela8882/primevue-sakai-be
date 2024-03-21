<?php

namespace App\Jobs\Pricebook;

use App\Models\Core\Currency;
use App\Models\Core\Log;
use App\Models\Pricelist\ExworkPrice;
use App\Models\Pricelist\Pricelist;
use App\Models\Product\Price;
use App\Models\Product\Pricebook;
use App\Models\User;
use App\Services\PricebookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputePriceJob implements ShouldQueue
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
            'myCurrencies',
            'pricelists',
            'pricelists.exworkPrices',
            'prices',
        ]);

        $pricebook
            ->pricelists
            ->sortByDesc('updated_at')
            ->map(fn (Pricelist $pricelist) => $pricelist->exworkPrices)
            ->collapse() // collection of exwork prices
            ->sortByDesc('updated_at')
            ->unique('product_id')
            ->each(function (ExworkPrice $exworkPrice) use ($pricebook) {
                $price = $pricebook->prices->filter(fn (Price $price) => $price->product_id == $exworkPrice->product_id)->firstWhere('exwork_price_id', $exworkPrice->_id);

                $pricebook
                    ->myCurrencies
                    ->each(function (Currency $currency) use ($price, $exworkPrice, $pricebook) {
                        $temporaryPrice = PricebookService::computeTemporaryPrice($pricebook, $exworkPrice);

                        $pricebook = Price::updateOrCreate(
                            [
                                'product_id' => $exworkPrice->product_id,
                                'exwork_price_id' => $exworkPrice->_id,
                                'currency_id' => $currency->_id,
                                'pricebook_id' => $pricebook->_id,
                            ],
                            [
                                'price' => $price?->price,
                                'temporary_price' => $temporaryPrice,
                                'active' => true,
                            ],
                        );
                    });
            });

        $pricebook->update(['isComputingPrice' => true]);

        Log::create([
            'record_id' => $pricebook->_id,
            'eventHook' => 'compute-price',
            'created_by' => $this->user->_id,
        ]);
    }
}
