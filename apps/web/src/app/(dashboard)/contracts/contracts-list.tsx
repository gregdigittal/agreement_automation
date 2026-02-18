'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Contract, Counterparty, Entity, Project, Region } from '@/lib/types';

const LIMIT = 25;
const WORKFLOW_OPTIONS = ['draft', 'review', 'signing', 'executed', 'archived'];

export function ContractsList() {
  const [list, setList] = useState<Contract[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [totalCount, setTotalCount] = useState(0);

  const [regions, setRegions] = useState<Region[]>([]);
  const [entities, setEntities] = useState<Entity[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [counterparties, setCounterparties] = useState<Counterparty[]>([]);

  const [q, setQ] = useState('');
  const [regionId, setRegionId] = useState('');
  const [entityId, setEntityId] = useState('');
  const [projectId, setProjectId] = useState('');
  const [contractType, setContractType] = useState('');
  const [workflowState, setWorkflowState] = useState('');
  const [offset, setOffset] = useState(0);

  useEffect(() => {
    fetch('/api/ccrs/regions?limit=500')
      .then((r) => r.json())
      .then(setRegions)
      .catch(() => undefined);
    fetch('/api/ccrs/entities?limit=500')
      .then((r) => r.json())
      .then(setEntities)
      .catch(() => undefined);
    fetch('/api/ccrs/projects?limit=500')
      .then((r) => r.json())
      .then(setProjects)
      .catch(() => undefined);
    fetch('/api/ccrs/counterparties?limit=500')
      .then((r) => r.json())
      .then(setCounterparties)
      .catch(() => undefined);
  }, []);

  useEffect(() => {
    setLoading(true);
    setError(null);
    const params = new URLSearchParams();
    if (q.trim()) params.set('q', q.trim());
    if (regionId) params.set('regionId', regionId);
    if (entityId) params.set('entityId', entityId);
    if (projectId) params.set('projectId', projectId);
    if (contractType) params.set('contractType', contractType);
    if (workflowState) params.set('workflowState', workflowState);
    params.set('limit', String(LIMIT));
    params.set('offset', String(offset));
    fetch(`/api/ccrs/contracts?${params.toString()}`)
      .then((r) => {
        if (!r.ok) throw new Error(`${r.status}`);
        const countHeader = r.headers.get('X-Total-Count');
        setTotalCount(countHeader ? Number(countHeader) : 0);
        return r.json();
      })
      .then(setList)
      .catch((e) => setError(e.message))
      .finally(() => setLoading(false));
  }, [q, regionId, entityId, projectId, contractType, workflowState, offset]);

  const regionMap = useMemo(() => new Map(regions.map((r) => [r.id, r])), [regions]);
  const entityMap = useMemo(() => new Map(entities.map((e) => [e.id, e])), [entities]);
  const projectMap = useMemo(() => new Map(projects.map((p) => [p.id, p])), [projects]);
  const counterpartyMap = useMemo(() => new Map(counterparties.map((c) => [c.id, c])), [counterparties]);

  const filteredEntities = regionId ? entities.filter((e) => e.region_id === regionId) : entities;
  const filteredProjects = entityId ? projects.filter((p) => p.entity_id === entityId) : projects;

  const start = totalCount === 0 ? 0 : offset + 1;
  const end = offset + list.length;

  if (loading) return <p className="text-muted-foreground">Loading…</p>;

  return (
    <div className="space-y-4">
      <div className="space-y-3">
        <div className="flex flex-wrap items-center gap-3">
          <Input
            placeholder="Search contracts"
            value={q}
            onChange={(e) => {
              setQ(e.target.value);
              setOffset(0);
            }}
            className="max-w-sm"
          />
          <select
            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={regionId}
            onChange={(e) => {
              setRegionId(e.target.value);
              setEntityId('');
              setProjectId('');
              setOffset(0);
            }}
          >
            <option value="">All regions</option>
            {regions.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </select>
          <select
            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={entityId}
            onChange={(e) => {
              setEntityId(e.target.value);
              setProjectId('');
              setOffset(0);
            }}
          >
            <option value="">All entities</option>
            {filteredEntities.map((e) => (
              <option key={e.id} value={e.id}>{e.name}</option>
            ))}
          </select>
          <select
            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={projectId}
            onChange={(e) => {
              setProjectId(e.target.value);
              setOffset(0);
            }}
          >
            <option value="">All projects</option>
            {filteredProjects.map((p) => (
              <option key={p.id} value={p.id}>{p.name}</option>
            ))}
          </select>
          <select
            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={contractType}
            onChange={(e) => {
              setContractType(e.target.value);
              setOffset(0);
            }}
          >
            <option value="">All types</option>
            <option value="Commercial">Commercial</option>
            <option value="Merchant">Merchant</option>
          </select>
          <select
            className="rounded-md border border-input bg-background px-3 py-2 text-sm"
            value={workflowState}
            onChange={(e) => {
              setWorkflowState(e.target.value);
              setOffset(0);
            }}
          >
            <option value="">All states</option>
            {WORKFLOW_OPTIONS.map((s) => (
              <option key={s} value={s}>{s}</option>
            ))}
          </select>
        </div>
      </div>

      {error && <p className="text-sm text-destructive">Error: {error}</p>}
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Title</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Status</TableHead>
            <TableHead>Region</TableHead>
            <TableHead>Entity</TableHead>
            <TableHead>Counterparty</TableHead>
            <TableHead>Created At</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {list.length === 0 ? (
            <TableRow>
              <TableCell colSpan={8} className="text-sm text-muted-foreground">
                No contracts found.
              </TableCell>
            </TableRow>
          ) : (
            list.map((c) => (
              <TableRow key={c.id}>
                <TableCell>{c.title ?? 'Untitled'}</TableCell>
                <TableCell>{c.contract_type}</TableCell>
                <TableCell>{c.workflow_state}</TableCell>
                <TableCell>{regionMap.get(c.region_id)?.name ?? c.region_id}</TableCell>
                <TableCell>{entityMap.get(c.entity_id)?.name ?? c.entity_id}</TableCell>
                <TableCell>{counterpartyMap.get(c.counterparty_id)?.legal_name ?? c.counterparty_id}</TableCell>
                <TableCell>{new Date(c.created_at).toLocaleDateString()}</TableCell>
                <TableCell>
                  <Link href={`/contracts/${c.id}`} className="text-primary text-sm hover:underline">View</Link>
                </TableCell>
              </TableRow>
            ))
          )}
        </TableBody>
      </Table>

      <div className="flex flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-muted-foreground">
          Showing {start}-{end} of {totalCount}
        </p>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" disabled={offset === 0} onClick={() => setOffset(Math.max(0, offset - LIMIT))}>
            Previous
          </Button>
          <Button
            variant="outline"
            size="sm"
            disabled={offset + LIMIT >= totalCount}
            onClick={() => setOffset(offset + LIMIT)}
          >
            Next
          </Button>
        </div>
      </div>
    </div>
  );
}
