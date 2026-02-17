'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

export function CounterpartyDetailPage({ id }: { id: string }) {
  const router = useRouter();
  const [data, setData] = useState<{
    legal_name: string;
    registration_number: string | null;
    address: string | null;
    jurisdiction: string | null;
    status: string;
  } | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch(`/api/ccrs/counterparties/${id}`)
      .then((r) => (r.ok ? r.json() : null))
      .then(setData)
      .finally(() => setLoading(false));
  }, [id]);
  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (!data) {
    router.push('/counterparties');
    return null;
  }
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">{data.legal_name}</h1>
        <Badge variant={data.status === 'Active' ? 'default' : 'secondary'}>{data.status}</Badge>
      </div>
      <Card>
        <CardHeader><CardTitle>Details</CardTitle></CardHeader>
        <CardContent className="space-y-2">
          {data.registration_number && <p><span className="font-medium">Registration:</span> {data.registration_number}</p>}
          {data.address && <p><span className="font-medium">Address:</span> {data.address}</p>}
          {data.jurisdiction && <p><span className="font-medium">Jurisdiction:</span> {data.jurisdiction}</p>}
          <Button variant="outline" size="sm" asChild className="mt-2">
            <Link href="/counterparties">Back to list</Link>
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
