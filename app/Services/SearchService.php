<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\WikiContract;

class SearchService
{
    /**
     * Search across contracts, counterparties, and wiki articles.
     * Returns a unified result set keyed by resource type.
     *
     * @return array{contracts: array, counterparties: array, wiki: array}
     */
    public function globalSearch(string $query, int $perType = 5): array
    {
        return [
            'contracts' => Contract::search($query)->take($perType)->get()->toArray(),
            'counterparties' => Counterparty::search($query)->take($perType)->get()->toArray(),
            'wiki' => WikiContract::search($query)->take($perType)->get()->toArray(),
        ];
    }
}
