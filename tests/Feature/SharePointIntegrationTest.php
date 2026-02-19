<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;

beforeEach(function () {
    $this->user = User::create(['id' => 'test-user', 'email' => 'test@example.com', 'name' => 'Test']);
    $this->actingAs($this->user);
});

it('stores sharepoint url and version on contract', function () {
    $region = Region::create(['name' => 'R1']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'E1']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'P1']);
    $cp = Counterparty::create(['legal_name' => 'CP1', 'status' => 'Active']);

    $contract = Contract::create([
        'region_id' => $region->id, 'entity_id' => $entity->id,
        'project_id' => $project->id, 'counterparty_id' => $cp->id,
        'contract_type' => 'Commercial', 'title' => 'SP Test',
    ]);

    $contract->update([
        'sharepoint_url' => 'https://digittalgroup.sharepoint.com/sites/legal/document.docx',
        'sharepoint_version' => '3.1',
    ]);

    $contract->refresh();
    expect($contract->sharepoint_url)->toContain('sharepoint.com');
    expect($contract->sharepoint_version)->toBe('3.1');
});
