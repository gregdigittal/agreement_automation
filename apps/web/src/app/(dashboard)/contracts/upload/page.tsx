export const dynamic = 'force-dynamic';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { UploadContractForm } from '../upload-contract-form';

export default function UploadContractPage() {
  return (
    <div className="max-w-lg space-y-4">
      <h1 className="text-2xl font-bold">Upload contract</h1>
      <Card>
        <CardHeader><CardTitle>Contract details and file</CardTitle></CardHeader>
        <CardContent>
          <UploadContractForm />
        </CardContent>
      </Card>
    </div>
  );
}
