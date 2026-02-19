<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Field;

class WorkflowBuilderField extends Field
{
    protected string $view = 'forms.components.workflow-builder-field';

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->dehydrateStateUsing(function ($state) {
            return is_array($state) ? $state : [];
        });
    }
}
