<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ReportExportControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Unauthenticated requests to the Excel export endpoint are redirected.
     */
    public function test_contracts_excel_download_requires_auth(): void
    {
        Excel::fake();

        $response = $this->get(route('reports.export.contracts.excel'));

        // Unauthenticated — should redirect to login (302)
        $response->assertStatus(302);
    }

    /**
     * A user holding only the `operations` role (not in the allowed list) receives a 403.
     */
    public function test_contracts_excel_forbidden_without_role(): void
    {
        Excel::fake();

        $user = User::factory()->create();
        $user->assignRole('operations');

        $response = $this->actingAs($user)
            ->get(route('reports.export.contracts.excel'));

        $response->assertStatus(403);
    }

    /**
     * A user with the `legal` role can download the contracts XLSX export.
     */
    public function test_contracts_excel_works_for_legal_role(): void
    {
        Excel::fake();

        $user = User::factory()->create();
        $user->assignRole('legal');

        Contract::factory()->count(3)->create();

        $response = $this->actingAs($user)
            ->get(route('reports.export.contracts.excel'));

        $response->assertSuccessful();

        // assertDownloaded expects a string filename, not a closure
        Excel::assertDownloaded('contracts_' . now()->format('Ymd_His') . '.xlsx');
    }

    /**
     * A user with the `system_admin` role can download the contracts PDF report.
     */
    public function test_contracts_pdf_works_for_system_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        Contract::factory()->count(2)->create();

        $response = $this->actingAs($user)
            ->get(route('reports.export.contracts.pdf'));

        $response->assertSuccessful();
    }
}
