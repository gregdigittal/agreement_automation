'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { Entity } from '@/lib/types';

export function EditEntityForm({ id }: { id: string }) {
  const router = useRouter();
  const [entity, setEntity] = useState<Entity | null>(null);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [regionName, setRegionName] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch(`/api/ccrs/entities/${id}`)
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then((data: Entity) => {
        setEntity(data);
        setName(data.name ?? '');
        setCode(data.code ?? '');
        setRegionName(data.regions?.name ?? '');
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch(`/api/ccrs/entities/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, code: code || undefined }),
      });
      if (!res.ok) {
        setError((await res.text()) || 'Failed');
        return;
      }
      router.push('/entities');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (!entity) return <p className="text-sm text-destructive">{error ?? 'Entity not found'}</p>;

  return (
    <Card>
      <CardHeader>
        <CardTitle>Entity details</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={submit} className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="region">Region</Label>
            <Input id="region" value={regionName} disabled />
          </div>
          <div className="space-y-2">
            <Label htmlFor="name">Name</Label>
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="code">Code (optional)</Label>
            <Input id="code" value={code} onChange={(e) => setCode(e.target.value)} />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Saving…' : 'Save'}
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}
