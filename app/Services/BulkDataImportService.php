<?php

namespace App\Services;

use App\Mail\UserInviteMail;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class BulkDataImportService
{
    public const HEADERS = [
        'regions' => ['name', 'code'],
        'entities' => ['region_code', 'name', 'code', 'legal_name', 'registration_number'],
        'projects' => ['entity_code', 'name', 'code'],
        'users' => ['name', 'email', 'roles'],
        'counterparties' => ['legal_name', 'registration_number', 'address', 'jurisdiction', 'status'],
    ];

    public const REQUIRED = [
        'regions' => ['name'],
        'entities' => ['region_code', 'name'],
        'projects' => ['entity_code', 'name'],
        'users' => ['name', 'email', 'roles'],
        'counterparties' => ['legal_name'],
    ];

    public function import(string $type, string $csvPath): array
    {
        if (!array_key_exists($type, self::HEADERS)) {
            return ['success' => 0, 'failed' => 0, 'errors' => ["Invalid import type: {$type}"]];
        }

        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            return ['success' => 0, 'failed' => 0, 'errors' => ['Could not open CSV file.']];
        }

        // Strip UTF-8 BOM if present
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
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
        $validRoles = Role::where('guard_name', 'web')->pluck('name')->toArray();

        // Parse comma-separated roles
        $roles = array_map('trim', array_map('strtolower', explode(',', $row['roles'])));
        $invalid = array_diff($roles, $validRoles);
        if (!empty($invalid)) {
            throw new \RuntimeException("Invalid role(s): " . implode(', ', $invalid) . ". Valid: " . implode(', ', $validRoles));
        }

        $email = strtolower(trim($row['email']));

        // Skip existing users
        if (User::where('email', $email)->exists()) {
            throw new \RuntimeException("User with email '{$email}' already exists.");
        }

        $user = DB::transaction(function () use ($row, $email, $roles) {
            $user = User::create([
                'name' => $row['name'],
                'email' => $email,
                'status' => 'active',
            ]);

            $user->syncRoles($roles);

            return $user;
        });

        Mail::to($user->email)
            ->queue(new UserInviteMail($user, $roles));
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
        $csv = implode(',', $headers) . "\n";

        if ($type === 'users') {
            $csv .= "Jane Smith,jane@company.com,legal\n";
            $csv .= "John Doe,john@company.com,\"commercial,finance\"\n";
        }

        return $csv;
    }
}
