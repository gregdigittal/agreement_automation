import { WorkflowTemplatesList } from './workflow-templates-list';

export default function WorkflowsPage() {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">Workflows</h1>
      <WorkflowTemplatesList />
    </div>
  );
}
