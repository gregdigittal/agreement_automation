import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function AuditPage() {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Audit trail</h1>
      <Card>
        <CardHeader>
          <CardTitle>Export audit log</CardTitle>
          <CardDescription>Restricted to Audit, Legal, and System Admin roles. Use the API: GET /api/ccrs/audit/export?from=...&to=...</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="text-sm text-muted-foreground">Phase 1a: Audit entries are recorded for region, entity, project, counterparty, and contract actions. Export is available via the backend API with role-based access.</p>
        </CardContent>
      </Card>
    </div>
  );
}
