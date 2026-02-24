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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    /**
     * Unauthenticated requests to the Excel export endpoint are redirected to login.
     */
    public function test_contracts_excel_download_requires_auth(): void
    {
        Excel::fake();

        $response = $this->get(route('reports.export.contracts.excel'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
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

        Excel::assertDownloaded(function (string $filename) {
            return str_starts_with($filename, 'contracts_') && str_ends_with($filename, '.xlsx');
        });
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
        $response->assertStatus(200);
    }
}
