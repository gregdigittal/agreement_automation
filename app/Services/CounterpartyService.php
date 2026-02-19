<?php

namespace App\Services;

use App\Models\Counterparty;
use Illuminate\Support\Collection;

class CounterpartyService
{
    public function findDuplicates(string $legalName, ?string $registrationNumber = null, ?string $excludeId = null): Collection
    {
        $query = Counterparty::query();

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $query->where(function ($q) use ($legalName, $registrationNumber) {
            $q->where(function ($fuzzy) use ($legalName) {
                $normalized = $this->normalize($legalName);
                $fuzzy->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(legal_name, " ", ""), ".", ""), ",", "")) LIKE ?', ["%{$normalized}%"]);
            });

            if ($registrationNumber) {
                $q->orWhere('registration_number', $registrationNumber);
            }
        });

        return $query->limit(10)->get()->map(fn (Counterparty $cp) => [
            'id' => $cp->id,
            'legal_name' => $cp->legal_name,
            'registration_number' => $cp->registration_number,
            'status' => $cp->status,
            'match_type' => $registrationNumber && $cp->registration_number === $registrationNumber
                ? 'exact_registration'
                : 'fuzzy_name',
        ]);
    }

    private function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = str_replace([' ', '.', ',', 'pty', 'ltd', 'limited', 'inc', 'corp', 'llc'], '', $value);
        return $value;
    }
}
