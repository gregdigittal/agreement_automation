'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ContractDetail } from './contract-detail';
import type { Contract } from '@/lib/types';

export function ContractDetailPage({ id }: { id: string }) {
  const router = useRouter();
  const [contract, setContract] = useState<Contract | null>(null);
  const [loading, setLoading] = useState(true);
  useEffect(() => {
    fetch(`/api/ccrs/contracts/${id}`)
      .then((r) => (r.ok ? r.json() : null))
      .then((payload: Contract | null) => setContract(payload))
      .finally(() => setLoading(false));
  }, [id]);
  if (loading) return <p className="text-muted-foreground">Loading…</p>;
  if (!contract) {
    router.push('/contracts');
    return null;
  }
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">{contract.title ?? 'Contract'}</h1>
      <ContractDetail contract={contract} />
    </div>
  );
}
