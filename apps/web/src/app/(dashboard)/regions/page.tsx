import Link from 'next/link';
import { RegionsList } from './regions-list';
import { Button } from '@/components/ui/button';

export default async function RegionsPage() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Regions</h1>
        <Button asChild>
          <Link href="/regions/new">Add region</Link>
        </Button>
      </div>
      <RegionsList />
    </div>
  );
}
