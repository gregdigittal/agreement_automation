'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { handleApiError } from '@/lib/api-error';

export function CreateCounterpartyForm() {
  const router = useRouter();
  const [legalName, setLegalName] = useState('');
  const [registrationNumber, setRegistrationNumber] = useState('');
  const [address, setAddress] = useState('');
  const [jurisdiction, setJurisdiction] = useState('');
  const [duplicates, setDuplicates] = useState<{ id: string; legal_name: string }[]>([]);
  const [submitting, setSubmitting] = useState(false);

  async function checkDuplicates() {
    if (!legalName.trim()) return;
    const res = await fetch(`/api/ccrs/counterparties/duplicates?legalName=${encodeURIComponent(legalName.trim())}&registrationNumber=${encodeURIComponent(registrationNumber.trim())}`);
    if (await handleApiError(res)) return;
    const data = await res.json();
    setDuplicates(Array.isArray(data) ? data : []);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await fetch('/api/ccrs/counterparties', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          legalName: legalName.trim(),
          registrationNumber: registrationNumber.trim() || undefined,
          address: address.trim() || undefined,
          jurisdiction: jurisdiction.trim() || undefined,
        }),
      });
      if (await handleApiError(res)) return;
      toast.success('Counterparty created');
      router.push('/counterparties');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="legalName">
          Legal name <span className="text-destructive">*</span>
        </Label>
        <Input id="legalName" value={legalName} onChange={(e) => setLegalName(e.target.value)} onBlur={checkDuplicates} required />
      </div>
      {duplicates.length > 0 && (
        <div className="rounded-md border border-amber-200 bg-amber-50 p-2 text-sm text-amber-800">
          <p className="font-medium">Possible duplicates</p>
          <div className="mt-2 space-y-2">
            {duplicates.map((d) => (
              <div key={d.id} className="flex flex-wrap items-center justify-between gap-2">
                <span>{d.legal_name}</span>
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => router.push(`/counterparties/${d.id}`)}
                >
                  View &amp; merge
                </Button>
              </div>
            ))}
          </div>
        </div>
      )}
      <div className="space-y-2">
        <Label htmlFor="registrationNumber">Registration number (optional)</Label>
        <Input id="registrationNumber" value={registrationNumber} onChange={(e) => setRegistrationNumber(e.target.value)} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="address">Address (optional)</Label>
        <Input id="address" value={address} onChange={(e) => setAddress(e.target.value)} />
      </div>
      <div className="space-y-2">
        <Label htmlFor="jurisdiction">Jurisdiction (optional)</Label>
        <Input id="jurisdiction" value={jurisdiction} onChange={(e) => setJurisdiction(e.target.value)} />
      </div>
      <div className="flex justify-end gap-2">
        <Button type="button" variant="outline" onClick={() => router.back()}>
          Cancel
        </Button>
        <Button type="submit" disabled={submitting}>
          {submitting ? 'Creating…' : 'Create'}
        </Button>
      </div>
    </form>
  );
}
