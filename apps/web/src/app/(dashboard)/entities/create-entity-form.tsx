'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Region {
  id: string;
  name: string;
}

export function CreateEntityForm() {
  const router = useRouter();
  const [regions, setRegions] = useState<Region[]>([]);
  const [regionId, setRegionId] = useState('');
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions').then((r) => r.json()).then(setRegions);
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!regionId) { setError('Select a region'); return; }
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch('/api/ccrs/entities', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ regionId, name, code: code || undefined }),
      });
      if (!res.ok) { setError(await res.text()); return; }
      router.push('/entities');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-2">
        <Label>Region</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={regionId} onChange={(e) => setRegionId(e.target.value)} required>
          <option value="">Select region</option>
          {regions.map((r) => <option key={r.id} value={r.id}>{r.name}</option>)}
        </select>
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
      <Button type="submit" disabled={submitting}>{submitting ? 'Creating…' : 'Create'}</Button>
    </form>
  );
}
