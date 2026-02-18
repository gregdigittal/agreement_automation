'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface WikiContract {
  id: string;
  name: string;
  category: string | null;
  region_id: string | null;
  description: string | null;
  status: string;
  storage_path: string | null;
}

interface Region {
  id: string;
  name: string;
}

export default function WikiContractDetail({ id }: { id: string }) {
  const router = useRouter();
  const [data, setData] = useState<WikiContract | null>(null);
  const [regions, setRegions] = useState<Region[]>([]);
  const [name, setName] = useState('');
  const [category, setCategory] = useState('');
  const [regionId, setRegionId] = useState('');
  const [description, setDescription] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [downloadUrl, setDownloadUrl] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions?limit=500')
      .then((r) => r.json())
      .then(setRegions)
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    fetch(`/api/ccrs/wiki-contracts/${id}`)
      .then((r) => (r.ok ? r.json() : null))
      .then((payload) => {
        if (!payload) return;
        setData(payload);
        setName(payload.name ?? '');
        setCategory(payload.category ?? '');
        setRegionId(payload.region_id ?? '');
        setDescription(payload.description ?? '');
      })
      .catch(() => undefined);
  }, [id]);

  async function save() {
    setError(null);
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}`, {
      method: 'PATCH',
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
    setData(await res.json());
  }

  async function publish() {
    setError(null);
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/publish`, { method: 'PATCH' });
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    setData(await res.json());
  }

  async function upload() {
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/upload`, { method: 'POST', body: form });
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    setData(await res.json());
  }

  async function getDownloadUrl() {
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/download-url`);
    const payload = await res.json();
    if (payload?.url) setDownloadUrl(payload.url);
  }

  if (!data) return <p className="text-muted-foreground">Loading…</p>;

  return (
    <div className="space-y-4 max-w-xl">
      <h1 className="text-2xl font-bold">WikiContract</h1>
      <div className="space-y-4">
        <div className="space-y-2">
          <Label>Name</Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} />
        </div>
        <div className="space-y-2">
          <Label>Category</Label>
          <Input value={category} onChange={(e) => setCategory(e.target.value)} />
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
              <option key={r.id} value={r.id}>
                {r.name}
              </option>
            ))}
          </select>
        </div>
        <div className="space-y-2">
          <Label>Description</Label>
          <Input value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>
        <div className="flex flex-wrap gap-2">
          <Button onClick={save}>Save</Button>
          <Button variant="outline" onClick={publish}>
            Publish
          </Button>
        </div>
      </div>

      <div className="space-y-2">
        <Label>Upload template (PDF/DOCX)</Label>
        <input type="file" onChange={(e) => setFile(e.target.files?.[0] ?? null)} />
        <Button variant="outline" onClick={upload} disabled={!file}>
          Upload
        </Button>
      </div>

      <div className="space-y-2">
        <Button variant="outline" onClick={getDownloadUrl}>
          Get download URL
        </Button>
        {downloadUrl && (
          <a href={downloadUrl} className="text-primary text-sm hover:underline" target="_blank" rel="noreferrer">
            Open file
          </a>
        )}
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}
      <Button variant="outline" onClick={() => router.push('/wiki-contracts')}>
        Back
      </Button>
    </div>
  );
}
