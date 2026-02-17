import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { ContractsList } from './contracts-list';

export default function ContractsPage() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Contracts</h1>
        <Button asChild>
          <Link href="/contracts/upload">Upload contract</Link>
        </Button>
      </div>
      <ContractsList />
    </div>
  );
}
