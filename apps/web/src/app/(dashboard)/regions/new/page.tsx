import { CreateRegionForm } from '../create-region-form';

export default function NewRegionPage() {
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">New region</h1>
      <CreateRegionForm />
    </div>
  );
}
