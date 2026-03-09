<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Scout\Searchable;

class Contract extends Model
{
    use HasFactory, HasUuidPrimaryKey, Searchable;
    protected $fillable = ['region_id', 'entity_id', 'second_entity_id', 'project_id', 'counterparty_id', 'governing_law_id', 'parent_contract_id', 'contract_type', 'contract_ref', 'title', 'storage_path', 'file_name', 'file_version', 'sharepoint_url', 'sharepoint_version', 'exchange_room_enabled', 'sharepoint_enabled', 'sharepoint_folder_id', 'sharepoint_site_id', 'sharepoint_drive_id', 'expiry_date', 'created_by', 'updated_by', 'is_restricted'];
    protected $casts = ['file_version' => 'integer', 'workflow_state' => 'string', 'signing_status' => 'string', 'expiry_date' => 'date', 'is_restricted' => 'boolean', 'exchange_room_enabled' => 'boolean', 'sharepoint_enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::creating(function (Contract $contract) {
            if (empty($contract->contract_ref)) {
                $contract->contract_ref = static::generateContractRef($contract->contract_type);
            }
        });
    }

    /**
     * Generate a human-readable contract reference like COM-2026-0042.
     */
    public static function generateContractRef(?string $contractType = null): string
    {
        $prefix = static::typePrefix($contractType);
        $year = now()->format('Y');

        $lastRef = static::where('contract_ref', 'LIKE', "{$prefix}-{$year}-%")
            ->orderByRaw("CAST(SUBSTRING_INDEX(contract_ref, '-', -1) AS UNSIGNED) DESC")
            ->value('contract_ref');

        $nextSeq = 1;
        if ($lastRef) {
            $parts = explode('-', $lastRef);
            $nextSeq = ((int) end($parts)) + 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $nextSeq);
    }

    private static function typePrefix(?string $contractType): string
    {
        if (! $contractType) {
            return 'CTR';
        }

        return match (strtolower($contractType)) {
            'commercial' => 'COM',
            'merchant' => 'MER',
            'inter-company' => 'INT',
            default => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $contractType), 0, 3)) ?: 'CTR',
        };
    }

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function secondEntity(): BelongsTo { return $this->belongsTo(Entity::class, 'second_entity_id'); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function governingLaw(): BelongsTo { return $this->belongsTo(GoverningLaw::class); }
    public function parentContract(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ContractLink::class, 'child_contract_id')->with('parentContract'); }
    public function keyDates(): HasMany { return $this->hasMany(ContractKeyDate::class); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function obligations(): HasMany { return $this->hasMany(ObligationsRegister::class); }
    public function languages(): HasMany { return $this->hasMany(ContractLanguage::class); }
    public function primaryLanguage() { return $this->hasOne(ContractLanguage::class)->where('is_primary', true); }
    public function childLinks(): HasMany { return $this->hasMany(ContractLink::class, 'parent_contract_id'); }
    public function parentLinks(): HasMany { return $this->hasMany(ContractLink::class, 'child_contract_id'); }

    public function amendments() { return $this->childLinks()->where('link_type', 'amendment')->with('childContract'); }
    public function renewals() { return $this->childLinks()->where('link_type', 'renewal')->with('childContract'); }
    public function sideLetters() { return $this->childLinks()->where('link_type', 'side_letter')->with('childContract'); }
    public function aiAnalyses(): HasMany { return $this->hasMany(AiAnalysisResult::class); }
    public function boldsignEnvelopes(): HasMany { return $this->hasMany(BoldsignEnvelope::class); }
    public function workflowInstances(): HasMany { return $this->hasMany(WorkflowInstance::class); }
    public function activeWorkflowInstance(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(WorkflowInstance::class)->where('state', 'active'); }
    public function merchantAgreementInputs(): HasMany { return $this->hasMany(MerchantAgreementInput::class); }
    public function redlineSessions(): HasMany { return $this->hasMany(RedlineSession::class); }
    public function complianceFindings(): HasMany { return $this->hasMany(ComplianceFinding::class); }
    public function kycPack(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(KycPack::class); }
    public function signingSessions(): HasMany { return $this->hasMany(SigningSession::class); }
    public function activeSigningSession() { return $this->hasOne(SigningSession::class)->where('status', 'active'); }
    public function exchangeRoom(): HasOne { return $this->hasOne(ExchangeRoom::class); }
    public function accessGrants(): HasMany { return $this->hasMany(ContractUserAccess::class); }
    public function authorizedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'contract_user_access')
            ->withPivot('access_level', 'granted_by')
            ->withTimestamps();
    }

    public function isStaging(): bool
    {
        return $this->workflow_state === 'staging';
    }

    public function isMetadataComplete(): bool
    {
        return $this->region_id
            && $this->entity_id
            && $this->project_id
            && $this->contract_type;
    }

    public function scopeStaging(Builder $query): Builder
    {
        return $query->where('workflow_state', 'staging');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'contract_ref' => $this->contract_ref,
            'title' => $this->title,
            'contract_type' => $this->contract_type,
            'workflow_state' => $this->workflow_state,
            'counterparty' => $this->counterparty?->legal_name,
            'region' => $this->region?->name,
            'entity' => $this->entity?->name,
            'project' => $this->project?->name,
        ];
    }

    public function searchableAs(): string
    {
        return 'contracts';
    }

    public function makeAllSearchableUsing($query)
    {
        return $query->with(['counterparty', 'region', 'entity', 'project']);
    }
}
