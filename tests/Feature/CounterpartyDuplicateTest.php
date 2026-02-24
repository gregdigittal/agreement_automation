<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Services\CounterpartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CounterpartyDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private CounterpartyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CounterpartyService();
    }

    /**
     * Two counterparties sharing the same registration_number should both be
     * returned (minus the one used as the search subject, which is excluded
     * by passing its own id via $excludeId in real usage — here we leave
     * $excludeId null to confirm the matcher fires at all).
     */
    public function test_finds_duplicate_by_registration_number(): void
    {
        $existing = Counterparty::factory()->create([
            'registration_number' => 'REG-001',
            'status' => 'Active',
        ]);

        // A second counterparty with a completely different name but the same reg number.
        Counterparty::factory()->create([
            'legal_name'          => 'Totally Different Ltd',
            'registration_number' => 'REG-999',
            'status'              => 'Active',
        ]);

        $results = $this->service->findDuplicates(
            'Nonmatching Name XYZ',
            'REG-001',
            null
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains('id', $existing->id));
    }

    /**
     * A counterparty whose legal_name shares the first 6 characters with the
     * search term should be returned even when the registration numbers differ.
     */
    public function test_finds_duplicate_by_similar_legal_name(): void
    {
        // "Acme C" is the shared 6-char prefix.
        $existing = Counterparty::factory()->create([
            'legal_name'          => 'Acme Corp International',
            'registration_number' => 'REG-AAA',
            'status'              => 'Active',
        ]);

        // Unrelated counterparty — should not appear.
        Counterparty::factory()->create([
            'legal_name'          => 'ZZZ Holdings',
            'registration_number' => 'REG-ZZZ',
            'status'              => 'Active',
        ]);

        $results = $this->service->findDuplicates(
            'Acme Corp UK',   // first 6 chars = "Acme C"
            'REG-NOMATCH',
            null
        );

        $this->assertCount(1, $results);
        $this->assertTrue($results->contains('id', $existing->id));
    }

    /**
     * Counterparties with status = 'Blacklisted' must never appear in duplicate
     * results, even when their registration_number is an exact match.
     */
    public function test_excludes_blacklisted(): void
    {
        Counterparty::factory()->create([
            'registration_number' => 'REG-BL',
            'status'              => 'Blacklisted',
        ]);

        $results = $this->service->findDuplicates(
            'Any Company Name',
            'REG-BL',
            null
        );

        $this->assertCount(0, $results);
        $this->assertTrue($results->isEmpty());
    }

    /**
     * When the caller supplies its own ID as $excludeId (edit-mode check),
     * that record must not appear in the returned collection even though it
     * matches on registration_number.
     */
    public function test_excludes_given_id(): void
    {
        $self = Counterparty::factory()->create([
            'registration_number' => 'REG-SELF',
            'status'              => 'Active',
        ]);

        $results = $this->service->findDuplicates(
            'Nonmatching Name XYZ',
            'REG-SELF',
            $self->id
        );

        $this->assertCount(0, $results);
        $this->assertFalse($results->contains('id', $self->id));
    }

    /**
     * When more than 5 records match, only 5 should be returned.
     */
    public function test_limits_to_five_results(): void
    {
        // Create 7 counterparties all sharing the same registration number.
        Counterparty::factory()->count(7)->create([
            'registration_number' => 'REG-MANY',
            'status'              => 'Active',
        ]);

        $results = $this->service->findDuplicates(
            'Nonmatching Name XYZ',
            'REG-MANY',
            null
        );

        $this->assertCount(5, $results);
    }

    /**
     * When neither the registration_number nor the first-6-char prefix of
     * legal_name matches any existing counterparty, an empty collection is
     * returned.
     */
    public function test_returns_empty_when_no_matches(): void
    {
        Counterparty::factory()->create([
            'legal_name'          => 'Alpha Corp',
            'registration_number' => 'REG-ALPHA',
            'status'              => 'Active',
        ]);

        $results = $this->service->findDuplicates(
            'Zeta Enterprises',   // first 6 chars = "Zeta E" — no match
            'REG-NOMATCH',
            null
        );

        $this->assertTrue($results->isEmpty());
    }

    /**
     * Returned models must only carry the five selected fields; no additional
     * attributes should be hydrated (guards against accidental field leakage).
     */
    public function test_returned_fields_are_limited_to_expected_columns(): void
    {
        Counterparty::factory()->create([
            'registration_number' => 'REG-COLS',
            'status'              => 'Active',
            'jurisdiction'        => 'AU',
        ]);

        $result = $this->service->findDuplicates(
            'Nonmatching Name XYZ',
            'REG-COLS',
            null
        )->first();

        $this->assertNotNull($result);

        $expected = ['id', 'legal_name', 'registration_number', 'status', 'jurisdiction'];
        $actual   = array_keys($result->getAttributes());

        sort($expected);
        sort($actual);

        $this->assertSame($expected, $actual);
    }
}
