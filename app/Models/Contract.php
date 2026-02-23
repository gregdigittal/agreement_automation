<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;
class Contract extends Model
{
    use HasFactory, HasUuidPrimaryKey, Searchable;
    protected $fillable = ['region_id', 'entity_id', 'project_id', 'counterparty_id', 'parent_contract_id', 'contract_type', 'title', 'workflow_state', 'signing_status', 'storage_path', 'file_name', 'file_version', 'sharepoint_url', 'sharepoint_version', 'expiry_date', 'created_by', 'updated_by'];
    protected $casts = ['file_version' => 'integer', 'workflow_state' => 'string', 'signing_status' => 'string', 'expiry_date' => 'date'];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
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
    public function merchantAgreementInputs(): HasMany { return $this->hasMany(MerchantAgreementInput::class); }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
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
}
