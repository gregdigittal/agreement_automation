<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\WikiContract;
use Illuminate\Support\Collection;

class SearchService
{
    public function globalSearch(string $query, int $limit = 20): Collection
    {
        $results = collect();

        $contracts = Contract::search($query)->take($limit)->get()->map(fn ($r) => [
            'type' => 'contract',
            'id' => $r->id,
            'title' => $r->title ?? 'Untitled',
            'subtitle' => $r->contract_type . ' — ' . $r->workflow_state,
            'url' => route('filament.admin.resources.contracts.edit', $r),
        ]);

        $counterparties = Counterparty::search($query)->take($limit)->get()->map(fn ($r) => [
            'type' => 'counterparty',
            'id' => $r->id,
            'title' => $r->legal_name,
            'subtitle' => $r->status . ($r->jurisdiction ? " — {$r->jurisdiction}" : ''),
            'url' => route('filament.admin.resources.counterparties.edit', $r),
        ]);

        $templates = WikiContract::search($query)->take($limit)->get()->map(fn ($r) => [
            'type' => 'template',
            'id' => $r->id,
            'title' => $r->name,
            'subtitle' => $r->category ?? $r->status,
            'url' => route('filament.admin.resources.wiki-contracts.edit', $r),
        ]);

        return $results->merge($contracts)->merge($counterparties)->merge($templates)->take($limit);
    }
}
