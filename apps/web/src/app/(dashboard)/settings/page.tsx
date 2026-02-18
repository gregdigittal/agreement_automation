'use client';

import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Notification {
  id: string;
  subject: string;
  channel: string;
  status: string;
  sent_at: string | null;
}

export default function SettingsPage() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [emailEnabled, setEmailEnabled] = useState(true);
  const [teamsEnabled, setTeamsEnabled] = useState(false);

  useEffect(() => {
    fetch('/api/ccrs/notifications')
      .then((r) => (r.ok ? r.json() : Promise.reject(r)))
      .then(setNotifications)
      .catch(() => setError('Failed to load notifications'));
  }, []);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Notification Preferences</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-4 text-sm">
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={emailEnabled} onChange={(e) => setEmailEnabled(e.target.checked)} />
            Email notifications
          </label>
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={teamsEnabled} onChange={(e) => setTeamsEnabled(e.target.checked)} />
            Microsoft Teams notifications
          </label>
          <p className="text-muted-foreground">Preferences are placeholders for now.</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent Notifications</CardTitle>
        </CardHeader>
        <CardContent>
          {error && <p className="text-sm text-destructive">{error}</p>}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Subject</TableHead>
                <TableHead>Channel</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Sent At</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {notifications.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={4} className="text-sm text-muted-foreground">No notifications yet.</TableCell>
                </TableRow>
              ) : (
                notifications.map((n) => (
                  <TableRow key={n.id}>
                    <TableCell>{n.subject}</TableCell>
                    <TableCell>{n.channel}</TableCell>
                    <TableCell>{n.status}</TableCell>
                    <TableCell>{n.sent_at ? new Date(n.sent_at).toLocaleString() : '—'}</TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}
