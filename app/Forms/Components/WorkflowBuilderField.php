<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class WorkflowBuilderField extends Field
{
    protected string $view = 'forms.components.workflow-builder-field';

    public function getDefaultState(): mixed
    {
        return [];
    }
}
