import WikiContractDetail from './wiki-contract-detail';

export default async function WikiContractDetailPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;
  return <WikiContractDetail id={id} />;
}
