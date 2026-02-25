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
     * Falls back to SQL LIKE search when Meilisearch feature flag is disabled.
     *
     * @return array{contracts: array, counterparties: array, wiki: array}
     */
    public function globalSearch(string $query, int $perType = 5): array
    {
        if (config('features.meilisearch', false)) {
            return [
                'contracts' => Contract::search($query)->take($perType)->get()->toArray(),
                'counterparties' => Counterparty::search($query)->take($perType)->get()->toArray(),
                'wiki' => WikiContract::search($query)->take($perType)->get()->toArray(),
            ];
        }

        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
        $like = '%' . $escaped . '%';

        return [
            'contracts' => Contract::where('title', 'LIKE', $like)->limit($perType)->get()->toArray(),
            'counterparties' => Counterparty::where('legal_name', 'LIKE', $like)->limit($perType)->get()->toArray(),
            'wiki' => WikiContract::where('name', 'LIKE', $like)->limit($perType)->get()->toArray(),
        ];
    }
}
