import { EditRegionForm } from '../edit-region-form';

export default async function RegionEditPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">Edit region</h1>
      <EditRegionForm id={id} />
    </div>
  );
}
