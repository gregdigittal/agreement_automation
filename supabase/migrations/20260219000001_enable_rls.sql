-- Enable RLS on all public tables.
-- The FastAPI backend uses service_role key which bypasses RLS.
-- These policies protect against direct anon/authenticated client access.

DO $$
DECLARE
  tbl RECORD;
BEGIN
  FOR tbl IN
    SELECT tablename FROM pg_tables WHERE schemaname = 'public'
  LOOP
    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', tbl.tablename);

    -- Drop existing policies if any, then recreate
    EXECUTE format('DROP POLICY IF EXISTS service_role_all ON public.%I', tbl.tablename);
    EXECUTE format(
      'CREATE POLICY service_role_all ON public.%I FOR ALL TO service_role USING (true) WITH CHECK (true)',
      tbl.tablename
    );

    EXECUTE format('DROP POLICY IF EXISTS authenticated_select ON public.%I', tbl.tablename);
    EXECUTE format(
      'CREATE POLICY authenticated_select ON public.%I FOR SELECT TO authenticated USING (true)',
      tbl.tablename
    );
  END LOOP;
END
$$;
