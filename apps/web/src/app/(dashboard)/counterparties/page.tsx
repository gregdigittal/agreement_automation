import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { CounterpartiesList } from './counterparties-list';

export default function CounterpartiesPage() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Counterparties</h1>
        <Button asChild>
          <Link href="/counterparties/new">Add counterparty</Link>
        </Button>
      </div>
      <CounterpartiesList />
    </div>
  );
}
