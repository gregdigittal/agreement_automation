'use client';

import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

interface ContractDetailProps {
  contract: {
    id: string;
    title: string | null;
    contract_type: string;
    workflow_state: string;
    signing_status: string | null;
    file_name: string | null;
    created_at: string;
    regions?: { name: string };
    entities?: { name: string };
    projects?: { name: string };
    counterparties?: { legal_name: string; status: string };
  };
}

export function ContractDetail({ contract }: ContractDetailProps) {
  const [downloadUrl, setDownloadUrl] = useState<string | null>(null);

  async function getDownloadUrl() {
    const res = await fetch(`/api/ccrs/contracts/${contract.id}/download-url`);
    const data = await res.json();
    if (data?.url) setDownloadUrl(data.url);
  }

  return (
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
  );
}
