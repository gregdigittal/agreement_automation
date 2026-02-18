'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Region {
  id: string;
  name: string;
}

export default function NewWikiContractPage() {
  const router = useRouter();
  const [regions, setRegions] = useState<Region[]>([]);
  const [name, setName] = useState('');
  const [category, setCategory] = useState('');
  const [regionId, setRegionId] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions?limit=500')
      .then((r) => r.json())
      .then(setRegions)
      .catch(() => undefined);
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const res = await fetch('/api/ccrs/wiki-contracts', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name,
        category: category || undefined,
        regionId: regionId || undefined,
        description: description || undefined,
      }),
    });
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    const created = await res.json();
    router.push(`/wiki-contracts/${created.id}`);
  }

  return (
    <div className="space-y-4 max-w-xl">
      <h1 className="text-2xl font-bold">New WikiContract</h1>
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="name">Name</Label>
          <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label htmlFor="category">Category</Label>
          <Input id="category" value={category} onChange={(e) => setCategory(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Region</Label>
          <select
            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={regionId}
            onChange={(e) => setRegionId(e.target.value)}
          >
            <option value="">All regions</option>
            {regions.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Input id="description" value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button type="submit">Create</Button>
      </form>
    </div>
  );
}
