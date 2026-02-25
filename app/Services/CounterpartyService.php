<?php

namespace App\Services;

use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Collection;

class CounterpartyService
{
    /**
     * Find counterparties that are likely duplicates of the given candidate.
     * Uses exact registration_number match and trigram-like LIKE search on legal_name.
     * Returns up to 5 matches excluding the given $excludeId.
     *
     * @return Collection<int, Counterparty>
     */
    public function findDuplicates(string $legalName, string $registrationNumber, ?string $excludeId = null): Collection
    {
        $query = Counterparty::query()
            ->where(function ($q) use ($legalName, $registrationNumber) {
                $q->where('registration_number', $registrationNumber)
                  ->orWhere('legal_name', 'LIKE', '%' . str_replace(['%', '_'], ['\\%', '\\_'], substr(trim($legalName), 0, 6)) . '%');
            })
            ->where('status', '!=', 'Blacklisted')
            ->limit(5);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->get(['id', 'legal_name', 'registration_number', 'status', 'jurisdiction']);
    }
}
