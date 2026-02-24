<?php

namespace App\Services;

use App\Models\Contract;

/**
 * RegulatoryComplianceService — Phase 2: Regulatory compliance checking.
 *
 * ARCHITECTURE:
 * This service will use the AI Worker to:
 * 1. Identify the contract's jurisdiction from extracted fields (entity.region -> country)
 * 2. Fetch applicable regulatory frameworks from a regulatory_frameworks table
 *    (maintained by System Admin: jurisdiction, framework_name, requirements_json)
 * 3. Run an AI check of the contract text against each applicable requirement
 * 4. Store findings in compliance_findings (contract_id, framework_id, requirement, status, evidence)
 * 5. Display findings in a "Compliance" tab on ContractResource
 *
 * PHASE 2 TABLES NEEDED:
 *   regulatory_frameworks:  id, jurisdiction_code, framework_name, requirements_json, is_active
 *   compliance_findings:    id, contract_id, framework_id, requirement_text,
 *                           status (compliant/non_compliant/unclear), evidence_clause,
 *                           ai_rationale, reviewed_by, reviewed_at
 *
 * NOT IMPLEMENTED IN THIS PHASE — stub only.
 */
class RegulatoryComplianceService
{
    /**
     * @throws \RuntimeException Phase 2 feature not yet implemented.
     */
    public function runComplianceCheck(Contract $contract): never
    {
        throw new \RuntimeException(
            'Regulatory compliance checking is a Phase 2 feature. ' .
            'See docs/Cursor-Prompt-Laravel-K.md Task 4.2 for the implementation architecture.'
        );
    }
}
