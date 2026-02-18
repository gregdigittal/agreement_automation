import { EditEntityForm } from '../edit-entity-form';

export default async function EntityEditPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">Edit entity</h1>
      <EditEntityForm id={id} />
    </div>
  );
}
