'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { handleApiError } from '@/lib/api-error';

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

  useEffect(() => {
    fetch('/api/ccrs/regions?limit=500')
      .then((r) => r.json())
      .then(setRegions)
      .catch(() => toast.error('Failed to load regions'));
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
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
    if (await handleApiError(res)) return;
    const created = await res.json();
    toast.success('Template created');
    router.push(`/wiki-contracts/${created.id}`);
  }

  return (
    <div className="space-y-4 max-w-xl">
      <h1 className="text-2xl font-bold">New WikiContract</h1>
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-2">
          <Label htmlFor="name">
            Name <span className="text-destructive">*</span>
          </Label>
          <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label htmlFor="category">
            Category <span className="text-destructive">*</span>
          </Label>
          <Input id="category" value={category} onChange={(e) => setCategory(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label>Region</Label>
          <Select value={regionId} onValueChange={setRegionId}>
            <SelectTrigger>
              <SelectValue placeholder="All regions" />
            </SelectTrigger>
            <SelectContent>
              {regions.map((r) => (
                <SelectItem key={r.id} value={r.id}>
                  {r.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="description">Description</Label>
          <Input id="description" value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => router.back()}>
            Cancel
          </Button>
          <Button type="submit">Create</Button>
        </div>
      </form>
    </div>
  );
}
