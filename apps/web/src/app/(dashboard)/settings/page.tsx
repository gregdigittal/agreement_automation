'use client';

import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { handleApiError } from '@/lib/api-error';

interface Notification {
  id: string;
  subject: string;
  channel: string;
  status: string;
  sent_at: string | null;
}

export default function SettingsPage() {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [emailEnabled, setEmailEnabled] = useState(true);
  const [teamsEnabled, setTeamsEnabled] = useState(false);

  useEffect(() => {
    fetch('/api/ccrs/notifications')
      .then(async (r) => {
        if (await handleApiError(r)) return null;
        return r.json();
      })
      .then((data) => {
        if (data) setNotifications(data);
      })
      .catch(() => toast.error('Failed to load notifications'));
  }, []);

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Notification Preferences</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap items-center gap-4 text-sm">
          <div className="flex items-center gap-2">
            <Checkbox
              id="email_enabled"
              checked={emailEnabled}
              onCheckedChange={(value) => setEmailEnabled(Boolean(value))}
            />
            <Label htmlFor="email_enabled">Email notifications</Label>
          </div>
          <div className="flex items-center gap-2">
            <Checkbox
              id="teams_enabled"
              checked={teamsEnabled}
              onCheckedChange={(value) => setTeamsEnabled(Boolean(value))}
            />
            <Label htmlFor="teams_enabled">Microsoft Teams notifications</Label>
          </div>
          <p className="text-muted-foreground">Preferences are placeholders for now.</p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recent Notifications</CardTitle>
        </CardHeader>
        <CardContent>
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
