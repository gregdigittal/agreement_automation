'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface Option {
  id: string;
  name: string;
}

export function UploadContractForm() {
  const router = useRouter();
  const [regions, setRegions] = useState<Option[]>([]);
  const [entities, setEntities] = useState<Option[]>([]);
  const [projects, setProjects] = useState<Option[]>([]);
  const [counterparties, setCounterparties] = useState<Option[]>([]);
  const [regionId, setRegionId] = useState('');
  const [entityId, setEntityId] = useState('');
  const [projectId, setProjectId] = useState('');
  const [counterpartyId, setCounterpartyId] = useState('');
  const [contractType, setContractType] = useState<'Commercial' | 'Merchant'>('Commercial');
  const [title, setTitle] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions').then((r) => r.json()).then(setRegions);
    fetch('/api/ccrs/counterparties').then((r) => r.json()).then(setCounterparties);
  }, []);
  useEffect(() => {
    if (!regionId) { setEntities([]); setEntityId(''); return; }
    fetch(`/api/ccrs/entities?regionId=${regionId}`).then((r) => r.json()).then(setEntities);
    setEntityId('');
  }, [regionId]);
  useEffect(() => {
    if (!entityId) { setProjects([]); setProjectId(''); return; }
    fetch(`/api/ccrs/projects?entityId=${entityId}`).then((r) => r.json()).then(setProjects);
    setProjectId('');
  }, [entityId]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file || !regionId || !entityId || !projectId || !counterpartyId) {
      setError('Fill all required fields and choose a file (PDF or DOCX).');
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const form = new FormData();
      form.append('file', file);
      form.append('regionId', regionId);
      form.append('entityId', entityId);
      form.append('projectId', projectId);
      form.append('counterpartyId', counterpartyId);
      form.append('contractType', contractType);
      if (title) form.append('title', title);
      const res = await fetch('/api/ccrs/contracts/upload', { method: 'POST', body: form });
      if (!res.ok) { setError(await res.text()); return; }
      router.push('/contracts');
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
        <Label>Entity</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={entityId} onChange={(e) => setEntityId(e.target.value)} required>
          <option value="">Select entity</option>
          {entities.map((e) => <option key={e.id} value={e.id}>{e.name}</option>)}
        </select>
      </div>
      <div className="space-y-2">
        <Label>Project</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={projectId} onChange={(e) => setProjectId(e.target.value)} required>
          <option value="">Select project</option>
          {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
        </select>
      </div>
      <div className="space-y-2">
        <Label>Counterparty</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={counterpartyId} onChange={(e) => setCounterpartyId(e.target.value)} required>
          <option value="">Select counterparty</option>
          {counterparties.map((c) => <option key={c.id} value={c.id}>{(c as { legal_name?: string }).legal_name ?? c.name}</option>)}
        </select>
      </div>
      <div className="space-y-2">
        <Label>Contract type</Label>
        <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={contractType} onChange={(e) => setContractType(e.target.value as 'Commercial' | 'Merchant')}>
          <option value="Commercial">Commercial</option>
          <option value="Merchant">Merchant</option>
        </select>
      </div>
      <div className="space-y-2">
        <Label htmlFor="title">Title (optional)</Label>
        <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="file">File (PDF or DOCX)</Label>
        <Input id="file" type="file" accept=".pdf,.docx" onChange={(e) => setFile(e.target.files?.[0] ?? null)} required />
      </div>
      {error && <p className="text-sm text-destructive">{error}</p>}
      <Button type="submit" disabled={submitting}>{submitting ? 'Uploading…' : 'Upload'}</Button>
    </form>
  );
}
