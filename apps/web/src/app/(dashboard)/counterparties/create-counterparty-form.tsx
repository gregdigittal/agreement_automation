'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function CreateCounterpartyForm() {
  const router = useRouter();
  const [legalName, setLegalName] = useState('');
  const [registrationNumber, setRegistrationNumber] = useState('');
  const [address, setAddress] = useState('');
  const [jurisdiction, setJurisdiction] = useState('');
  const [duplicates, setDuplicates] = useState<{ id: string; legal_name: string }[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function checkDuplicates() {
    if (!legalName.trim()) return;
    const res = await fetch(`/api/ccrs/counterparties/duplicates?legalName=${encodeURIComponent(legalName.trim())}&registrationNumber=${encodeURIComponent(registrationNumber.trim())}`);
    const data = await res.json();
    setDuplicates(Array.isArray(data) ? data : []);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
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
      if (!res.ok) { setError(await res.text()); return; }
      router.push('/counterparties');
      router.refresh();
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-2">
        <Label htmlFor="legalName">Legal name</Label>
        <Input id="legalName" value={legalName} onChange={(e) => setLegalName(e.target.value)} onBlur={checkDuplicates} required />
      </div>
      {duplicates.length > 0 && (
        <div className="rounded-md border border-amber-200 bg-amber-50 p-2 text-sm text-amber-800">
          Possible duplicates: {duplicates.map((d) => d.legal_name).join(', ')}. Review before creating.
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
      {error && <p className="text-sm text-destructive">{error}</p>}
      <Button type="submit" disabled={submitting}>{submitting ? 'Creating…' : 'Create'}</Button>
    </form>
  );
}
