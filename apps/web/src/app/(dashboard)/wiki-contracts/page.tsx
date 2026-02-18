import { WikiContractsList } from './wiki-contracts-list';

export default function WikiContractsPage() {
  return (
    <div className="space-y-4">
      <h1 className="text-2xl font-bold">WikiContracts</h1>
      <WikiContractsList />
    </div>
  );
}
