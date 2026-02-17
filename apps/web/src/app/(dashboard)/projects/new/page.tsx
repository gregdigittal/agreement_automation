import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CreateProjectForm } from '../create-project-form';

export default function NewProjectPage() {
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">New project</h1>
      <Card>
        <CardHeader><CardTitle>Project details</CardTitle></CardHeader>
        <CardContent>
          <CreateProjectForm />
        </CardContent>
      </Card>
    </div>
  );
}
