<?php
namespace App\Models;

use App\Traits\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Contract extends Model
{
    use HasFactory, HasUuidPrimaryKey;
    protected $fillable = ['region_id', 'entity_id', 'project_id', 'counterparty_id', 'parent_contract_id', 'contract_type', 'title', 'workflow_state', 'signing_status', 'storage_path', 'file_name', 'file_version', 'sharepoint_url', 'sharepoint_version', 'created_by', 'updated_by'];
    protected $casts = ['file_version' => 'integer', 'workflow_state' => 'string', 'signing_status' => 'string'];

    public function region(): BelongsTo { return $this->belongsTo(Region::class); }
    public function entity(): BelongsTo { return $this->belongsTo(Entity::class); }
    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function counterparty(): BelongsTo { return $this->belongsTo(Counterparty::class); }
    public function parentContract(): BelongsTo { return $this->belongsTo(Contract::class, 'parent_contract_id'); }
    public function keyDates(): HasMany { return $this->hasMany(ContractKeyDate::class); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function obligations(): HasMany { return $this->hasMany(ObligationsRegister::class); }
    public function languages(): HasMany { return $this->hasMany(ContractLanguage::class); }
    public function primaryLanguage() { return $this->hasOne(ContractLanguage::class)->where('is_primary', true); }
    public function parentLinks(): HasMany { return $this->hasMany(ContractLink::class, 'parent_contract_id'); }
    public function childLinks(): HasMany { return $this->hasMany(ContractLink::class, 'child_contract_id'); }

    public function amendments() { return $this->parentLinks()->where('link_type', 'amendment')->with('childContract'); }
    public function renewals() { return $this->parentLinks()->where('link_type', 'renewal')->with('childContract'); }
    public function sideLetters() { return $this->parentLinks()->where('link_type', 'side_letter')->with('childContract'); }
    public function aiAnalyses(): HasMany { return $this->hasMany(AiAnalysisResult::class); }
    public function boldsignEnvelopes(): HasMany { return $this->hasMany(BoldsignEnvelope::class); }
    public function workflowInstances(): HasMany { return $this->hasMany(WorkflowInstance::class); }
    public function merchantAgreementInputs(): HasMany { return $this->hasMany(MerchantAgreementInput::class); }
}
