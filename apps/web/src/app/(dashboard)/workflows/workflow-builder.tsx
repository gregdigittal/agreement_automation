'use client';

import { useEffect, useMemo, useState } from 'react';
import { useRouter } from 'next/navigation';
import ReactFlow, { Background, useEdgesState, useNodesState } from 'reactflow';
import 'reactflow/dist/style.css';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { handleApiError } from '@/lib/api-error';

type StageType = 'approval' | 'signing' | 'review' | 'draft';

interface WorkflowStage {
  name: string;
  type: StageType;
  description?: string | null;
  owners: string[];
  approvers: string[];
  required_artifacts: string[];
  allowed_transitions: string[];
  sla_hours?: number | null;
  signing_order?: 'parallel' | 'sequential' | null;
}

interface WorkflowTemplate {
  id: string;
  name: string;
  contract_type: 'Commercial' | 'Merchant';
  stages: WorkflowStage[];
  status: string;
  version?: number;
}

interface WorkflowVersionEntry {
  id: string;
  created_at: string;
  details?: { version?: number; stages?: WorkflowStage[] };
}

interface EscalationRule {
  id: string;
  stage_name: string;
  sla_breach_hours: number;
  tier: number;
  escalate_to_role: string | null;
  escalate_to_user_id: string | null;
}

interface Region {
  id: string;
  name: string;
}

interface Entity {
  id: string;
  name: string;
}

interface Project {
  id: string;
  name: string;
}

const defaultStage: WorkflowStage = {
  name: 'Stage 1',
  type: 'approval',
  description: '',
  owners: [],
  approvers: [],
  required_artifacts: [],
  allowed_transitions: [],
  sla_hours: null,
  signing_order: null,
};

