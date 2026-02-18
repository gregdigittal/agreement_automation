import { WorkflowBuilder } from '../workflow-builder';

export default async function WorkflowDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Edit workflow</h1>
      <WorkflowBuilder templateId={id} />
    </div>
  );
}
