<?php

namespace App\Services;

use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BulkDataImportService
{
    public const HEADERS = [
        'regions' => ['name', 'code'],
        'entities' => ['region_code', 'name', 'code', 'legal_name', 'registration_number'],
        'projects' => ['entity_code', 'name', 'code'],
        'users' => ['name', 'email', 'role'],
        'counterparties' => ['legal_name', 'registration_number', 'address', 'jurisdiction', 'status'],
    ];

    public const REQUIRED = [
        'regions' => ['name'],
        'entities' => ['region_code', 'name'],
        'projects' => ['entity_code', 'name'],
        'users' => ['name', 'email', 'role'],
        'counterparties' => ['legal_name'],
    ];

    public function import(string $type, string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Could not open CSV file.']];
        }

        $headers = fgetcsv($handle);
        if (!$headers) {
            fclose($handle);
            return ['success' => 0, 'failed' => 0, 'errors' => ['CSV file is empty or has no headers.']];
        }

        $headers = array_map('trim', array_map('strtolower', $headers));
        $required = self::REQUIRED[$type] ?? [];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) {
            fclose($handle);
            return ['success' => 0, 'failed' => 0, 'errors' => ['Missing required columns: ' . implode(', ', $missing)]];
        }

        $success = 0;
        $failed = 0;
        $errors = [];
        $rowNum = 1;

        while (($line = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($line) !== count($headers)) {
                $errors[] = "Row {$rowNum}: column count mismatch.";
                $failed++;
                continue;
            }

            $row = array_combine($headers, $line);

            try {
                match ($type) {
                    'regions' => $this->importRegion($row),
                    'entities' => $this->importEntity($row),
                    'projects' => $this->importProject($row),
                    'users' => $this->importUser($row),
                    'counterparties' => $this->importCounterparty($row),
                };
                $success++;
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                $failed++;
            }
        }

        fclose($handle);

        return compact('success', 'failed', 'errors');
    }

    private function importRegion(array $row): void
    {
        Region::create([
            'name' => $row['name'],
            'code' => $row['code'] ?? null,
        ]);
    }

    private function importEntity(array $row): void
    {
        $region = Region::where('code', $row['region_code'])->first();
        if (!$region) {
            throw new \RuntimeException("Region with code '{$row['region_code']}' not found.");
        }

        Entity::create([
            'region_id' => $region->id,
            'name' => $row['name'],
            'code' => $row['code'] ?? null,
            'legal_name' => $row['legal_name'] ?? null,
            'registration_number' => $row['registration_number'] ?? null,
        ]);
    }

    private function importProject(array $row): void
    {
        $entity = Entity::where('code', $row['entity_code'])->first();
        if (!$entity) {
            throw new \RuntimeException("Entity with code '{$row['entity_code']}' not found.");
        }

        Project::create([
            'entity_id' => $entity->id,
            'name' => $row['name'],
            'code' => $row['code'] ?? null,
        ]);
    }

    private function importUser(array $row): void
    {
        $validRoles = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];
        $role = strtolower(trim($row['role']));
        if (!in_array($role, $validRoles)) {
            throw new \RuntimeException("Invalid role '{$row['role']}'. Valid: " . implode(', ', $validRoles));
        }

        $user = User::firstOrCreate(
            ['email' => strtolower(trim($row['email']))],
            ['name' => $row['name']],
        );

        if (!$user->hasRole($role)) {
            $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            $user->assignRole($roleModel);
        }
    }

    private function importCounterparty(array $row): void
    {
        Counterparty::create([
            'legal_name' => $row['legal_name'],
            'registration_number' => $row['registration_number'] ?? null,
            'address' => $row['address'] ?? null,
            'jurisdiction' => $row['jurisdiction'] ?? null,
            'status' => $row['status'] ?? 'Active',
        ]);
    }

    public function generateTemplate(string $type): string
    {
        $headers = self::HEADERS[$type] ?? [];
        return implode(',', $headers) . "\n";
    }
}
