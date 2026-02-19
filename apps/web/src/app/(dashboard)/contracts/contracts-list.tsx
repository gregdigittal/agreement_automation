'use client';

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { Contract, Counterparty, Entity, Project, Region } from '@/lib/types';
import { handleApiError } from '@/lib/api-error';

const LIMIT = 25;
const WORKFLOW_OPTIONS = ['draft', 'review', 'signing', 'executed', 'archived'];

export function ContractsList() {
  const [list, setList] = useState<Contract[]>([]);
  const [loading, setLoading] = useState(true);
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
      .catch(() => toast.error('Failed to load regions'));
    fetch('/api/ccrs/entities?limit=500')
      .then((r) => r.json())
      .then(setEntities)
      .catch(() => toast.error('Failed to load entities'));
    fetch('/api/ccrs/projects?limit=500')
      .then((r) => r.json())
      .then(setProjects)
      .catch(() => toast.error('Failed to load projects'));
    fetch('/api/ccrs/counterparties?limit=500')
      .then((r) => r.json())
      .then(setCounterparties)
      .catch(() => toast.error('Failed to load counterparties'));
  }, []);

  useEffect(() => {
    setLoading(true);
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
      .then(async (r) => {
        if (await handleApiError(r)) return null;
        const countHeader = r.headers.get('X-Total-Count');
        setTotalCount(countHeader ? Number(countHeader) : 0);
        return r.json();
      })
      .then((data) => {
        if (!data) return;
        setList(data);
      })
      .catch(() => toast.error('Failed to load contracts'))
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
          <Select
            value={regionId || 'all'}
            onValueChange={(value) => {
              setRegionId(value === 'all' ? '' : value);
              setEntityId('');
              setProjectId('');
              setOffset(0);
            }}
          >
            <SelectTrigger className="min-w-[180px]">
              <SelectValue placeholder="All regions" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All regions</SelectItem>
              {regions.map((r) => (
                <SelectItem key={r.id} value={r.id}>
                  {r.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={entityId || 'all'}
            onValueChange={(value) => {
              setEntityId(value === 'all' ? '' : value);
              setProjectId('');
              setOffset(0);
            }}
          >
            <SelectTrigger className="min-w-[180px]">
              <SelectValue placeholder="All entities" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All entities</SelectItem>
              {filteredEntities.map((e) => (
                <SelectItem key={e.id} value={e.id}>
                  {e.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={projectId || 'all'}
            onValueChange={(value) => {
              setProjectId(value === 'all' ? '' : value);
              setOffset(0);
            }}
          >
            <SelectTrigger className="min-w-[180px]">
              <SelectValue placeholder="All projects" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All projects</SelectItem>
              {filteredProjects.map((p) => (
                <SelectItem key={p.id} value={p.id}>
                  {p.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Select
            value={contractType || 'all'}
            onValueChange={(value) => {
              setContractType(value === 'all' ? '' : value);
              setOffset(0);
            }}
          >
            <SelectTrigger className="min-w-[160px]">
              <SelectValue placeholder="All types" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All types</SelectItem>
              <SelectItem value="Commercial">Commercial</SelectItem>
              <SelectItem value="Merchant">Merchant</SelectItem>
            </SelectContent>
          </Select>
          <Select
            value={workflowState || 'all'}
            onValueChange={(value) => {
              setWorkflowState(value === 'all' ? '' : value);
              setOffset(0);
            }}
          >
            <SelectTrigger className="min-w-[160px]">
              <SelectValue placeholder="All states" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All states</SelectItem>
              {WORKFLOW_OPTIONS.map((s) => (
                <SelectItem key={s} value={s}>
                  {s}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {loading ? (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
        </div>
      ) : (
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
      )}

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
