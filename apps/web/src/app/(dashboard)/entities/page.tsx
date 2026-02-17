import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { EntitiesList } from './entities-list';

export default function EntitiesPage() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Entities</h1>
        <Button asChild>
          <Link href="/entities/new">Add entity</Link>
        </Button>
      </div>
      <EntitiesList />
    </div>
  );
}
