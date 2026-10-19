<?php

declare(strict_types=1);

namespace App\Service\Customer;

use App\Data\ProductCatalog as SiteProductCatalog;

final class ProductCatalog
{
    public function __construct(private readonly SiteProductCatalog $catalog)
    {
    }

    public function all(): array
    {
        return $this->catalog->all();
    }

    public function byId(string $id): ?array
    {
        $product = $this->catalog->byId()[$id] ?? null;
        if ($product === null) {
            return null;
        }
        $product['price'] = (float) preg_replace('/[^0-9.]/', '', $product['price']);
        return $product;
    }

    public function byName(string $name): ?array
    {
        foreach ($this->catalog->byId() as $id => $product) {
            if ($product['title'] === $name) {
                return $this->byId($id);
            }
        }
        return null;
    }
}
