<?php

namespace App\Filament\Pages;

use App\Models\StoredSignature;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class MySignaturesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static string $view = 'filament.pages.my-signatures';
    protected static ?int $navigationSort = 95;
    protected static ?string $title = 'My Signatures';
    protected static ?string $navigationLabel = 'My Signatures';

    public ?array $data = [];
    public bool $showAddForm = false;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('label')
                    ->label('Label')
                    ->placeholder('e.g. My formal signature')
                    ->maxLength(100)
                    ->helperText('A descriptive name for this signature.'),

                Select::make('type')
                    ->label('Type')
                    ->options([
                        'signature' => 'Full Signature',
                        'initials' => 'Initials',
                    ])
                    ->default('signature')
                    ->required(),

                Select::make('capture_method')
                    ->label('Capture Method')
                    ->options([
                        'draw' => 'Draw',
                        'type' => 'Type',
                        'upload' => 'Upload Image',
                        'webcam' => 'Camera',
                    ])
                    ->default('draw')
                    ->required()
                    ->live(),

                TextInput::make('typed_text')
                    ->label('Type your signature')
                    ->visible(fn ($get) => $get('capture_method') === 'type')
                    ->placeholder('Type your full name')
                    ->maxLength(255),

                FileUpload::make('upload_file')
                    ->label('Upload signature image')
                    ->visible(fn ($get) => $get('capture_method') === 'upload')
                    ->acceptedFileTypes(['image/png', 'image/jpeg'])
                    ->disk('local')
                    ->directory('temp-signatures'),

                Toggle::make('is_default')
                    ->label('Set as default')
                    ->helperText('Default signatures are automatically suggested when signing documents.'),
            ])
            ->statePath('data');
    }

    public function toggleAddForm(): void
    {
        $this->showAddForm = !$this->showAddForm;
        if ($this->showAddForm) {
            $this->form->fill();
        }
    }

    /**
     * Save a signature captured via draw or webcam (base64 from JS).
     */
    public function saveDrawnSignature(string $imageData, string $label, string $type, string $method, bool $isDefault): void
    {
        $user = auth()->user();

        // Validate base64 image
        $decoded = base64_decode($imageData, true);
        if ($decoded === false) {
            Notification::make()->title('Invalid signature data.')->danger()->send();
            return;
        }

        $imageInfo = @getimagesizefromstring($decoded);
        if ($imageInfo === false || !in_array($imageInfo['mime'], ['image/png', 'image/jpeg'])) {
            Notification::make()->title('Signature must be a valid PNG or JPEG image.')->danger()->send();
            return;
        }

        $path = "stored-signatures/{$user->id}/" . \Illuminate\Support\Str::uuid() . '.png';
        $disk = config('ccrs.contracts_disk', 's3');
        Storage::disk($disk)->put($path, $decoded);

        // Clear default if setting this as default
        if ($isDefault) {
            StoredSignature::where('user_id', $user->id)
                ->where('type', $type)
                ->update(['is_default' => false]);
        }

        StoredSignature::create([
            'user_id' => $user->id,
            'label' => $label ?: ($type === 'initials' ? 'My initials' : 'My signature'),
            'type' => $type,
            'capture_method' => $method,
            'image_path' => $path,
            'is_default' => $isDefault,
        ]);

        Notification::make()->title('Signature saved successfully.')->success()->send();
        $this->showAddForm = false;
    }

    /**
     * Save a typed signature.
     */
    public function saveTypedSignature(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (empty($data['typed_text'])) {
            Notification::make()->title('Please type your name.')->danger()->send();
            return;
        }

        // Generate a simple signature image from typed text server-side isn't practical.
        // Instead, the frontend JS will generate a canvas image and call saveDrawnSignature.
        Notification::make()->title('Use the draw pad to generate typed signatures.')->info()->send();
    }

    /**
     * Save an uploaded signature.
     */
    public function saveUploadedSignature(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();

        if (empty($data['upload_file'])) {
            Notification::make()->title('Please upload a signature image.')->danger()->send();
            return;
        }

        $localPath = Storage::disk('local')->path($data['upload_file']);
        $imageData = file_get_contents($localPath);

        // Validate uploaded file is a real image
        $imageInfo = @getimagesizefromstring($imageData);
        if ($imageInfo === false || !in_array($imageInfo['mime'], ['image/png', 'image/jpeg'])) {
            Storage::disk('local')->delete($data['upload_file']);
            Notification::make()->title('Uploaded file is not a valid image.')->danger()->send();
            return;
        }

        $path = "stored-signatures/{$user->id}/" . \Illuminate\Support\Str::uuid() . '.png';
        $disk = config('ccrs.contracts_disk', 's3');
        Storage::disk($disk)->put($path, $imageData);

        // Clean up local temp
        Storage::disk('local')->delete($data['upload_file']);

        $type = $data['type'] ?? 'signature';
        $isDefault = $data['is_default'] ?? false;

        if ($isDefault) {
            StoredSignature::where('user_id', $user->id)
                ->where('type', $type)
                ->update(['is_default' => false]);
        }

        StoredSignature::create([
            'user_id' => $user->id,
            'label' => $data['label'] ?: ($type === 'initials' ? 'My initials' : 'My signature'),
            'type' => $type,
            'capture_method' => 'upload',
            'image_path' => $path,
            'is_default' => $isDefault,
        ]);

        Notification::make()->title('Signature saved successfully.')->success()->send();
        $this->showAddForm = false;
        $this->form->fill();
    }

    /**
     * Set a stored signature as the default for its type.
     */
    public function setDefault(string $signatureId): void
    {
        $user = auth()->user();
        $signature = StoredSignature::where('id', $signatureId)
            ->where('user_id', $user->id)
            ->first();

        if (!$signature) {
            return;
        }

        StoredSignature::where('user_id', $user->id)
            ->where('type', $signature->type)
            ->update(['is_default' => false]);

        $signature->update(['is_default' => true]);

        Notification::make()->title('Default signature updated.')->success()->send();
    }

    /**
     * Delete a stored signature.
     */
    public function deleteSignature(string $signatureId): void
    {
        $user = auth()->user();
        $signature = StoredSignature::where('id', $signatureId)
            ->where('user_id', $user->id)
            ->first();

        if (!$signature) {
            return;
        }

        // Delete image from storage
        $disk = config('ccrs.contracts_disk', 's3');
        Storage::disk($disk)->delete($signature->image_path);

        $signature->delete();

        Notification::make()->title('Signature deleted.')->success()->send();
    }

    public function getStoredSignaturesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return StoredSignature::where('user_id', auth()->id())
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get();
    }
}
