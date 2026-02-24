<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Helpers\Feature;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Services\RedlineService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class RedlineSessionPage extends Page
{
    protected static string $resource = ContractResource::class;
    protected static string $view = 'filament.resources.contract-resource.pages.redline-session';
    protected static ?string $title = 'Redline Review';

    public RedlineSession $session;
    public string $sessionId;

    public function mount(string $record, string $session): void
    {
        if (Feature::disabled('redlining')) {
            abort(404, 'Redlining feature is not enabled.');
        }

        $this->sessionId = $session;
        $this->session = RedlineSession::with([
            'contract',
            'wikiContract',
            'clauses' => fn ($q) => $q->orderBy('clause_number'),
            'creator',
        ])->findOrFail($session);

        if ($this->session->contract_id !== $record) {
            abort(404);
        }
    }

    public function getTitle(): string
    {
        return "Redline Review: {$this->session->contract->title}";
    }

    public function getBreadcrumbs(): array
    {
        return [
            ContractResource::getUrl() => 'Contracts',
            ContractResource::getUrl('view', ['record' => $this->session->contract_id]) => $this->session->contract->title,
            'Redline Review',
        ];
    }

    public function acceptClause(string $clauseId): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'accepted', null, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} accepted")
            ->success()
            ->send();
    }

    public function rejectClause(string $clauseId): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'rejected', null, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} rejected")
            ->info()
            ->send();
    }

    public function modifyClause(string $clauseId, string $finalText): void
    {
        $clause = RedlineClause::findOrFail($clauseId);
        app(RedlineService::class)->reviewClause($clause, 'modified', $finalText, auth()->user());

        $this->refreshSession();

        Notification::make()
            ->title("Clause {$clause->clause_number} modified")
            ->warning()
            ->send();
    }

    public function generateFinalDocument(): void
    {
        try {
            app(RedlineService::class)->generateFinalDocument($this->session);

            Notification::make()
                ->title('Final document generated')
                ->body('The redlined document has been saved. You can download it from the contract files.')
                ->success()
                ->send();

        } catch (\RuntimeException $e) {
            Notification::make()
                ->title('Cannot generate document')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    private function refreshSession(): void
    {
        $this->session = $this->session->fresh([
            'contract',
            'wikiContract',
            'clauses' => fn ($q) => $q->orderBy('clause_number'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateFinal')
                ->label('Generate Final Document')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->disabled(fn (): bool => !$this->session->isFullyReviewed())
                ->requiresConfirmation()
                ->modalHeading('Generate Final Document')
                ->modalDescription('This will compile all reviewed clauses into a final DOCX document and upload it to the contract\'s file storage.')
                ->action(fn () => $this->generateFinalDocument()),

            Action::make('backToContract')
                ->label('Back to Contract')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => ContractResource::getUrl('view', ['record' => $this->session->contract_id])),
        ];
    }
}
