-- Enable pg_trgm for fuzzy matching (Epic 8.2)
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- Add GIN trigram index for counterparty fuzzy search
CREATE INDEX IF NOT EXISTS idx_counterparties_legal_name_trgm
  ON counterparties USING GIN (legal_name gin_trgm_ops);

-- Add supporting_document_ref to counterparties (Epic 17.1 — was accepted but not persisted)
ALTER TABLE counterparties ADD COLUMN IF NOT EXISTS supporting_document_ref TEXT;

-- Add updated_at trigger for all tables (fixes manual updated_at in application code)
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
DECLARE
  t TEXT;
BEGIN
  FOR t IN SELECT unnest(ARRAY[
    'regions','entities','projects','counterparties',
    'contracts','counterparty_contacts','signing_authority'
  ])
  LOOP
    EXECUTE format('DROP TRIGGER IF EXISTS set_updated_at ON %I', t);
    EXECUTE format(
      'CREATE TRIGGER set_updated_at BEFORE UPDATE ON %I FOR EACH ROW EXECUTE PROCEDURE update_updated_at_column()',
      t
    );
  END LOOP;
END;
$$;
