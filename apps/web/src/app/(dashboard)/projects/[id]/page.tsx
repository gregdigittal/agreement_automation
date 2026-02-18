import { EditProjectForm } from '../edit-project-form';

export default async function ProjectEditPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <div className="max-w-md space-y-4">
      <h1 className="text-2xl font-bold">Edit project</h1>
      <EditProjectForm id={id} />
    </div>
  );
}
