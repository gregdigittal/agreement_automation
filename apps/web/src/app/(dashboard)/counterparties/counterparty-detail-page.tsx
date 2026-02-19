'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { ConfirmDialog } from '@/components/confirm-dialog';
import type { Counterparty, CounterpartyContact } from '@/lib/types';
import { handleApiError } from '@/lib/api-error';

const STATUS_OPTIONS: Counterparty['status'][] = ['Active', 'Suspended', 'Blacklisted'];

export function CounterpartyDetailPage({ id }: { id: string }) {
  const router = useRouter();
  const [data, setData] = useState<Counterparty | null>(null);
  const [loading, setLoading] = useState(true);
  const [editMode, setEditMode] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [statusSubmitting, setStatusSubmitting] = useState(false);
  const [statusConfirmOpen, setStatusConfirmOpen] = useState(false);

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

  const [overrideOpen, setOverrideOpen] = useState(false);
  const [overrideTitle, setOverrideTitle] = useState('');
  const [overrideReason, setOverrideReason] = useState('');
  const [overrideSubmitting, setOverrideSubmitting] = useState(false);

  const [duplicates, setDuplicates] = useState<{ id: string; legal_name: string }[]>([]);

  useEffect(() => {
    let active = true;
    const load = async () => {
      const res = await fetch(`/api/ccrs/counterparties/${id}`);
      if (await handleApiError(res)) return;
      const payload = (await res.json()) as Counterparty;
      if (!active) return;
      setData(payload);
      setLegalName(payload.legal_name ?? '');
      setRegistrationNumber(payload.registration_number ?? '');
      setAddress(payload.address ?? '');
      setJurisdiction(payload.jurisdiction ?? '');
      setPreferredLanguage(payload.preferred_language ?? 'en');
      setNewStatus(payload.status);
      setSupportingDocumentRef(payload.supporting_document_ref ?? '');
      setContacts(payload.counterparty_contacts ?? []);
    };
    load().catch(() => toast.error('Failed to load counterparty')).finally(() => {
      if (active) setLoading(false);
    });
    return () => {
      active = false;
    };
  }, [id]);

  useEffect(() => {
    if (!data?.legal_name) return;
    const params = new URLSearchParams({
      legalName: data.legal_name,
    });
    if (data.registration_number) params.set('registrationNumber', data.registration_number);
    const load = async () => {
      const res = await fetch(`/api/ccrs/counterparties/duplicates?${params.toString()}`);
      if (await handleApiError(res)) return;
      const rows = await res.json();
      const cleaned = Array.isArray(rows)
        ? rows.filter((row) => row.id && row.id !== data.id)
        : [];
      setDuplicates(cleaned);
    };
    load().catch(() => undefined);
  }, [data?.id, data?.legal_name, data?.registration_number]);

  async function submitEdit(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const res = await fetch(`/api/ccrs/counterparties/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          legalName,
          registrationNumber: registrationNumber || undefined,
          address: address || undefined,
          jurisdiction: jurisdiction || undefined,
          preferredLanguage: preferredLanguage || undefined,
        }),
      });
      if (await handleApiError(res)) return;
      const refreshed = (await res.json()) as Counterparty;
      setData(refreshed);
      setEditMode(false);
      toast.success('Counterparty updated');
    } finally {
      setSubmitting(false);
    }
  }

  function handleStatusSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!statusReason.trim()) {
      toast.error('Status change requires a reason.');
      return;
    }
    setStatusConfirmOpen(true);
  }

  async function confirmStatusChange() {
    setStatusSubmitting(true);
    try {
      const res = await fetch(`/api/ccrs/counterparties/${id}/status`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          status: newStatus,
          reason: statusReason,
          supportingDocumentRef: supportingDocumentRef || undefined,
        }),
      });
      if (await handleApiError(res)) return;
      const refreshed = (await res.json()) as Counterparty;
      setData(refreshed);
      setStatusReason('');
      toast.success(`Status changed to ${newStatus}`);
    } finally {
      setStatusSubmitting(false);
      setStatusConfirmOpen(false);
    }
  }

  async function addContact(e: React.FormEvent) {
    e.preventDefault();
    if (!contactName.trim()) {
      toast.error('Contact name is required.');
      return;
    }
    const res = await fetch(`/api/ccrs/counterparties/${id}/contacts`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        name: contactName,
        email: contactEmail || undefined,
        role: contactRole || undefined,
        isSigner: contactSigner,
      }),
    });
    if (await handleApiError(res)) return;
    const created = (await res.json()) as CounterpartyContact;
    setContacts((prev) => [...prev, created]);
    setContactName('');
    setContactEmail('');
    setContactRole('');
    setContactSigner(false);
    toast.success('Contact added');
  }

  async function removeContact(contactId: string) {
    const res = await fetch(`/api/ccrs/counterparty-contacts/${contactId}`, { method: 'DELETE' });
    if (await handleApiError(res)) return;
    setContacts((prev) => prev.filter((c) => c.id !== contactId));
    toast.success('Contact removed');
  }

  async function submitOverrideRequest() {
    if (!overrideTitle.trim() || !overrideReason.trim()) {
      toast.error('Contract title and reason are required.');
      return;
    }
    setOverrideSubmitting(true);
    try {
      const res = await fetch(`/api/ccrs/counterparties/${id}/override-requests`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          contractTitle: overrideTitle.trim(),
          reason: overrideReason.trim(),
        }),
      });
      if (await handleApiError(res)) return;
      setOverrideOpen(false);
      setOverrideTitle('');
      setOverrideReason('');
      toast.success('Override request submitted');
    } finally {
      setOverrideSubmitting(false);
    }
  }

  async function mergeDuplicate(sourceId: string) {
    const res = await fetch(`/api/ccrs/counterparties/${id}/merge`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ sourceId }),
    });
    if (await handleApiError(res)) return;
    setDuplicates((prev) => prev.filter((d) => d.id !== sourceId));
  }

  if (loading) {
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
                <Label htmlFor="legal_name">
                  Legal name <span className="text-destructive">*</span>
                </Label>
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
          <form onSubmit={handleStatusSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label>
                New status <span className="text-destructive">*</span>
              </Label>
              <Select value={newStatus} onValueChange={(value) => setNewStatus(value as Counterparty['status'])}>
                <SelectTrigger>
                  <SelectValue placeholder="Select status" />
                </SelectTrigger>
                <SelectContent>
                  {STATUS_OPTIONS.map((s) => (
                    <SelectItem key={s} value={s}>
                      {s}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="reason">
                Reason <span className="text-destructive">*</span>
              </Label>
              <Textarea
                id="reason"
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
            <Button type="submit" disabled={statusSubmitting}>
              {statusSubmitting ? 'Updating…' : 'Change status'}
            </Button>
          </form>
          <AlertDialog open={statusConfirmOpen} onOpenChange={setStatusConfirmOpen}>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Change counterparty status</AlertDialogTitle>
                <AlertDialogDescription>
                  This will update the counterparty status and notify relevant users.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction onClick={confirmStatusChange}>
                  Confirm change
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </CardContent>
      </Card>

      {data.status !== 'Active' && (
        <Card>
          <CardHeader>
            <CardTitle>Override request</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <p className="text-sm text-muted-foreground">
              This counterparty is {data.status}. New contracts cannot be created without an override.
            </p>
            <Dialog open={overrideOpen} onOpenChange={setOverrideOpen}>
              <DialogTrigger asChild>
                <Button variant="outline">Request Override</Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Request override</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                  <div className="space-y-2">
                    <Label htmlFor="override-title">Contract title</Label>
                    <Input
                      id="override-title"
                      value={overrideTitle}
                      onChange={(e) => setOverrideTitle(e.target.value)}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="override-reason">Reason</Label>
                    <Textarea
                      id="override-reason"
                      value={overrideReason}
                      onChange={(e) => setOverrideReason(e.target.value)}
                    />
                  </div>
                </div>
                <DialogFooter>
                  <Button onClick={submitOverrideRequest} disabled={overrideSubmitting}>
                    {overrideSubmitting ? 'Submitting…' : 'Submit request'}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>
          </CardContent>
        </Card>
      )}

      {duplicates.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Potential duplicates</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {duplicates.map((dup) => (
              <div key={dup.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-3">
                <div>
                  <p className="font-medium">{dup.legal_name}</p>
                  <p className="text-sm text-muted-foreground">{dup.id}</p>
                </div>
                <ConfirmDialog
                  trigger={
                    <Button variant="outline" size="sm">
                      Merge into this counterparty
                    </Button>
                  }
                  title="Merge counterparty"
                  description="This will merge the selected counterparty into the current record and remove the duplicate."
                  confirmLabel="Merge"
                  variant="destructive"
                  onConfirm={() => mergeDuplicate(dup.id)}
                />
              </div>
            ))}
          </CardContent>
        </Card>
      )}

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
                  <ConfirmDialog
                    trigger={
                      <Button variant="outline" size="sm">
                        Remove
                      </Button>
                    }
                    title="Remove contact"
                    description="This will permanently remove this contact from the counterparty."
                    confirmLabel="Remove"
                    variant="destructive"
                    onConfirm={() => removeContact(c.id)}
                  />
                </div>
              ))}
            </div>
          )}

          <form onSubmit={addContact} className="space-y-3 border-t border-border pt-4">
            <h3 className="text-sm font-medium">Add contact</h3>
            <div className="grid gap-3 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="contact_name">
                  Name <span className="text-destructive">*</span>
                </Label>
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
                <Checkbox id="contact_signer" checked={contactSigner} onCheckedChange={(value) => setContactSigner(Boolean(value))} />
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
