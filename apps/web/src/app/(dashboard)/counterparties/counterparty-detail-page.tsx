'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Counterparty, CounterpartyContact } from '@/lib/types';

const STATUS_OPTIONS: Counterparty['status'][] = ['Active', 'Suspended', 'Blacklisted'];

export function CounterpartyDetailPage({ id }: { id: string }) {
  const router = useRouter();
  const [data, setData] = useState<Counterparty | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editMode, setEditMode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [statusSubmitting, setStatusSubmitting] = useState(false);

  const [legalName, setLegalName] = useState('');
  const [registrationNumber, setRegistrationNumber] = useState('');
  const [address, setAddress] = useState('');
  const [jurisdiction, setJurisdiction] = useState('');
  const [preferredLanguage, setPreferredLanguage] = useState('en');

  const [newStatus, setNewStatus] = useState<Counterparty['status']>('Active');
  const [statusReason, setStatusReason] = useState('');
  const [supportingDocumentRef, setSupportingDocumentRef] = useState('');

  const [contacts, setContacts] = useState<CounterpartyContact[]>([]);
  const [contactName, setContactName] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [contactRole, setContactRole] = useState('');
  const [contactSigner, setContactSigner] = useState(false);

  useEffect(() => {
    fetch(`/api/ccrs/counterparties/${id}`)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`${r.status}`))))
      .then((payload: Counterparty) => {
        setData(payload);
        setLegalName(payload.legal_name ?? '');
        setRegistrationNumber(payload.registration_number ?? '');
        setAddress(payload.address ?? '');
        setJurisdiction(payload.jurisdiction ?? '');
        setPreferredLanguage(payload.preferred_language ?? 'en');
        setNewStatus(payload.status);
        setSupportingDocumentRef(payload.supporting_document_ref ?? '');
        setContacts(payload.counterparty_contacts ?? []);
      })
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [id]);

  async function submitEdit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);
    try {
      const res = await fetch(`/api/ccrs/counterparties/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          legal_name: legalName,
          registration_number: registrationNumber || undefined,
          address: address || undefined,
          jurisdiction: jurisdiction || undefined,
          preferred_language: preferredLanguage || undefined,
        }),
      });
      if (!res.ok) {
        setError(await res.text());
        return;
      }
      const refreshed = (await res.json()) as Counterparty;
      setData(refreshed);
      setEditMode(false);
    } finally {
      setSubmitting(false);
    }
  }

  async function submitStatusChange() {
    if (!statusReason.trim()) {
      setError('Status change requires a reason.');
      return;
    }
    const ok = window.confirm('Change counterparty status?');
    if (!ok) return;
    setStatusSubmitting(true);
    setError(null);
    try {
      const res = await fetch(`/api/ccrs/counterparties/${id}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          status: newStatus,
          reason: statusReason,
          supporting_document_ref: supportingDocumentRef || undefined,
        }),
      });
      if (!res.ok) {
        setError(await res.text());
        return;
      }
      const refreshed = (await res.json()) as Counterparty;
      setData(refreshed);
      setStatusReason('');
    } finally {
      setStatusSubmitting(false);
    }
  }

  async function addContact(e: React.FormEvent) {
    e.preventDefault();
    if (!contactName.trim()) {
      setError('Contact name is required.');
      return;
    }
    setError(null);
    const res = await fetch(`/api/ccrs/counterparties/${id}/contacts`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: contactName,
        email: contactEmail || undefined,
        role: contactRole || undefined,
        is_signer: contactSigner,
      }),
    });
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    const created = (await res.json()) as CounterpartyContact;
    setContacts((prev) => [...prev, created]);
    setContactName('');
    setContactEmail('');
    setContactRole('');
    setContactSigner(false);
  }

  async function removeContact(contactId: string) {
    const ok = window.confirm('Delete this contact?');
    if (!ok) return;
    setError(null);
    const res = await fetch(`/api/ccrs/counterparty-contacts/${contactId}`, { method: 'DELETE' });
    if (!res.ok) {
      setError(await res.text());
      return;
    }
    setContacts((prev) => prev.filter((c) => c.id !== contactId));
  }

  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (!data) {
    router.push('/counterparties');
    return null;
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <h1 className="text-2xl font-bold">{data.legal_name}</h1>
          <Badge variant={data.status === 'Active' ? 'default' : 'secondary'}>{data.status}</Badge>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={() => setEditMode((v) => !v)}>
            {editMode ? 'Cancel' : 'Edit'}
          </Button>
          <Button variant="outline" size="sm" asChild>
            <Link href="/counterparties">Back to list</Link>
          </Button>
        </div>
      </div>

      {editMode ? (
        <Card>
          <CardHeader>
            <CardTitle>Edit counterparty</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submitEdit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="legal_name">Legal name</Label>
                <Input id="legal_name" value={legalName} onChange={(e) => setLegalName(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="registration_number">Registration number</Label>
                <Input id="registration_number" value={registrationNumber} onChange={(e) => setRegistrationNumber(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="address">Address</Label>
                <Input id="address" value={address} onChange={(e) => setAddress(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="jurisdiction">Jurisdiction</Label>
                <Input id="jurisdiction" value={jurisdiction} onChange={(e) => setJurisdiction(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="preferred_language">Preferred language</Label>
                <Input id="preferred_language" value={preferredLanguage} onChange={(e) => setPreferredLanguage(e.target.value)} />
              </div>
              {error && <p className="text-sm text-destructive">{error}</p>}
              <Button type="submit" disabled={submitting}>
                {submitting ? 'Saving…' : 'Save'}
              </Button>
            </form>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {data.registration_number && <p><span className="font-medium">Registration:</span> {data.registration_number}</p>}
            {data.address && <p><span className="font-medium">Address:</span> {data.address}</p>}
            {data.jurisdiction && <p><span className="font-medium">Jurisdiction:</span> {data.jurisdiction}</p>}
            <p><span className="font-medium">Preferred language:</span> {data.preferred_language ?? 'en'}</p>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Status management</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-wrap items-center gap-3">
            <Badge variant={data.status === 'Active' ? 'default' : 'secondary'}>{data.status}</Badge>
            {data.status_reason && <span className="text-sm text-muted-foreground">Reason: {data.status_reason}</span>}
          </div>
          <div className="space-y-2">
            <Label>New status</Label>
            <select
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={newStatus}
              onChange={(e) => setNewStatus(e.target.value as Counterparty['status'])}
            >
              {STATUS_OPTIONS.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </select>
          </div>
          <div className="space-y-2">
            <Label htmlFor="reason">Reason</Label>
            <textarea
              id="reason"
              className="min-h-[100px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
              value={statusReason}
              onChange={(e) => setStatusReason(e.target.value)}
              required
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="supporting_ref">Supporting document reference (optional)</Label>
            <Input
              id="supporting_ref"
              value={supportingDocumentRef}
              onChange={(e) => setSupportingDocumentRef(e.target.value)}
            />
          </div>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <Button onClick={submitStatusChange} disabled={statusSubmitting}>
            {statusSubmitting ? 'Updating…' : 'Change status'}
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Contacts</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {contacts.length === 0 ? (
            <p className="text-sm text-muted-foreground">No contacts yet.</p>
          ) : (
            <div className="space-y-3">
              {contacts.map((c) => (
                <div key={c.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-3">
                  <div>
                    <p className="font-medium">{c.name}</p>
                    <p className="text-sm text-muted-foreground">
                      {c.email ?? 'No email'} · {c.role ?? 'No role'} · {c.is_signer ? 'Signer' : 'Contact'}
                    </p>
                  </div>
                  <Button variant="outline" size="sm" onClick={() => void removeContact(c.id)}>
                    Delete
                  </Button>
                </div>
              ))}
            </div>
          )}

          <form onSubmit={addContact} className="space-y-3 border-t border-border pt-4">
            <h3 className="text-sm font-medium">Add contact</h3>
            <div className="grid gap-3 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="contact_name">Name</Label>
                <Input id="contact_name" value={contactName} onChange={(e) => setContactName(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="contact_email">Email</Label>
                <Input id="contact_email" value={contactEmail} onChange={(e) => setContactEmail(e.target.value)} />
              </div>
              <div className="space-y-2">
                <Label htmlFor="contact_role">Role</Label>
                <Input id="contact_role" value={contactRole} onChange={(e) => setContactRole(e.target.value)} />
              </div>
              <div className="flex items-center gap-2 pt-6">
                <input
                  id="contact_signer"
                  type="checkbox"
                  className="h-4 w-4"
                  checked={contactSigner}
                  onChange={(e) => setContactSigner(e.target.checked)}
                />
                <Label htmlFor="contact_signer">Signer</Label>
              </div>
            </div>
            <Button type="submit">Add contact</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}
