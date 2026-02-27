#!/bin/bash
set -e

PROMPT_DIR="ccrs-tdd-prompts"
LOG_DIR="tdd-logs"
mkdir -p "$LOG_DIR"

FILES=(
  "00-shared-setup.md"
  "01-authentication.md"
  "02-rbac.md"
  "03-org-structure.md"
  "04-counterparties.md"
  "05-contract-crud.md"
  "06-contract-lifecycle.md"
  "07-workflow-templates.md"
  "08a-signing-sessions.md"
  "08b-signing-completion.md"
  "09-ai-analysis.md"
  "10-linked-contracts.md"
  "11-merchant-agreements.md"
  "12-notifications.md"
  "13-reports.md"
  "14-bulk-operations.md"
  "15-vendor-portal.md"
  "16-restricted-contracts.md"
  "17-compliance-audit.md"
  "18-global-search.md"
)

for file in "${FILES[@]}"; do
  echo ""
  echo "========================================"
  echo "  Processing: $file"
  echo "========================================"
  echo ""

  PROMPT="Read the file $PROMPT_DIR/$file and execute the instructions inside it. Create the test file as specified. Do not ask questions — just generate the complete test file."

  claude -p "$PROMPT" \
    --allowedTools "Edit,Write,Read,Bash" \
    --output-format text \
    2>&1 | tee "$LOG_DIR/${file%.md}.log"

  echo ""
  echo "✅ Completed: $file"
  echo "   Log: $LOG_DIR/${file%.md}.log"
  echo ""
done

echo ""
echo "========================================"
echo "  All 20 prompts processed!"
echo "  Logs in: $LOG_DIR/"
echo "========================================"
echo ""
echo "Run all tests with:"
echo "  php artisan test --testsuite=Feature"
