'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { handleApiError } from '@/lib/api-error';

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
  const [file, setFile] = useState<File | null>(null);
  const [downloadUrl, setDownloadUrl] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions?limit=500')
      .then((r) => r.json())
      .then(setRegions)
      .catch(() => toast.error('Failed to load regions'));
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
      .catch(() => toast.error('Failed to load template'));
  }, [id]);

  async function save(e?: React.FormEvent) {
    if (e) e.preventDefault();
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
    if (await handleApiError(res)) return;
    setData(await res.json());
    toast.success('Template saved');
  }

  async function publish() {
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/publish`, { method: 'PATCH' });
    if (await handleApiError(res)) return;
    setData(await res.json());
    toast.success('Template published');
  }

  async function upload() {
    if (!file) return;
    const form = new FormData();
    form.append('file', file);
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/upload`, { method: 'POST', body: form });
    if (await handleApiError(res)) return;
    setData(await res.json());
    toast.success('File uploaded');
  }

  async function getDownloadUrl() {
    const res = await fetch(`/api/ccrs/wiki-contracts/${id}/download-url`);
    if (await handleApiError(res)) return;
    const payload = await res.json();
    if (payload?.url) setDownloadUrl(payload.url);
  }

  if (!data) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }

  return (
    <div className="space-y-4 max-w-xl">
      <h1 className="text-2xl font-bold">WikiContract</h1>
      <form onSubmit={save} className="space-y-4">
        <div className="space-y-2">
          <Label>
            Name <span className="text-destructive">*</span>
          </Label>
          <Input value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label>
            Category <span className="text-destructive">*</span>
          </Label>
          <Input value={category} onChange={(e) => setCategory(e.target.value)} required />
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
          <Label>Description</Label>
          <Input value={description} onChange={(e) => setDescription(e.target.value)} />
        </div>
        <div className="flex flex-wrap gap-2">
          <Button type="submit">Save</Button>
          <Button type="button" variant="outline" onClick={publish}>
            Publish
          </Button>
        </div>
      </form>

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

      <Button variant="outline" onClick={() => router.push('/wiki-contracts')}>
        Back
      </Button>
    </div>
  );
}
