<?php

namespace App\Filament\Resources\WorkflowTemplateResource\Pages;

use App\Filament\Resources\WorkflowTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWorkflowTemplates extends ListRecords
{
    protected static string $resource = WorkflowTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
