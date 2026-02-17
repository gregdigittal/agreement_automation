import Link from 'next/link';
import { Button } from '@/components/ui/button';
import { ProjectsList } from './projects-list';

export default function ProjectsPage() {
  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Projects</h1>
        <Button asChild>
          <Link href="/projects/new">Add project</Link>
        </Button>
      </div>
      <ProjectsList />
    </div>
  );
}
