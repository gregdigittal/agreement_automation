import { ContractDetailPage } from '../contract-detail-page';

export default async function Page({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  return <ContractDetailPage id={id} />;
}
