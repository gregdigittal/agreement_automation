"use client";

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Textarea } from '@/components/ui/textarea';
import { handleApiError } from '@/lib/api-error';

interface Option {
  id: string;
  name: string;
}

interface WikiContract {
  id: string;
  name: string;
  status: string;
}

export default function MerchantAgreementsPage() {
  const router = useRouter();
  const [templates, setTemplates] = useState<WikiContract[]>([]);
  const [regions, setRegions] = useState<Option[]>([]);
  const [entities, setEntities] = useState<Option[]>([]);
  const [projects, setProjects] = useState<Option[]>([]);
  const [counterparties, setCounterparties] = useState<Option[]>([]);

  const [templateId, setTemplateId] = useState('');
  const [vendorName, setVendorName] = useState('');
  const [merchantFee, setMerchantFee] = useState('');
  const [regionId, setRegionId] = useState('');
  const [entityId, setEntityId] = useState('');
  const [projectId, setProjectId] = useState('');
  const [counterpartyId, setCounterpartyId] = useState('');
  const [regionTerms, setRegionTerms] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchJson = async (url: string) => {
      const res = await fetch(url);
      if (!res.ok) throw new Error(`${res.status}`);
      return res.json();
    };
    setLoading(true);
    Promise.all([
      fetchJson('/api/ccrs/wiki-contracts?status=published'),
      fetchJson('/api/ccrs/regions?limit=500'),
      fetchJson('/api/ccrs/entities?limit=500'),
      fetchJson('/api/ccrs/projects?limit=500'),
      fetchJson('/api/ccrs/counterparties?limit=500'),
    ])
      .then(([templatesData, regionsData, entitiesData, projectsData, counterpartiesData]) => {
        setTemplates(templatesData);
        setRegions(regionsData);
        setEntities(entitiesData);
        setProjects(projectsData);
        setCounterparties(counterpartiesData);
      })
      .catch(() => toast.error('Failed to load options'))
      .finally(() => setLoading(false));
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (!templateId || !vendorName.trim() || !regionId || !entityId || !projectId || !counterpartyId) {
      toast.error('Fill all required fields before generating.');
      return;
    }
    const res = await fetch('/api/ccrs/merchant-agreements/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        templateId,
        vendorName,
        merchantFee: merchantFee || undefined,
        regionId,
        entityId,
        projectId,
        counterpartyId,
        regionTerms: regionTerms ? { notes: regionTerms } : undefined,
      }),
    });
    if (await handleApiError(res)) return;
    const contract = await res.json();
    toast.success('Merchant agreement generated');
    router.push(`/contracts/${contract.id}`);
  }

  return (
    <div className="space-y-4 max-w-2xl">
      <h1 className="text-2xl font-bold">Generate Merchant Agreement</h1>
      {loading && (
        <div className="space-y-2">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      )}
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-2">
          <Label>
            Template <span className="text-destructive">*</span>
          </Label>
          <Select value={templateId} onValueChange={setTemplateId}>
            <SelectTrigger>
              <SelectValue placeholder="Select template" />
            </SelectTrigger>
            <SelectContent>
              {templates.map((t) => (
                <SelectItem key={t.id} value={t.id}>
                  {t.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor">
            Vendor name <span className="text-destructive">*</span>
          </Label>
          <Input id="vendor" value={vendorName} onChange={(e) => setVendorName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label htmlFor="fee">Merchant fee</Label>
          <Input id="fee" value={merchantFee} onChange={(e) => setMerchantFee(e.target.value)} />
        </div>
        <div className="grid gap-3 md:grid-cols-2">
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
                {entities.map((e) => (
                  <SelectItem key={e.id} value={e.id}>
                    {e.name}
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
                {projects.map((p) => (
                  <SelectItem key={p.id} value={p.id}>
                    {p.name}
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
                {counterparties.map((c) => (
                  <SelectItem key={c.id} value={c.id}>
                    {c.name ?? (c as { legal_name?: string }).legal_name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="space-y-2">
          <Label>Region-specific terms</Label>
          <Textarea value={regionTerms} onChange={(e) => setRegionTerms(e.target.value)} placeholder="Optional notes" />
        </div>
        <div className="flex justify-end gap-2">
          <Button type="button" variant="outline" onClick={() => router.back()}>
            Cancel
          </Button>
          <Button type="submit">Generate</Button>
        </div>
      </form>
    </div>
  );
}
