import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { CreateCounterpartyForm } from '../create-counterparty-form';

export default function NewCounterpartyPage() {
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">New counterparty</h1>
      <Card>
        <CardHeader><CardTitle>Counterparty details</CardTitle></CardHeader>
        <CardContent>
          <CreateCounterpartyForm />
        </CardContent>
      </Card>
    </div>
  );
}