export function WorkflowBuilder({ templateId }: { templateId?: string }) {
  const router = useRouter();
  const [template, setTemplate] = useState<WorkflowTemplate | null>(null);
  const [name, setName] = useState('');
  const [contractType, setContractType] = useState<'Commercial' | 'Merchant'>('Commercial');
  const [stages, setStages] = useState<WorkflowStage[]>([defaultStage]);
  const [selectedStage, setSelectedStage] = useState(0);
  const [saving, setSaving] = useState(false);

  const [regions, setRegions] = useState<Region[]>([]);
  const [entities, setEntities] = useState<Entity[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [aiOpen, setAiOpen] = useState(false);
  const [aiDescription, setAiDescription] = useState('');
  const [aiRegionId, setAiRegionId] = useState('');
  const [aiEntityId, setAiEntityId] = useState('');
  const [aiProjectId, setAiProjectId] = useState('');
  const [aiResult, setAiResult] = useState<{
    explanation?: string;
    confidence?: number;
    validation_errors?: string[] | null;
  } | null>(null);

  const [escalationRules, setEscalationRules] = useState<EscalationRule[]>([]);
  const [ruleSlaHours, setRuleSlaHours] = useState('24');
  const [ruleTier, setRuleTier] = useState('1');
  const [ruleRole, setRuleRole] = useState('');
  const [ruleUserId, setRuleUserId] = useState('');

  const [versions, setVersions] = useState<WorkflowVersionEntry[]>([]);

  const nodesData = useMemo(
    () =>
      stages.map((s, idx) => ({
        id: s.name,
        data: { label: s.name },
        position: { x: idx * 220, y: 50 },
      })),
    [stages],
  );
  const edgesData = useMemo(
    () =>
      stages.flatMap((s) =>
        (s.allowed_transitions || []).map((t) => ({
          id: `${s.name}-${t}`,
          source: s.name,
          target: t,
        })),
      ),
    [stages],
  );
  const [nodes, setNodes, onNodesChange] = useNodesState(nodesData);
  const [edges, setEdges, onEdgesChange] = useEdgesState(edgesData);

  useEffect(() => {
    if (!templateId) return;
    fetch(`/api/ccrs/workflow-templates/${templateId}`)
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        return r.json();
      })
      .then((data: WorkflowTemplate) => {
        setTemplate(data);
        setName(data.name);
        setContractType(data.contract_type);
        setStages(data.stages?.length ? data.stages : [defaultStage]);
      })
      .catch(() => toast.error('Failed to load workflow template'));
  }, [templateId]);

  useEffect(() => {
    fetch('/api/ccrs/regions')
      .then((r) => (r.ok ? r.json() : []))
      .then(setRegions)
      .catch(() => undefined);
    fetch('/api/ccrs/entities')
      .then((r) => (r.ok ? r.json() : []))
      .then(setEntities)
      .catch(() => undefined);
    fetch('/api/ccrs/projects')
      .then((r) => (r.ok ? r.json() : []))
      .then(setProjects)
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!templateId) return;
    fetch(`/api/ccrs/workflow-templates/${templateId}/escalation-rules`)
      .then((r) => (r.ok ? r.json() : []))
      .then(setEscalationRules)
      .catch(() => undefined);
  }, [templateId]);

  useEffect(() => {
    if (!templateId) return;
    fetch(`/api/ccrs/workflow-templates/${templateId}/versions`)
      .then((r) => (r.ok ? r.json() : []))
      .then((data) => setVersions(Array.isArray(data) ? data : []))
      .catch(() => undefined);
  }, [templateId]);

  useEffect(() => {
    setNodes(nodesData);
    setEdges(edgesData);
  }, [nodesData, edgesData, setNodes, setEdges]);

  async function saveTemplate() {
    setSaving(true);
    const payload = { name, contractType, stages };
    try {
      if (templateId) {
        const res = await fetch(`/api/ccrs/workflow-templates/${templateId}`, {
          method: 'PATCH',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        if (await handleApiError(res)) return;
        setTemplate(await res.json());
        toast.success('Workflow template saved');
      } else {
        const res = await fetch('/api/ccrs/workflow-templates', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        if (await handleApiError(res)) return;
        const created = (await res.json()) as WorkflowTemplate;
        toast.success('Workflow template saved');
        router.push(`/workflows/${created.id}`);
      }
    } finally {
      setSaving(false);
    }
  }

  function handleTemplateSubmit(e: React.FormEvent) {
    e.preventDefault();
    void saveTemplate();
  }

  async function publishTemplate() {
    if (!templateId) return;
    setSaving(true);
    try {
      const res = await fetch(`/api/ccrs/workflow-templates/${templateId}/publish`, { method: 'POST' });
      if (await handleApiError(res)) return;
      setTemplate(await res.json());
      toast.success('Workflow template published');
    } finally {
      setSaving(false);
    }
  }

  async function generateWithAi() {
    if (!aiDescription.trim()) return;
    const res = await fetch('/api/ccrs/workflows/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        description: aiDescription,
        regionId: aiRegionId || undefined,
        entityId: aiEntityId || undefined,
        projectId: aiProjectId || undefined,
      }),
    });
    if (await handleApiError(res)) return;
    const data = await res.json();
    setStages(data.stages ?? [defaultStage]);
    setSelectedStage(0);
    setAiResult({
      explanation: data.explanation,
      confidence: data.confidence,
      validation_errors: data.validation_errors,
    });
    setAiOpen(false);
    toast.success('Workflow generated');
  }

  async function addEscalationRule() {
    if (!templateId) return;
    const currentStage = stages[selectedStage]?.name;
    if (!currentStage) return;
    const res = await fetch(`/api/ccrs/workflow-templates/${templateId}/escalation-rules`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        stage_name: currentStage,
        sla_breach_hours: Number(ruleSlaHours),
        tier: Number(ruleTier),
        escalate_to_role: ruleRole || undefined,
        escalate_to_user_id: ruleUserId || undefined,
      }),
    });
    if (res.ok) {
      const updated = await fetch(`/api/ccrs/workflow-templates/${templateId}/escalation-rules`).then((r) =>
        r.ok ? r.json() : [],
      );
      setEscalationRules(updated);
      setRuleRole('');
      setRuleUserId('');
    }
  }

  async function deleteEscalationRule(ruleId: string) {
    if (!templateId) return;
    const res = await fetch(`/api/ccrs/escalation-rules/${ruleId}`, { method: 'DELETE' });
    if (res.ok) {
      setEscalationRules((prev) => prev.filter((r) => r.id !== ruleId));
    }
  }

  function addStage() {
    setStages((prev) => [
      ...prev,
      { ...defaultStage, name: `Stage ${prev.length + 1}` },
    ]);
    setSelectedStage(stages.length);
  }

  function updateStage<K extends keyof WorkflowStage>(key: K, value: WorkflowStage[K]) {
    setStages((prev) => prev.map((s, idx) => (idx === selectedStage ? { ...s, [key]: value } : s)));
  }

  const previousStages = useMemo(() => {
    if (versions.length < 2) return [];
    return versions[1]?.details?.stages ?? [];
  }, [versions]);

  const stageDiffs = useMemo(() => {
    const currentStages = template?.stages ?? stages;
    const prevMap = new Map(previousStages.map((s) => [s.name, s]));
    const currMap = new Map(currentStages.map((s) => [s.name, s]));
    const added = currentStages.filter((s) => !prevMap.has(s.name));
    const removed = previousStages.filter((s) => !currMap.has(s.name));
    const modified = currentStages
      .filter((s) => prevMap.has(s.name))
      .filter((s) => JSON.stringify(s) !== JSON.stringify(prevMap.get(s.name)));
    return { added, removed, modified };
  }, [previousStages, stages, template?.stages]);

  return (
    <div className="space-y-4">
      <form onSubmit={handleTemplateSubmit} className="grid gap-4 md:grid-cols-3">
        <div className="space-y-2">
          <Label htmlFor="name">
            Template name <span className="text-destructive">*</span>
          </Label>
          <Input id="name" value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="space-y-2">
          <Label>Contract type</Label>
          <Select value={contractType} onValueChange={(value) => setContractType(value as 'Commercial' | 'Merchant')}>
            <SelectTrigger>
              <SelectValue placeholder="Select contract type" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="Commercial">Commercial</SelectItem>
              <SelectItem value="Merchant">Merchant</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div className="flex items-end gap-2">
          <Dialog open={aiOpen} onOpenChange={setAiOpen}>
            <DialogTrigger asChild>
              <Button variant="outline">Generate with AI</Button>
            </DialogTrigger>
            <DialogContent className="max-w-2xl">
              <DialogHeader>
                <DialogTitle>Generate workflow with AI</DialogTitle>
              </DialogHeader>
              <div className="space-y-3">
                <Textarea
                  className="min-h-[140px]"
                  placeholder="Describe the workflow requirements..."
                  value={aiDescription}
                  onChange={(e) => setAiDescription(e.target.value)}
                />
                <div className="grid gap-2 md:grid-cols-3">
                  <Select value={aiRegionId} onValueChange={setAiRegionId}>
                    <SelectTrigger>
                      <SelectValue placeholder="Region (optional)" />
                    </SelectTrigger>
                    <SelectContent>
                      {regions.map((r) => (
                        <SelectItem key={r.id} value={r.id}>
                          {r.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Select value={aiEntityId} onValueChange={setAiEntityId}>
                    <SelectTrigger>
                      <SelectValue placeholder="Entity (optional)" />
                    </SelectTrigger>
                    <SelectContent>
                      {entities.map((e) => (
                        <SelectItem key={e.id} value={e.id}>
                          {e.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <Select value={aiProjectId} onValueChange={setAiProjectId}>
                    <SelectTrigger>
                      <SelectValue placeholder="Project (optional)" />
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
              </div>
              <DialogFooter>
                <Button onClick={generateWithAi}>Generate</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
          <Button type="button" variant="outline" onClick={() => router.back()}>
            Cancel
          </Button>
          <Button type="submit" disabled={saving}>
            {saving ? 'Saving…' : 'Save'}
          </Button>
          {templateId && (
            <Button type="button" variant="outline" onClick={publishTemplate} disabled={saving}>
              Publish
            </Button>
          )}
        </div>
      </form>
      {aiResult && (
        <div className="rounded-md border border-border p-3 text-sm">
          <p className="font-medium">AI summary</p>
          <p className="text-muted-foreground">Confidence: {Math.round((aiResult.confidence ?? 0) * 100)}%</p>
          {aiResult.explanation && <p className="mt-2">{aiResult.explanation}</p>}
          {aiResult.validation_errors && aiResult.validation_errors.length > 0 && (
            <p className="mt-2 text-destructive">
              Validation warnings: {aiResult.validation_errors.join(', ')}
            </p>
          )}
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-[1fr_320px]">
        <div className="h-[320px] rounded-md border border-border">
          <ReactFlow nodes={nodes} edges={edges} onNodesChange={onNodesChange} onEdgesChange={onEdgesChange} fitView>
            <Background />
          </ReactFlow>
        </div>
        <div className="space-y-3 rounded-md border border-border p-4">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-medium">Stage config</h3>
            <Button size="sm" variant="outline" onClick={addStage}>Add stage</Button>
          </div>
          <div className="space-y-2">
            <Label>Stage</Label>
            <Select value={String(selectedStage)} onValueChange={(value) => setSelectedStage(Number(value))}>
              <SelectTrigger>
                <SelectValue placeholder="Select stage" />
              </SelectTrigger>
              <SelectContent>
                {stages.map((s, idx) => (
                  <SelectItem key={s.name} value={String(idx)}>
                    {s.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Name</Label>
            <Input value={stages[selectedStage]?.name ?? ''} onChange={(e) => updateStage('name', e.target.value)} />
          </div>
          <div className="space-y-2">
            <Label>Type</Label>
            <Select
              value={stages[selectedStage]?.type ?? 'approval'}
              onValueChange={(value) => updateStage('type', value as StageType)}
            >
              <SelectTrigger>
                <SelectValue placeholder="Select type" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="approval">Approval</SelectItem>
                <SelectItem value="signing">Signing</SelectItem>
                <SelectItem value="review">Review</SelectItem>
                <SelectItem value="draft">Draft</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-2">
            <Label>Allowed transitions (comma-separated)</Label>
            <Input
              value={(stages[selectedStage]?.allowed_transitions ?? []).join(',')}
              onChange={(e) => updateStage('allowed_transitions', e.target.value.split(',').map((v) => v.trim()).filter(Boolean))}
            />
          </div>

          <div className="space-y-2 border-t border-border pt-3">
            <h4 className="text-sm font-medium">Escalation rules</h4>
            {!templateId ? (
              <p className="text-xs text-muted-foreground">Save the template to add escalation rules.</p>
            ) : (
              <div className="space-y-2">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>SLA (hrs)</TableHead>
                      <TableHead>Tier</TableHead>
                      <TableHead>Escalate to</TableHead>
                      <TableHead />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {escalationRules.filter((r) => r.stage_name === stages[selectedStage]?.name).length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={4} className="text-xs text-muted-foreground">No rules for this stage.</TableCell>
                      </TableRow>
                    ) : (
                      escalationRules
                        .filter((r) => r.stage_name === stages[selectedStage]?.name)
                        .map((r) => (
                          <TableRow key={r.id}>
                            <TableCell>{r.sla_breach_hours}</TableCell>
                            <TableCell>{r.tier}</TableCell>
                            <TableCell>{r.escalate_to_role ?? r.escalate_to_user_id ?? '—'}</TableCell>
                            <TableCell>
                              <Button size="sm" variant="outline" onClick={() => deleteEscalationRule(r.id)}>Delete</Button>
                            </TableCell>
                          </TableRow>
                        ))
                    )}
                  </TableBody>
                </Table>

                <div className="grid gap-2 md:grid-cols-2">
                  <Input placeholder="SLA hours" value={ruleSlaHours} onChange={(e) => setRuleSlaHours(e.target.value)} />
                  <Input placeholder="Tier" value={ruleTier} onChange={(e) => setRuleTier(e.target.value)} />
                  <Input placeholder="Escalate to role" value={ruleRole} onChange={(e) => setRuleRole(e.target.value)} />
                  <Input placeholder="Escalate to user id" value={ruleUserId} onChange={(e) => setRuleUserId(e.target.value)} />
                </div>
                <Button size="sm" variant="outline" onClick={addEscalationRule}>Add rule</Button>
              </div>
            )}
          </div>
        </div>
      </div>

      {templateId && versions.length > 1 && (
        <div className="rounded-md border border-border p-4">
          <h3 className="text-sm font-medium">Version history</h3>
          <p className="text-xs text-muted-foreground">
            Comparing version {versions[1]?.details?.version ?? 'previous'} to current version {template?.version ?? 'current'}.
          </p>
          <div className="mt-3 grid gap-4 md:grid-cols-2">
            <div>
              <p className="text-xs font-medium text-muted-foreground">Previous</p>
              <pre className="mt-2 max-h-[320px] overflow-auto rounded-md bg-muted p-2 text-xs">
                {JSON.stringify(previousStages, null, 2)}
              </pre>
            </div>
            <div>
              <p className="text-xs font-medium text-muted-foreground">Current</p>
              <pre className="mt-2 max-h-[320px] overflow-auto rounded-md bg-muted p-2 text-xs">
                {JSON.stringify(template?.stages ?? stages, null, 2)}
              </pre>
            </div>
          </div>
          <div className="mt-4 grid gap-3 md:grid-cols-3">
            <div className="rounded-md border border-emerald-200 bg-emerald-50 p-2 text-xs">
              <p className="font-medium text-emerald-700">Added</p>
              {stageDiffs.added.length === 0 ? (
                <p className="text-emerald-600">None</p>
              ) : (
                stageDiffs.added.map((s) => <p key={s.name}>{s.name}</p>)
              )}
            </div>
            <div className="rounded-md border border-amber-200 bg-amber-50 p-2 text-xs">
              <p className="font-medium text-amber-700">Modified</p>
              {stageDiffs.modified.length === 0 ? (
                <p className="text-amber-600">None</p>
              ) : (
                stageDiffs.modified.map((s) => <p key={s.name}>{s.name}</p>)
              )}
            </div>
            <div className="rounded-md border border-rose-200 bg-rose-50 p-2 text-xs">
              <p className="font-medium text-rose-700">Removed</p>
              {stageDiffs.removed.length === 0 ? (
                <p className="text-rose-600">None</p>
              ) : (
                stageDiffs.removed.map((s) => <p key={s.name}>{s.name}</p>)
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
