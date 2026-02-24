<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\WikiContract;

/**
 * RedlineService — Phase 2: AI-assisted clause negotiation and redlining.
 *
 * ARCHITECTURE:
 * This service will use the AI Worker microservice (ai-worker/) to:
 * 1. Parse a DOCX contract into clauses using python-docx
 * 2. Compare each clause against the matching WikiContract template
 * 3. Generate AI-suggested redline changes using Claude (structured diff output)
 * 4. Store redline suggestions in a new redline_suggestions table
 * 5. Allow legal users to accept/reject/modify suggestions in Filament
 *
 * PHASE 2 TABLES NEEDED:
 *   redline_sessions:  id, contract_id, wiki_contract_id, status, created_by, created_at
 *   redline_clauses:   id, session_id, clause_number, original_text, suggested_text,
 *                      change_type (addition/deletion/modification), ai_rationale,
 *                      status (pending/accepted/rejected/modified), reviewed_by
 *
 * PHASE 2 FILAMENT ADDITIONS NEEDED:
 *   - ContractResource: "Redline" action -> opens RedlineSessionPage
 *   - RedlineSessionPage: side-by-side diff view with accept/reject per clause
 *
 * NOT IMPLEMENTED IN THIS PHASE — stub only.
 */
class RedlineService
{
    /**
     * @throws \RuntimeException Phase 2 feature not yet implemented.
     */
    public function startRedlineSession(Contract $contract, WikiContract $template): never
    {
        throw new \RuntimeException(
            'Clause redlining is a Phase 2 feature. ' .
            'See docs/Cursor-Prompt-Laravel-K.md Task 4.1 for the implementation architecture.'
        );
    }
}
