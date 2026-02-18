'use client';

import { useEffect, useMemo, useState } from 'react';
import { differenceInDays } from 'date-fns';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

interface Region { id: string; name: string }
interface Entity { id: string; name: string }

export default function ReportsPage() {
  const [regions, setRegions] = useState<Region[]>([]);
  const [entities, setEntities] = useState<Entity[]>([]);
  const [regionId, setRegionId] = useState('');
  const [entityId, setEntityId] = useState('');
  const [fromDate, setFromDate] = useState('');
  const [toDate, setToDate] = useState('');

  const [contractStatus, setContractStatus] = useState<any>(null);
  const [expiryHorizon, setExpiryHorizon] = useState<any>(null);
  const [signingStatus, setSigningStatus] = useState<any>(null);
  const [aiCosts, setAiCosts] = useState<any>(null);

  useEffect(() => {
    fetch('/api/ccrs/regions').then((r) => (r.ok ? r.json() : [])).then(setRegions).catch(() => undefined);
    fetch('/api/ccrs/entities').then((r) => (r.ok ? r.json() : [])).then(setEntities).catch(() => undefined);
  }, []);

  useEffect(() => {
    const params = new URLSearchParams();
    if (regionId) params.set('region_id', regionId);
    if (entityId) params.set('entity_id', entityId);
    fetch(`/api/ccrs/reports/contract-status?${params.toString()}`).then((r) => (r.ok ? r.json() : null)).then(setContractStatus);
    fetch(`/api/ccrs/reports/expiry-horizon?${regionId ? `region_id=${regionId}` : ''}`).then((r) => (r.ok ? r.json() : null)).then(setExpiryHorizon);
    fetch('/api/ccrs/reports/signing-status').then((r) => (r.ok ? r.json() : null)).then(setSigningStatus);

    let periodDays = 30;
    if (fromDate && toDate) {
      periodDays = Math.max(1, differenceInDays(new Date(toDate), new Date(fromDate)));
    }
    fetch(`/api/ccrs/reports/ai-costs?period_days=${periodDays}`).then((r) => (r.ok ? r.json() : null)).then(setAiCosts);
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

  function downloadText(filename: string, content: string, type = 'text/plain') {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Reports</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-2 md:grid-cols-4">
          <select className="rounded-md border border-input bg-background px-3 py-2 text-sm" value={regionId} onChange={(e) => setRegionId(e.target.value)}>
            <option value="">All regions</option>
            {regions.map((r) => (
              <option key={r.id} value={r.id}>{r.name}</option>
            ))}
          </select>
          <select className="rounded-md border border-input bg-background px-3 py-2 text-sm" value={entityId} onChange={(e) => setEntityId(e.target.value)}>
            <option value="">All entities</option>
            {entities.map((e) => (
              <option key={e.id} value={e.id}>{e.name}</option>
            ))}
          </select>
          <Input type="date" value={fromDate} onChange={(e) => setFromDate(e.target.value)} />
          <Input type="date" value={toDate} onChange={(e) => setToDate(e.target.value)} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Contract Status Summary</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div className="h-64">
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
          <div className="h-64">
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
            <Button variant="outline" size="sm" onClick={() => downloadText('contract-status.csv', JSON.stringify(contractStatus, null, 2))}>Export CSV</Button>
            <Button variant="outline" size="sm" onClick={() => downloadText('contract-status.pdf', JSON.stringify(contractStatus, null, 2), 'application/pdf')}>Export PDF</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Expiry Horizon</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64">
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
            <Button variant="outline" size="sm" onClick={() => downloadText('expiry-horizon.csv', JSON.stringify(expiryHorizon, null, 2))}>Export CSV</Button>
            <Button variant="outline" size="sm" onClick={() => downloadText('expiry-horizon.pdf', JSON.stringify(expiryHorizon, null, 2), 'application/pdf')}>Export PDF</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Signing Pipeline</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64">
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
            <Button variant="outline" size="sm" onClick={() => downloadText('signing-status.csv', JSON.stringify(signingStatus, null, 2))}>Export CSV</Button>
            <Button variant="outline" size="sm" onClick={() => downloadText('signing-status.pdf', JSON.stringify(signingStatus, null, 2), 'application/pdf')}>Export PDF</Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>AI Costs</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="h-64">
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
            <Button variant="outline" size="sm" onClick={() => downloadText('ai-costs.csv', JSON.stringify(aiCosts, null, 2))}>Export CSV</Button>
            <Button variant="outline" size="sm" onClick={() => downloadText('ai-costs.pdf', JSON.stringify(aiCosts, null, 2), 'application/pdf')}>Export PDF</Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
