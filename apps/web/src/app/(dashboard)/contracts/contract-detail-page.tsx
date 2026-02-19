'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { ContractDetail } from './contract-detail';
import { Skeleton } from '@/components/ui/skeleton';
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
  if (loading) {
    return (
      <div className="space-y-6">
        <Skeleton className="h-8 w-1/3" />
        <div className="grid gap-4 md:grid-cols-2">
          <Skeleton className="h-32 w-full" />
          <Skeleton className="h-32 w-full" />
        </div>
        <Skeleton className="h-64 w-full" />
      </div>
    );
  }
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
