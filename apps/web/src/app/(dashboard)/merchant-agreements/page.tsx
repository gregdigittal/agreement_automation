"use client";

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetch('/api/ccrs/wiki-contracts?status=published')
      .then((r) => r.json())
      .then(setTemplates)
      .catch(() => undefined);
    fetch('/api/ccrs/regions?limit=500').then((r) => r.json()).then(setRegions).catch(() => undefined);
    fetch('/api/ccrs/entities?limit=500').then((r) => r.json()).then(setEntities).catch(() => undefined);
    fetch('/api/ccrs/projects?limit=500').then((r) => r.json()).then(setProjects).catch(() => undefined);
    fetch('/api/ccrs/counterparties?limit=500').then((r) => r.json()).then(setCounterparties).catch(() => undefined);
  }, []);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
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
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    const contract = await res.json();
    router.push(`/contracts/${contract.id}`);
  }

  return (
    <div className="space-y-4 max-w-2xl">
      <h1 className="text-2xl font-bold">Generate Merchant Agreement</h1>
      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-2">
          <Label>Template</Label>
          <select className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" value={templateId} onChange={(e) => setTemplateId(e.target.value)} required>
            <option value="">Select template</option>
            {templates.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </select>
        </div>
        <div className="space-y-2">
          <Label htmlFor="vendor">Vendor name</Label>
          <Input id="vendor" value={vendorName} onChange={(e) => setVendorName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label htmlFor="fee">Merchant fee</Label>
          <Input id="fee" value={merchantFee} onChange={(e) => setMerchantFee(e.target.value)} />
        </div>
        <div className="grid gap-3 md:grid-cols-2">
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
              {counterparties.map((c) => <option key={c.id} value={c.id}>{c.name ?? (c as { legal_name?: string }).legal_name}</option>)}
            </select>
          </div>
        </div>
        <div className="space-y-2">
          <Label>Region-specific terms</Label>
          <Input value={regionTerms} onChange={(e) => setRegionTerms(e.target.value)} placeholder="Optional notes" />
        </div>
        {error && <p className="text-sm text-destructive">{error}</p>}
        <Button type="submit">Generate</Button>
      </form>
    </div>
  );
}
