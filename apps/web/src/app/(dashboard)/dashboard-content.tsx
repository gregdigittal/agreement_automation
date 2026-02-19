'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { FileText, Clock, AlertTriangle, CheckCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

interface DashboardData {
  contractStatus: { state: string; count: number }[];
  expiryHorizon: { window: string; count: number }[];
  escalationCount: number;
  recentAudit: { action: string; resource_type: string; actor_email: string; created_at: string }[];
  notificationCount: number;
}

export function DashboardContent({ userName }: { userName: string }) {
  const [data, setData] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    Promise.all([
      fetch('/api/ccrs/reports/contract-status').then((r) => (r.ok ? r.json() : [])),
      fetch('/api/ccrs/reports/expiry-horizon').then((r) => (r.ok ? r.json() : [])),
      fetch('/api/ccrs/escalations/active').then((r) => (r.ok ? r.json() : [])),
      fetch('/api/ccrs/audit?limit=10').then((r) => (r.ok ? r.json() : [])),
      fetch('/api/ccrs/notifications/unread-count').then((r) => (r.ok ? r.json() : { count: 0 })),
    ])
      .then(([status, expiry, escalations, audit, notifs]) => {
        setData({
          contractStatus: status,
          expiryHorizon: expiry,
          escalationCount: Array.isArray(escalations) ? escalations.length : 0,
          recentAudit: audit,
          notificationCount: notifs.count ?? 0,
        });
        setLoading(false);
      })
      .catch(() => setLoading(false));
  }, []);

  const today = new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });

  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
          <Skeleton className="h-24" />
        </div>
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-64" />
          <Skeleton className="h-64" />
        </div>
      </div>
    );
  }

  const totalContracts = data?.contractStatus?.reduce((sum, s) => sum + s.count, 0) ?? 0;
  const pendingApproval =
    data?.contractStatus
      ?.filter((s) => ['review', 'approval', 'legal_review'].includes(s.state))
      .reduce((sum, s) => sum + s.count, 0) ?? 0;
  const expiringSoon =
    data?.expiryHorizon?.filter((e) => e.window === '30_days').reduce((sum, e) => sum + e.count, 0) ?? 0;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Welcome back, {userName}</h1>
          <p className="text-muted-foreground">{today}</p>
        </div>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Total Contracts</CardTitle>
            <FileText className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{totalContracts}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Pending Approval</CardTitle>
            <Clock className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{pendingApproval}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Expiring &lt;30 Days</CardTitle>
            <AlertTriangle className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{expiringSoon}</div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground">Active Escalations</CardTitle>
            <AlertTriangle className="h-4 w-4 text-destructive" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{data?.escalationCount ?? 0}</div>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Recent Activity</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="space-y-3">
              {(data?.recentAudit ?? []).slice(0, 8).map((entry, i) => (
                <div key={i} className="flex items-center justify-between text-sm">
                  <div>
                    <span className="font-medium">{entry.action.replace(/_/g, ' ')}</span>
                    <span className="text-muted-foreground"> on {entry.resource_type}</span>
                  </div>
                  <span className="text-xs text-muted-foreground">
                    {new Date(entry.created_at).toLocaleString()}
                  </span>
                </div>
              ))}
              {(!data?.recentAudit || data.recentAudit.length === 0) && (
                <p className="text-sm text-muted-foreground">No recent activity</p>
              )}
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardHeader>
            <CardTitle>Quick Links</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-2">
              <Link href="/contracts/upload" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <FileText className="h-4 w-4" /> Upload a contract
              </Link>
              <Link href="/counterparties/new" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> Add a counterparty
              </Link>
              <Link href="/workflows" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> Manage workflows
              </Link>
              <Link href="/reports" className="flex items-center gap-2 rounded-md p-2 hover:bg-muted text-sm">
                <CheckCircle className="h-4 w-4" /> View reports
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
