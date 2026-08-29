<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the payload shapes consumed by the Vuetify frontend.
 */
class DataTableService
{
    /**
     * Normalise a paginator into the shape VDataTableServer expects: a named
     * list bound to `:items` plus the pre-pagination `total` bound to
     * `:items-length`.
     *
     * @param  string  $itemsKey  Response key the page reads, e.g. `products`.
     * @param  (callable(mixed): mixed)|null  $transform  Optional row mapper.
     * @return array<string, mixed>
     */
    public function fromPaginator(LengthAwarePaginator $paginator, string $itemsKey = 'items', ?callable $transform = null): array
    {
        $rows = Collection::make($paginator->items());

        if ($transform !== null) {
            $rows = $rows->map($transform);
        }

        return [
            $itemsKey => $rows->values()->all(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'itemsPerPage' => $paginator->perPage(),
            'lastPage' => $paginator->lastPage(),
        ];
    }
}
