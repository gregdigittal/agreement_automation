'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Entity {
  id: string;
  name: string;
}

export function CreateProjectForm() {
  const router = useRouter();
  const [entities, setEntities] = useState<Entity[]>([]);
  const [entityId, setEntityId] = useState('');
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/entities').then((r) => r.json()).then(setEntities);
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!entityId) { setError('Select an entity'); return; }
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch('/api/ccrs/projects', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ entityId, name, code: code || undefined }),
      });
      if (!res.ok) { setError(await res.text()); return; }
      router.push('/projects');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-2">
        <Label>Entity</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={entityId} onChange={(e) => setEntityId(e.target.value)} required>
          <option value="">Select entity</option>
          {entities.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
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
