import { WorkflowBuilder } from '../workflow-builder';

export default function NewWorkflowPage() {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">New workflow template</h1>
      <WorkflowBuilder />
    </div>
  );
}
