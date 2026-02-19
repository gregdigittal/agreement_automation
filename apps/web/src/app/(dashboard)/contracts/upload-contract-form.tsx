'use client';

import { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { handleApiError } from '@/lib/api-error';

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

  useEffect(() => {
    fetch('/api/ccrs/regions')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setRegions)
      .catch(() => toast.error('Failed to load regions'));
    fetch('/api/ccrs/counterparties')
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setCounterparties)
      .catch(() => toast.error('Failed to load counterparties'));
  }, []);
  useEffect(() => {
    if (!regionId) {
      setEntities([]);
      setEntityId('');
      return;
    }
    fetch(`/api/ccrs/entities?regionId=${regionId}`)
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setEntities)
      .catch(() => toast.error('Failed to load entities'));
    setEntityId('');
  }, [regionId]);
  useEffect(() => {
    if (!entityId) {
      setProjects([]);
      setProjectId('');
      return;
    }
    fetch(`/api/ccrs/projects?entityId=${entityId}`)
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then(setProjects)
      .catch(() => toast.error('Failed to load projects'));
    setProjectId('');
  }, [entityId]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!file || !regionId || !entityId || !projectId || !counterpartyId) {
      toast.error('Fill all required fields and choose a file (PDF or DOCX).');
      return;
    }
    setSubmitting(true);
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
      if (await handleApiError(res)) return;
      toast.success('Contract uploaded');
      router.push('/contracts');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-2">
        <Label>
          Region <span className="text-destructive">*</span>
        </Label>
        <Select value={regionId} onValueChange={setRegionId}>
          <SelectTrigger>
            <SelectValue placeholder="Select region" />
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
        <Label>
          Entity <span className="text-destructive">*</span>
        </Label>
        <Select value={entityId} onValueChange={setEntityId}>
          <SelectTrigger>
            <SelectValue placeholder="Select entity" />
          </SelectTrigger>
          <SelectContent>
            {entities.map((entity) => (
              <SelectItem key={entity.id} value={entity.id}>
                {entity.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>
          Project <span className="text-destructive">*</span>
        </Label>
        <Select value={projectId} onValueChange={setProjectId}>
          <SelectTrigger>
            <SelectValue placeholder="Select project" />
          </SelectTrigger>
          <SelectContent>
            {projects.map((project) => (
              <SelectItem key={project.id} value={project.id}>
                {project.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>
          Counterparty <span className="text-destructive">*</span>
        </Label>
        <Select value={counterpartyId} onValueChange={setCounterpartyId}>
          <SelectTrigger>
            <SelectValue placeholder="Select counterparty" />
          </SelectTrigger>
          <SelectContent>
            {counterparties.map((counterparty) => (
              <SelectItem key={counterparty.id} value={counterparty.id}>
                {(counterparty as { legal_name?: string }).legal_name ?? counterparty.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label>
          Contract type <span className="text-destructive">*</span>
        </Label>
        <Select value={contractType} onValueChange={(value) => setContractType(value as 'Commercial' | 'Merchant')}>
          <SelectTrigger>
            <SelectValue placeholder="Select contract type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="Commercial">Commercial</SelectItem>
            <SelectItem value="Merchant">Merchant</SelectItem>
          </SelectContent>
        </Select>
      </div>
      <div className="space-y-2">
        <Label htmlFor="title">Title (optional)</Label>
        <Input id="title" value={title} onChange={(e) => setTitle(e.target.value)} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="file">
          File (PDF or DOCX) <span className="text-destructive">*</span>
        </Label>
        <Input id="file" type="file" accept=".pdf,.docx" onChange={(e) => setFile(e.target.files?.[0] ?? null)} required />
      </div>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Uploading…' : 'Upload'}
        </Button>
      </div>
    </form>
  );
}
