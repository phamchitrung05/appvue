<?php

namespace Tests\Unit;

use App\Services\DataTableService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class DataTableServiceTest extends TestCase
{
    private function paginator(array $items, int $total, int $perPage = 2, int $page = 1): LengthAwarePaginator
    {
        return new LengthAwarePaginator($items, $total, $perPage, $page);
    }

    public function test_it_normalises_a_paginator_for_v_data_table_server(): void
    {
        $service = new DataTableService;

        $result = $service->fromPaginator(
            $this->paginator([['id' => 1], ['id' => 2]], 7, 2, 2),
            'products',
        );

        $this->assertSame([['id' => 1], ['id' => 2]], $result['products']);
        $this->assertSame(7, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(2, $result['itemsPerPage']);
        $this->assertSame(4, $result['lastPage']);
    }

    public function test_it_defaults_the_items_key_to_items(): void
    {
        $result = (new DataTableService)->fromPaginator($this->paginator([['id' => 1]], 1));

        $this->assertArrayHasKey('items', $result);
        $this->assertSame([['id' => 1]], $result['items']);
    }

    public function test_it_applies_the_row_transformer_and_reindexes_rows(): void
    {
        $result = (new DataTableService)->fromPaginator(
            $this->paginator([['id' => 1, 'name' => 'Latte'], ['id' => 2, 'name' => 'Bạc xỉu']], 2),
            'products',
            fn (array $row): array => ['id' => $row['id'], 'label' => mb_strtoupper($row['name'])],
        );

        $this->assertSame([
            ['id' => 1, 'label' => 'LATTE'],
            ['id' => 2, 'label' => 'BẠC XỈU'],
        ], $result['products']);
    }

    public function test_it_returns_an_empty_list_when_there_are_no_rows(): void
    {
        $result = (new DataTableService)->fromPaginator($this->paginator([], 0), 'products');

        $this->assertSame([], $result['products']);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['lastPage']);
    }
}

