import Link from 'next/link';
import { auth } from '@/auth';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default async function DashboardPage() {
  const session = await auth();
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Dashboard</h1>
        <p className="text-muted-foreground">Phase 1a — Foundation and core data</p>
      </div>
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        <Card>
          <CardHeader>
            <CardTitle>Regions</CardTitle>
            <CardDescription>Org structure: regions, entities, projects</CardDescription>
          </CardHeader>
          <CardContent>
            <Link href="/regions" className="text-primary hover:underline">Manage regions →</Link>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Counterparties</CardTitle>
            <CardDescription>External parties and duplicate check</CardDescription>
          </CardHeader>
          <CardContent>
            <Link href="/counterparties" className="text-primary hover:underline">Manage counterparties →</Link>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Contracts</CardTitle>
            <CardDescription>Upload, search, and repository</CardDescription>
          </CardHeader>
          <CardContent>
            <Link href="/contracts" className="text-primary hover:underline">View contracts →</Link>
          </CardContent>
        </Card>
      </div>
      {session?.user && (
        <p className="text-sm text-muted-foreground">Signed in as {session.user.email ?? session.user.name}</p>
      )}
    </div>
  );
}
