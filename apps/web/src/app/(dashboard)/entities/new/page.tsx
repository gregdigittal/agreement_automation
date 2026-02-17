import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CreateEntityForm } from '../create-entity-form';

export default function NewEntityPage() {
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">New entity</h1>
      <Card>
        <CardHeader><CardTitle>Entity details</CardTitle></CardHeader>
        <CardContent>
          <CreateEntityForm />
        </CardContent>
      </Card>
    </div>
  );
}
