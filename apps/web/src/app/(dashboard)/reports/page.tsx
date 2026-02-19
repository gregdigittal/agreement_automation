'use client';

import { useEffect, useMemo, useState } from 'react';
import { differenceInDays } from 'date-fns';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Bar,
  BarChart,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';
import { handleApiError } from '@/lib/api-error';

interface Region { id: string; name: string }
interface Entity { id: string; name: string }

export default function ReportsPage() {
  const [regions, setRegions] = useState<Region[]>([]);
  const [entities, setEntities] = useState<Entity[]>([]);
  const [regionId, setRegionId] = useState('');
  const [entityId, setEntityId] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');
  const [loading, setLoading] = useState(true);

  const [contractStatus, setContractStatus] = useState<any>(null);
  const [expiryHorizon, setExpiryHorizon] = useState<any>(null);
  const [signingStatus, setSigningStatus] = useState<any>(null);
  const [aiCosts, setAiCosts] = useState<any>(null);

  useEffect(() => {
    const fetchJson = async (url: string) => {
      const res = await fetch(url);
      if (await handleApiError(res)) return null;
      return res.json();
    };
    Promise.all([fetchJson('/api/ccrs/regions'), fetchJson('/api/ccrs/entities')])
      .then(([regionsData, entitiesData]) => {
        if (regionsData) setRegions(regionsData);
        if (entitiesData) setEntities(entitiesData);
      })
      .catch(() => toast.error('Failed to load filters'));
  }, []);

  useEffect(() => {
    const params = new URLSearchParams();
    if (regionId) params.set('region_id', regionId);
    if (entityId) params.set('entity_id', entityId);
    const fetchJson = async (url: string) => {
      const res = await fetch(url);
      if (await handleApiError(res)) return null;
      return res.json();
    };
    let periodDays = 30;
    if (fromDate && toDate) {
      periodDays = Math.max(1, differenceInDays(new Date(toDate), new Date(fromDate)));
    }
    setLoading(true);
    Promise.all([
      fetchJson(`/api/ccrs/reports/contract-status?${params.toString()}`),
      fetchJson(`/api/ccrs/reports/expiry-horizon?${regionId ? `region_id=${regionId}` : ''}`),
      fetchJson('/api/ccrs/reports/signing-status'),
      fetchJson(`/api/ccrs/reports/ai-costs?period_days=${periodDays}`),
    ])
      .then(([statusData, horizonData, signingData, costsData]) => {
        if (statusData) setContractStatus(statusData);
        if (horizonData) setExpiryHorizon(horizonData);
        if (signingData) setSigningStatus(signingData);
        if (costsData) setAiCosts(costsData);
      })
      .catch(() => toast.error('Failed to load reports'))
      .finally(() => setLoading(false));
  }, [regionId, entityId, fromDate, toDate]);

  const stateData = useMemo(() => {
    if (!contractStatus?.by_state) return [];
    return Object.entries(contractStatus.by_state).map(([name, value]) => ({ name, value }));
  }, [contractStatus]);

  const typeData = useMemo(() => {
    if (!contractStatus?.by_type) return [];
    return Object.entries(contractStatus.by_type).map(([name, value]) => ({ name, value }));
  }, [contractStatus]);

  const expiryData = useMemo(() => {
    if (!expiryHorizon?.counts) return [];
    const labels: Record<string, string> = { '0_30': '0-30', '31_60': '31-60', '61_90': '61-90', '90_plus': '90+' };
    return Object.entries(expiryHorizon.counts).map(([key, value]) => ({ name: labels[key] ?? key, value }));
  }, [expiryHorizon]);

  const signingData = useMemo(() => {
    if (!signingStatus?.by_status) return [];
    return Object.entries(signingStatus.by_status).map(([name, value]) => ({ name, value }));
  }, [signingStatus]);

  const aiCostData = useMemo(() => {
    if (!aiCosts?.by_type) return [];
    return Object.entries(aiCosts.by_type).map(([name, value]: any) => ({
      name,
      cost: value.cost_usd,
      count: value.count,
    }));
  }, [aiCosts]);

  const stateLabel = stateData.length
    ? `Contract status breakdown: ${stateData.map((s) => `${s.name} ${s.value}`).join(', ')}`
    : 'Contract status breakdown: no data';
  const typeLabel = typeData.length
    ? `Contract type distribution: ${typeData.map((s) => `${s.name} ${s.value}`).join(', ')}`
    : 'Contract type distribution: no data';
  const expiryLabel = expiryData.length
    ? `Expiry horizon distribution: ${expiryData.map((s) => `${s.name} ${s.value}`).join(', ')}`
    : 'Expiry horizon distribution: no data';
  const signingLabel = signingData.length
    ? `Signing status distribution: ${signingData.map((s) => `${s.name} ${s.value}`).join(', ')}`
    : 'Signing status distribution: no data';
  const aiCostLabel = aiCostData.length
    ? `AI cost by type: ${aiCostData.map((s) => `${s.name} ${s.cost}`).join(', ')}`
    : 'AI cost by type: no data';

  function jsonToCsv(data: unknown): string {
    if (!Array.isArray(data) || data.length === 0) {
      if (data && typeof data === 'object') {
        const entries = Object.entries(data as Record<string, unknown>);
        const sections: string[] = [];
        for (const [key, value] of entries) {
          if (Array.isArray(value) && value.length > 0) {
            const headers = Object.keys(value[0] as Record<string, unknown>);
            const rows = value.map((row: Record<string, unknown>) =>
              headers.map((h) => `"${String(row[h] ?? '').replace(/"/g, '""')}"`).join(',')
            );
            sections.push(`${key}\n${headers.join(',')}\n${rows.join('\n')}`);
          }
        }
        return sections.join('\n\n');
      }
      return '';
    }
    const headers = Object.keys(data[0] as Record<string, unknown>);
    const rows = data.map((row: Record<string, unknown>) =>
      headers.map((h) => `"${String(row[h] ?? '').replace(/"/g, '""')}"`).join(',')
    );
    return `${headers.join(',')}\n${rows.join('\n')}`;
  }

  function downloadCsv(filename: string, data: unknown) {
    const csv = jsonToCsv(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-4">
      {loading && (
        <div className="space-y-4">
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-10 w-full" />
          <Skeleton className="h-64 w-full" />
        </div>
      )}
      <Card>
        <CardHeader>
          <CardTitle>Reports</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-4">
          <Select value={regionId || 'all'} onValueChange={(value) => setRegionId(value === 'all' ? '' : value)}>
            <SelectTrigger>
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
          <Select value={entityId || 'all'} onValueChange={(value) => setEntityId(value === 'all' ? '' : value)}>
            <SelectTrigger>
              <SelectValue placeholder="All entities" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">All entities</SelectItem>
              {entities.map((e) => (
                <SelectItem key={e.id} value={e.id}>
                  {e.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
          <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Contract Status Summary</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div className="h-64" role="img" aria-label={stateLabel}>
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie data={stateData} dataKey="value" nameKey="name" outerRadius={90}>
                  {stateData.map((_, idx) => (
                    <Cell key={idx} fill={['#6366f1', '#22c55e', '#f97316', '#ef4444'][idx % 4]} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </div>
          <div className="h-64" role="img" aria-label={typeLabel}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={typeData}>
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="value" fill="#6366f1" />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => downloadCsv('contract-status.csv', contractStatus)}>Export CSV</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Expiry Horizon</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64" role="img" aria-label={expiryLabel}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={expiryData}>
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="value" fill="#22c55e" />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => downloadCsv('expiry-horizon.csv', expiryHorizon)}>Export CSV</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Signing Pipeline</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64" role="img" aria-label={signingLabel}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart layout="vertical" data={signingData}>
                <XAxis type="number" />
                <YAxis type="category" dataKey="name" />
                <Tooltip />
                <Bar dataKey="value" fill="#f97316" />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => downloadCsv('signing-status.csv', signingStatus)}>Export CSV</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>AI Costs</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64" role="img" aria-label={aiCostLabel}>
            <ResponsiveContainer width="100%" height="100%">
              <BarChart data={aiCostData}>
                <XAxis dataKey="name" />
                <YAxis />
                <Tooltip />
                <Legend />
                <Bar dataKey="cost" fill="#14b8a6" />
              </BarChart>
            </ResponsiveContainer>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => downloadCsv('ai-costs.csv', aiCosts)}>Export CSV</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
