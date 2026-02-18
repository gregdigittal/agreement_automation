'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Contract } from '@/lib/types';

interface ContractDetailProps {
  contract: Contract;
}

const ANALYSIS_TYPES = [
  { label: 'Summary', value: 'summary' },
  { label: 'Full Extraction', value: 'extraction' },
  { label: 'Risk Assessment', value: 'risk' },
  { label: 'Template Deviation', value: 'deviation' },
  { label: 'Obligations', value: 'obligations' },
];

const LANGUAGE_OPTIONS = ['en', 'fr', 'es', 'de', 'it', 'pt', 'zh', 'ja', 'ko', 'ar'];

export function ContractDetail({ contract }: ContractDetailProps) {
  const [downloadUrl, setDownloadUrl] = useState<string | null>(null);
  const [templates, setTemplates] = useState<WorkflowTemplate[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState('');
  const [workflow, setWorkflow] = useState<WorkflowInstance | null>(null);
  const [history, setHistory] = useState<WorkflowAction[]>([]);
  const [workflowError, setWorkflowError] = useState<string | null>(null);
  const [linked, setLinked] = useState<LinkedContracts | null>(null);

  const [keyDates, setKeyDates] = useState<KeyDate[]>([]);
  const [keyDateType, setKeyDateType] = useState('');
  const [keyDateValue, setKeyDateValue] = useState('');
  const [keyDateDescription, setKeyDateDescription] = useState('');
  const [keyDateReminders, setKeyDateReminders] = useState('');

  const [reminders, setReminders] = useState<Reminder[]>([]);
  const [reminderKeyDateId, setReminderKeyDateId] = useState('');
  const [reminderType, setReminderType] = useState('expiry');
  const [reminderLeadDays, setReminderLeadDays] = useState('30');
  const [reminderChannel, setReminderChannel] = useState('email');
  const [reminderRecipient, setReminderRecipient] = useState('');
  const [reminderError, setReminderError] = useState<string | null>(null);

  const [analysis, setAnalysis] = useState<AnalysisResponse | null>(null);
  const [analysisLoading, setAnalysisLoading] = useState(false);
  const [analysisError, setAnalysisError] = useState<string | null>(null);
  const [editingFieldId, setEditingFieldId] = useState<string | null>(null);
  const [editingFieldValue, setEditingFieldValue] = useState('');

  const [languages, setLanguages] = useState<ContractLanguage[]>([]);
  const [languageCode, setLanguageCode] = useState('en');
  const [languagePrimary, setLanguagePrimary] = useState(false);
  const [languageFile, setLanguageFile] = useState<File | null>(null);
  const [languageError, setLanguageError] = useState<string | null>(null);

  async function getDownloadUrl() {
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/download-url`);
    const data = await res.json();
    if (data?.url) setDownloadUrl(data.url);
  }

  async function loadAnalysis() {
    setAnalysisLoading(true);
    setAnalysisError(null);
    try {
      const res = await fetch(`/api/ccrs/contracts/${contract.id}/analysis`);
      if (!res.ok) throw new Error(await res.text());
      setAnalysis(await res.json());
    } catch (e) {
      setAnalysisError((e as Error).message);
    } finally {
      setAnalysisLoading(false);
    }
  }

  useEffect(() => {
    fetch('/api/ccrs/workflow-templates?status=published')
      .then((r) => (r.ok ? r.json() : []))
      .then(setTemplates)
      .catch(() => undefined);

    fetch(`/api/ccrs/contracts/${contract.id}/workflow`)
      .then((r) => (r.ok ? r.json() : null))
      .then((data) => setWorkflow(data))
      .catch(() => undefined);

    fetch(`/api/ccrs/contracts/${contract.id}/linked`)
      .then((r) => (r.ok ? r.json() : null))
      .then(setLinked)
      .catch(() => undefined);

    fetch(`/api/ccrs/contracts/${contract.id}/key-dates`)
      .then((r) => (r.ok ? r.json() : []))
      .then(setKeyDates)
      .catch(() => undefined);

    fetch(`/api/ccrs/contracts/${contract.id}/reminders`)
      .then((r) => (r.ok ? r.json() : []))
      .then(setReminders)
      .catch(() => undefined);

    fetch(`/api/ccrs/contracts/${contract.id}/languages`)
      .then((r) => (r.ok ? r.json() : []))
      .then(setLanguages)
      .catch(() => undefined);

    void loadAnalysis();
  }, [contract.id]);

  useEffect(() => {
    if (!workflow?.id) return;
    fetch(`/api/ccrs/workflow-instances/${workflow.id}/history`)
      .then((r) => (r.ok ? r.json() : []))
      .then(setHistory)
      .catch(() => undefined);
  }, [workflow?.id]);

  async function startWorkflow() {
    if (!selectedTemplate) {
      setWorkflowError('Select a template');
      return;
    }
    setWorkflowError(null);
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/workflow`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ templateId: selectedTemplate }),
    });
    if (!res.ok) {
      setWorkflowError(await res.text());
      return;
    }
    const data = await res.json();
    setWorkflow(data);
  }

  async function stageAction(action: 'approve' | 'reject' | 'rework') {
    if (!workflow) return;
    setWorkflowError(null);
    const res = await fetch(`/api/ccrs/workflow-instances/${workflow.id}/stages/${workflow.current_stage}/action`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action }),
    });
    if (!res.ok) {
      setWorkflowError(await res.text());
      return;
    }
    const refreshed = await fetch(`/api/ccrs/contracts/${contract.id}/workflow`).then((r) => (r.ok ? r.json() : null));
    setWorkflow(refreshed);
    const hist = await fetch(`/api/ccrs/workflow-instances/${workflow.id}/history`).then((r) => (r.ok ? r.json() : []));
    setHistory(hist);
  }

  async function sendToSign() {
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/send-to-sign`, { method: 'POST' });
    if (!res.ok) {
      setWorkflowError(await res.text());
    }
  }

  async function createLinked(type: 'amendments' | 'side-letters') {
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/${type}`, { method: 'POST' });
    if (res.ok) {
      const refreshed = await fetch(`/api/ccrs/contracts/${contract.id}/linked`).then((r) => (r.ok ? r.json() : null));
      setLinked(refreshed);
    }
  }

  async function createRenewal(kind: 'extension' | 'new_version') {
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/renewals`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type: kind }),
    });
    if (res.ok) {
      const refreshed = await fetch(`/api/ccrs/contracts/${contract.id}/linked`).then((r) => (r.ok ? r.json() : null));
      setLinked(refreshed);
    }
  }

  async function addKeyDate() {
    if (!keyDateType || !keyDateValue) return;
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/key-dates`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        dateType: keyDateType,
        dateValue: keyDateValue,
        description: keyDateDescription || undefined,
        reminderDays: keyDateReminders
          ? keyDateReminders.split(',').map((v) => Number(v.trim())).filter((v) => !Number.isNaN(v))
          : undefined,
      }),
    });
    if (res.ok) {
      const updated = await fetch(`/api/ccrs/contracts/${contract.id}/key-dates`).then((r) => (r.ok ? r.json() : []));
      setKeyDates(updated);
      setKeyDateType('');
      setKeyDateValue('');
      setKeyDateDescription('');
      setKeyDateReminders('');
    }
  }

  async function verifyKeyDate(id: string) {
    const res = await fetch(`/api/ccrs/key-dates/${id}/verify`, { method: 'PATCH' });
    if (res.ok) {
      const updated = await fetch(`/api/ccrs/contracts/${contract.id}/key-dates`).then((r) => (r.ok ? r.json() : []));
      setKeyDates(updated);
    }
  }

  async function deleteKeyDate(id: string) {
    const res = await fetch(`/api/ccrs/key-dates/${id}`, { method: 'DELETE' });
    if (res.ok) {
      setKeyDates((prev) => prev.filter((k) => k.id !== id));
    }
  }

  async function addReminder() {
    if (!reminderLeadDays) return;
    setReminderError(null);
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/reminders`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        keyDateId: reminderKeyDateId || undefined,
        reminderType,
        leadDays: Number(reminderLeadDays),
        channel: reminderChannel,
        recipientEmail: reminderRecipient || undefined,
      }),
    });
    if (!res.ok) {
      setReminderError(await res.text());
      return;
    }
    const updated = await fetch(`/api/ccrs/contracts/${contract.id}/reminders`).then((r) => (r.ok ? r.json() : []));
    setReminders(updated);
    setReminderKeyDateId('');
    setReminderLeadDays('30');
    setReminderRecipient('');
  }

  async function toggleReminder(reminder: Reminder) {
    const res = await fetch(`/api/ccrs/reminders/${reminder.id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isActive: !reminder.is_active }),
    });
    if (res.ok) {
      const updated = await fetch(`/api/ccrs/contracts/${contract.id}/reminders`).then((r) => (r.ok ? r.json() : []));
      setReminders(updated);
    }
  }

  async function deleteReminder(id: string) {
    const res = await fetch(`/api/ccrs/reminders/${id}`, { method: 'DELETE' });
    if (res.ok) {
      setReminders((prev) => prev.filter((r) => r.id !== id));
    }
  }

  async function triggerAnalysis(type: string) {
    setAnalysisError(null);
    setAnalysisLoading(true);
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/analyze`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ analysisType: type }),
    });
    if (!res.ok) {
      setAnalysisError(await res.text());
      setAnalysisLoading(false);
      return;
    }
    await loadAnalysis();
  }

  async function verifyField(fieldId: string) {
    await fetch(`/api/ccrs/ai-fields/${fieldId}/verify`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ isVerified: true }),
    });
    await loadAnalysis();
  }

  async function correctField(fieldId: string, value: string) {
    await fetch(`/api/ccrs/ai-fields/${fieldId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ fieldValue: value }),
    });
    setEditingFieldId(null);
    setEditingFieldValue('');
    await loadAnalysis();
  }

  async function uploadLanguage() {
    if (!languageFile) {
      setLanguageError('Select a file');
      return;
    }
    setLanguageError(null);
    const form = new FormData();
    form.append('language_code', languageCode);
    form.append('is_primary', String(languagePrimary));
    form.append('file', languageFile);
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/languages`, {
      method: 'POST',
      body: form,
    });
    if (!res.ok) {
      setLanguageError(await res.text());
      return;
    }
    const updated = await fetch(`/api/ccrs/contracts/${contract.id}/languages`).then((r) => (r.ok ? r.json() : []));
    setLanguages(updated);
    setLanguageFile(null);
  }

  async function deleteLanguage(id: string) {
    const res = await fetch(`/api/ccrs/contract-languages/${id}`, { method: 'DELETE' });
    if (res.ok) {
      setLanguages((prev) => prev.filter((l) => l.id !== id));
    }
  }

  async function downloadLanguage(id: string) {
    const res = await fetch(`/api/ccrs/contract-languages/${id}/download-url`);
    if (!res.ok) return;
    const data = await res.json();
    if (data?.url) window.open(data.url, '_blank', 'noopener,noreferrer');
  }

  const analyses = analysis?.analyses ?? [];
  const extractedFields = analysis?.extracted_fields ?? [];
  const summaryResult = analyses.find((a) => a.analysis_type === 'summary' && a.status === 'completed')?.result;
  const riskResult = analyses.find((a) => a.analysis_type === 'risk' && a.status === 'completed')?.result;
  const deviationResult = analyses.find((a) => a.analysis_type === 'deviation' && a.status === 'completed')?.result;
  const obligationsResult = analyses.find((a) => a.analysis_type === 'obligations' && a.status === 'completed')?.result;

  const riskScore = typeof riskResult?.overall_risk_score === 'number' ? riskResult.overall_risk_score : 0;
  const deviationList = deviationResult?.deviations ?? [];
  const obligationsList = obligationsResult?.obligations ?? [];
  const riskList = riskResult?.risks ?? [];
  const riskColor = riskScore >= 0.75 ? 'bg-red-500' : riskScore >= 0.5 ? 'bg-orange-500' : 'bg-emerald-500';

  return (
    <Tabs defaultValue="overview" className="space-y-4">
      <TabsList>
        <TabsTrigger value="overview">Overview</TabsTrigger>
        <TabsTrigger value="workflow">Workflow</TabsTrigger>
        <TabsTrigger value="linked">Linked Docs</TabsTrigger>
        <TabsTrigger value="key-dates">Key Dates</TabsTrigger>
        <TabsTrigger value="ai">AI Analysis</TabsTrigger>
        <TabsTrigger value="languages">Languages</TabsTrigger>
      </TabsList>

      <TabsContent value="overview">
        <Card>
          <CardHeader>
            <CardTitle>Details</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            <p><span className="font-medium">Type:</span> {contract.contract_type}</p>
            <p><span className="font-medium">State:</span> {contract.workflow_state}</p>
            {contract.signing_status && <p><span className="font-medium">Signing:</span> {contract.signing_status}</p>}
            {contract.regions && <p><span className="font-medium">Region:</span> {contract.regions.name}</p>}
            {contract.entities && <p><span className="font-medium">Entity:</span> {contract.entities.name}</p>}
            {contract.projects && <p><span className="font-medium">Project:</span> {contract.projects.name}</p>}
            {contract.counterparties && <p><span className="font-medium">Counterparty:</span> {contract.counterparties.legal_name}</p>}
            {contract.file_name && <p><span className="font-medium">File:</span> {contract.file_name}</p>}
            <p className="text-sm text-muted-foreground">Created: {new Date(contract.created_at).toLocaleString()}</p>
            <Button variant="outline" size="sm" onClick={getDownloadUrl} className="mt-2">
              Get download link
            </Button>
            {downloadUrl && (
              <p className="text-sm mt-2">
                <a href={downloadUrl} target="_blank" rel="noopener noreferrer" className="text-primary hover:underline">
                  Open file (link expires in 1 hour)
                </a>
              </p>
            )}
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="workflow" className="space-y-4">
        <Card>
          <CardHeader>
            <CardTitle>Workflow</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {!workflow ? (
              <div className="space-y-2">
                <p className="text-sm text-muted-foreground">No active workflow.</p>
                <div className="flex flex-wrap items-center gap-2">
                  <select
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={selectedTemplate}
                    onChange={(e) => setSelectedTemplate(e.target.value)}
                  >
                    <option value="">Select template</option>
                    {templates.map((t) => (
                      <option key={t.id} value={t.id}>{t.name}</option>
                    ))}
                  </select>
                  <Button onClick={startWorkflow}>Start workflow</Button>
                </div>
              </div>
            ) : (
              <div className="space-y-3">
                <p className="text-sm">Current stage: <span className="font-medium">{workflow.current_stage}</span></p>
                <div className="flex flex-wrap items-center gap-2">
                  <Button variant="outline" size="sm" onClick={() => stageAction('approve')}>Approve</Button>
                  <Button variant="outline" size="sm" onClick={() => stageAction('reject')}>Reject</Button>
                  <Button variant="outline" size="sm" onClick={() => stageAction('rework')}>Rework</Button>
                  <Button variant="outline" size="sm" onClick={sendToSign}>Send to Sign</Button>
                </div>
              </div>
            )}
            {workflowError && <p className="text-sm text-destructive">{workflowError}</p>}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Workflow history</CardTitle>
          </CardHeader>
          <CardContent className="space-y-2">
            {history.length === 0 ? (
              <p className="text-sm text-muted-foreground">No workflow actions yet.</p>
            ) : (
              <ul className="space-y-2">
                {history.map((h) => (
                  <li key={h.id} className="text-sm">
                    <span className="font-medium">{h.action}</span> on {h.stage_name} — {h.actor_email ?? h.actor_id ?? 'Unknown'}
                  </li>
                ))}
              </ul>
            )}
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="linked">
        <Card>
          <CardHeader>
            <CardTitle>Linked documents</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" size="sm" onClick={() => createLinked('amendments')}>Create amendment</Button>
              <Button variant="outline" size="sm" onClick={() => createRenewal('extension')}>Renew (extend)</Button>
              <Button variant="outline" size="sm" onClick={() => createRenewal('new_version')}>Renew (new version)</Button>
              <Button variant="outline" size="sm" onClick={() => createLinked('side-letters')}>Add side letter</Button>
            </div>
            <div className="text-sm text-muted-foreground">
              Amendments: {linked?.amendment?.length ?? 0} · Renewals: {linked?.renewal?.length ?? 0} · Side letters: {linked?.side_letter?.length ?? 0}
            </div>
          </CardContent>
        </Card>
      </TabsContent>

      <TabsContent value="key-dates">
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>Key dates</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {keyDates.length === 0 ? (
                <p className="text-sm text-muted-foreground">No key dates yet.</p>
              ) : (
                <ul className="space-y-2 text-sm">
                  {keyDates.map((k) => (
                    <li key={k.id} className="flex flex-wrap items-center justify-between gap-2">
                      <span>{k.date_type}: {k.date_value}</span>
                      <div className="flex items-center gap-2">
                        {!k.is_verified && (
                          <Button size="sm" variant="outline" onClick={() => verifyKeyDate(k.id)}>Verify</Button>
                        )}
                        <Button size="sm" variant="outline" onClick={() => deleteKeyDate(k.id)}>Delete</Button>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
              <div className="grid gap-2 md:grid-cols-4">
                <Input placeholder="date_type" value={keyDateType} onChange={(e) => setKeyDateType(e.target.value)} />
                <Input type="date" value={keyDateValue} onChange={(e) => setKeyDateValue(e.target.value)} />
                <Input placeholder="description" value={keyDateDescription} onChange={(e) => setKeyDateDescription(e.target.value)} />
                <Input placeholder="reminder days (e.g. 90,60)" value={keyDateReminders} onChange={(e) => setKeyDateReminders(e.target.value)} />
              </div>
              <Button variant="outline" size="sm" onClick={addKeyDate}>Add key date</Button>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Reminders</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {reminders.length === 0 ? (
                <p className="text-sm text-muted-foreground">No reminders yet.</p>
              ) : (
                <div className="space-y-2">
                  {reminders.map((r) => (
                    <div key={r.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-3 text-sm">
                      <div>
                        <p className="font-medium">{r.reminder_type} · {r.lead_days} days</p>
                        <p className="text-muted-foreground">{r.recipient_email ?? 'No recipient'} · {r.channel}</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <Button size="sm" variant="outline" onClick={() => toggleReminder(r)}>
                          {r.is_active ? 'Deactivate' : 'Activate'}
                        </Button>
                        <Button size="sm" variant="outline" onClick={() => deleteReminder(r.id)}>Delete</Button>
                      </div>
                    </div>
                  ))}
                </div>
              )}

              <div className="space-y-3 border-t border-border pt-4">
                <p className="text-sm font-medium">Add reminder</p>
                <div className="grid gap-2 md:grid-cols-3">
                  <select
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={reminderKeyDateId}
                    onChange={(e) => setReminderKeyDateId(e.target.value)}
                  >
                    <option value="">Key date (optional)</option>
                    {keyDates.map((k) => (
                      <option key={k.id} value={k.id}>{k.date_type}: {k.date_value}</option>
                    ))}
                  </select>
                  <select
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={reminderType}
                    onChange={(e) => setReminderType(e.target.value)}
                  >
                    <option value="expiry">Expiry</option>
                    <option value="renewal_notice">Renewal notice</option>
                    <option value="payment">Payment</option>
                    <option value="sla">SLA</option>
                    <option value="obligation">Obligation</option>
                    <option value="custom">Custom</option>
                  </select>
                  <Input placeholder="Lead days" value={reminderLeadDays} onChange={(e) => setReminderLeadDays(e.target.value)} />
                </div>
                <div className="grid gap-2 md:grid-cols-2">
                  <select
                    className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={reminderChannel}
                    onChange={(e) => setReminderChannel(e.target.value)}
                  >
                    <option value="email">Email</option>
                    <option value="teams">Teams</option>
                    <option value="calendar">Calendar</option>
                  </select>
                  <Input placeholder="Recipient email" value={reminderRecipient} onChange={(e) => setReminderRecipient(e.target.value)} />
                </div>
                {reminderError && <p className="text-sm text-destructive">{reminderError}</p>}
                <Button variant="outline" size="sm" onClick={addReminder}>Add reminder</Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </TabsContent>


      <TabsContent value="ai">
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle>AI Analysis</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="outline">Analyze</Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent>
                    {ANALYSIS_TYPES.map((t) => (
                      <DropdownMenuItem key={t.value} onClick={() => triggerAnalysis(t.value)}>
                        {t.label}
                      </DropdownMenuItem>
                    ))}
                  </DropdownMenuContent>
                </DropdownMenu>
                {analysisLoading && <p className="text-sm text-muted-foreground">Running analysis…</p>}
              </div>
              {analysisError && <p className="text-sm text-destructive">{analysisError}</p>}
              <div className="grid gap-3 md:grid-cols-2">
                {analyses.map((a) => (
                  <Card key={a.id}>
                    <CardHeader>
                      <CardTitle className="flex items-center justify-between text-base">
                        {a.analysis_type}
                        <Badge variant={a.status === 'completed' ? 'default' : 'secondary'}>{a.status}</Badge>
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-1 text-sm">
                      <p>Tokens: {a.token_usage_input ?? 0} + {a.token_usage_output ?? 0}</p>
                      <p>Cost: ${Number(a.cost_usd ?? 0).toFixed(4)}</p>
                      <p>Processing: {a.processing_time_ms ?? 0} ms</p>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </CardContent>
          </Card>

          {summaryResult && (
            <Card>
              <CardHeader>
                <CardTitle>Summary</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p className="whitespace-pre-wrap">{summaryResult.summary}</p>
                <p className="text-muted-foreground">Key parties: {summaryResult.key_parties?.join(', ') || '—'}</p>
                <p className="text-muted-foreground">Effective: {summaryResult.effective_date ?? '—'} · Expiry: {summaryResult.expiry_date ?? '—'}</p>
                <p className="text-muted-foreground">Governing law: {summaryResult.governing_law ?? '—'}</p>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader>
              <CardTitle>Extracted fields</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Field</TableHead>
                    <TableHead>Value</TableHead>
                    <TableHead>Evidence</TableHead>
                    <TableHead>Confidence</TableHead>
                    <TableHead>Verified</TableHead>
                    <TableHead>Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {extractedFields.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={6} className="text-sm text-muted-foreground">No extracted fields.</TableCell>
                    </TableRow>
                  ) : (
                    extractedFields.map((f) => (
                      <TableRow key={f.id}>
                        <TableCell>{f.field_name}</TableCell>
                        <TableCell>
                          {editingFieldId === f.id ? (
                            <Input value={editingFieldValue} onChange={(e) => setEditingFieldValue(e.target.value)} />
                          ) : (
                            f.field_value ?? '—'
                          )}
                        </TableCell>
                        <TableCell className="max-w-xs text-xs text-muted-foreground">{f.evidence_clause ?? '—'}</TableCell>
                        <TableCell>
                          <div className="h-2 w-24 rounded-full bg-muted">
                            <div
                              className="h-2 rounded-full bg-emerald-500"
                              style={{ width: `${Math.round((f.confidence ?? 0) * 100)}%` }}
                            />
                          </div>
                        </TableCell>
                        <TableCell>{f.is_verified ? 'Yes' : 'No'}</TableCell>
                        <TableCell>
                          {editingFieldId === f.id ? (
                            <Button size="sm" variant="outline" onClick={() => correctField(f.id, editingFieldValue)}>Save</Button>
                          ) : (
                            <div className="flex items-center gap-2">
                              <Button size="sm" variant="outline" onClick={() => verifyField(f.id)}>Verify</Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => {
                                  setEditingFieldId(f.id);
                                  setEditingFieldValue(f.field_value ?? '');
                                }}
                              >
                                Correct
                              </Button>
                            </div>
                          )}
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Risk assessment</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="flex items-center gap-3">
                <div className="h-2 w-40 rounded-full bg-muted">
                  <div className={`h-2 rounded-full ${riskColor}`} style={{ width: `${Math.round(riskScore * 100)}%` }} />
                </div>
                <span className="text-sm text-muted-foreground">{Math.round(riskScore * 100)}%</span>
              </div>
              <div className="grid gap-2 md:grid-cols-2">
                {riskList.map((r: RiskItem, idx: number) => (
                  <Card key={`${r.category}-${idx}`}>
                    <CardHeader>
                      <CardTitle className="flex items-center justify-between text-sm">
                        {r.category}
                        <Badge variant="secondary">{r.severity}</Badge>
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm space-y-1">
                      <p>{r.description}</p>
                      {r.recommendation && <p className="text-muted-foreground">{r.recommendation}</p>}
                    </CardContent>
                  </Card>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Deviations</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Clause</TableHead>
                    <TableHead>Template</TableHead>
                    <TableHead>Contract</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Risk</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {deviationList.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-sm text-muted-foreground">No deviations.</TableCell>
                    </TableRow>
                  ) : (
                    deviationList.map((d: DeviationItem, idx: number) => (
                      <TableRow key={`${d.clause_reference}-${idx}`}>
                        <TableCell>{d.clause_reference}</TableCell>
                        <TableCell className="text-xs text-muted-foreground">{d.template_text ?? '—'}</TableCell>
                        <TableCell className="text-xs">{d.contract_text}</TableCell>
                        <TableCell>{d.deviation_type}</TableCell>
                        <TableCell>{d.risk_level}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Obligations</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Type</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead>Due</TableHead>
                    <TableHead>Recurrence</TableHead>
                    <TableHead>Responsible</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {obligationsList.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-sm text-muted-foreground">No obligations found.</TableCell>
                    </TableRow>
                  ) : (
                    obligationsList.map((o: ObligationItem, idx: number) => (
                      <TableRow key={`${o.obligation_type}-${idx}`}>
                        <TableCell>{o.obligation_type}</TableCell>
                        <TableCell>{o.description}</TableCell>
                        <TableCell>{o.due_date ?? '—'}</TableCell>
                        <TableCell>{o.recurrence ?? '—'}</TableCell>
                        <TableCell>{o.responsible_party ?? '—'}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </TabsContent>

      <TabsContent value="languages">
        <Card>
          <CardHeader>
            <CardTitle>Language versions</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            {languages.length === 0 ? (
              <p className="text-sm text-muted-foreground">No language versions.</p>
            ) : (
              <div className="space-y-2">
                {languages.map((lang) => (
                  <div key={lang.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border p-3 text-sm">
                    <div>
                      <p className="font-medium">
                        {lang.language_code.toUpperCase()} {lang.is_primary && <Badge className="ml-2">Primary</Badge>}
                      </p>
                      <p className="text-muted-foreground">{lang.file_name ?? '—'}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button size="sm" variant="outline" onClick={() => downloadLanguage(lang.id)}>Download</Button>
                      <Button size="sm" variant="outline" onClick={() => deleteLanguage(lang.id)}>Delete</Button>
                    </div>
                  </div>
                ))}
              </div>
            )}

            <div className="space-y-2 border-t border-border pt-4">
              <p className="text-sm font-medium">Add language version</p>
              <div className="grid gap-2 md:grid-cols-3">
                <select className="rounded-md border border-input bg-background px-3 py-2 text-sm" value={languageCode} onChange={(e) => setLanguageCode(e.target.value)}>
                  {LANGUAGE_OPTIONS.map((lang) => (
                    <option key={lang} value={lang}>{lang.toUpperCase()}</option>
                  ))}
                </select>
                <div className="flex items-center gap-2">
                  <input type="checkbox" checked={languagePrimary} onChange={(e) => setLanguagePrimary(e.target.checked)} />
                  <span className="text-sm">Primary</span>
                </div>
                <input type="file" onChange={(e) => setLanguageFile(e.target.files?.[0] ?? null)} />
              </div>
              {languageError && <p className="text-sm text-destructive">{languageError}</p>}
              <Button size="sm" variant="outline" onClick={uploadLanguage}>Upload</Button>
            </div>
          </CardContent>
        </Card>
      </TabsContent>
    </Tabs>
  );
}

interface WorkflowTemplate {
  id: string;
  name: string;
  status: string;
  version: number;
  contract_type: string;
}

interface WorkflowInstance {
  id: string;
  template_id: string;
  current_stage: string;
}

interface WorkflowAction {
  id: string;
  stage_name: string;
  action: string;
  actor_id: string | null;
  actor_email: string | null;
}

interface LinkedContracts {
  amendment: Array<{ id: string; title: string | null }>;
  renewal: Array<{ id: string; title: string | null }>;
  side_letter: Array<{ id: string; title: string | null }>;
  addendum?: Array<{ id: string; title: string | null }>;
}

interface KeyDate {
  id: string;
  date_type: string;
  date_value: string;
  is_verified: boolean;
}

interface Reminder {
  id: string;
  key_date_id: string | null;
  reminder_type: string;
  lead_days: number;
  channel: string;
  recipient_email: string | null;
  is_active: boolean;
}

interface AnalysisRecord {
  id: string;
  analysis_type: string;
  status: string;
  result: Record<string, any> | null;
  token_usage_input: number | null;
  token_usage_output: number | null;
  cost_usd: number | null;
  processing_time_ms: number | null;
}

interface AnalysisResponse {
  analyses: AnalysisRecord[];
  extracted_fields: ExtractedField[];
}

interface ExtractedField {
  id: string;
  field_name: string;
  field_value: string | null;
  evidence_clause: string | null;
  confidence: number | null;
  is_verified: boolean;
}

interface RiskItem {
  category: string;
  description: string;
  severity: string;
  recommendation?: string | null;
}

interface DeviationItem {
  clause_reference: string;
  template_text?: string | null;
  contract_text: string;
  deviation_type: string;
  risk_level: string;
}

interface ObligationItem {
  obligation_type: string;
  description: string;
  due_date?: string | null;
  recurrence?: string | null;
  responsible_party?: string | null;
}

interface ContractLanguage {
  id: string;
  language_code: string;
  file_name: string | null;
  is_primary: boolean;
}
