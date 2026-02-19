'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { Entity } from '@/lib/types';
import { handleApiError } from '@/lib/api-error';

export function EditEntityForm({ id }: { id: string }) {
  const router = useRouter();
  const [entity, setEntity] = useState<Entity | null>(null);
  const [name, setName] = useState('');
  const [code, setCode] = useState('');
  const [regionName, setRegionName] = useState('');
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [deleting, setDeleting] = useState(false);

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
      .catch(() => toast.error('Failed to load entity'))
      .finally(() => setLoading(false));
  }, [id]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await fetch(`/api/ccrs/entities/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, code: code || undefined }),
      });
      if (await handleApiError(res)) return;
      toast.success('Entity updated');
      router.push('/entities');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Entity details</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </CardContent>
      </Card>
    );
  }
  if (!entity) return <p className="text-sm text-muted-foreground">Entity not found.</p>;

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
            <Label htmlFor="name">
              Name <span className="text-destructive">*</span>
            </Label>
            <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="code">Code (optional)</Label>
            <Input id="code" value={code} onChange={(e) => setCode(e.target.value)} />
          </div>
          <div className="flex justify-between gap-2">
            <ConfirmDialog
              trigger={
                <Button type="button" variant="destructive" disabled={deleting}>
                  {deleting ? 'Deleting…' : 'Delete'}
                </Button>
              }
              title="Delete entity"
              description="This will permanently delete this entity. This action cannot be undone."
              confirmLabel="Delete"
              variant="destructive"
              onConfirm={async () => {
                setDeleting(true);
                try {
                  const res = await fetch(`/api/ccrs/entities/${id}`, { method: 'DELETE' });
                  if (await handleApiError(res)) return;
                  toast.success('Entity deleted');
                  router.push('/entities');
                  router.refresh();
                } finally {
                  setDeleting(false);
                }
              }}
            />
            <div className="flex gap-2">
              <Button type="button" variant="outline" onClick={() => router.back()}>
                Cancel
              </Button>
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Saving…' : 'Save'}
              </Button>
            </div>
          </div>
        </form>
      </CardContent>
    </Card>
  );
}
