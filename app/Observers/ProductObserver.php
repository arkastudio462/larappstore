<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ProductSearchService;

class ProductObserver
{
    public function __construct(private readonly ProductSearchService $search) {}

    public function created(Product $product): void
    {
        $this->search->reindex($product);
    }

    public function updated(Product $product): void
    {
        $this->search->reindex($product);
    }

    public function restored(Product $product): void
    {
        $this->search->reindex($product);
    }

    public function deleted(Product $product): void
    {
        $this->search->remove($product->getKey());
    }
}
