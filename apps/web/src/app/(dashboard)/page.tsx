import { auth } from '@/auth';
import { DashboardContent } from './dashboard-content';

export default async function DashboardPage() {
  const session = await auth();
  const userName = session?.user?.name || session?.user?.email || 'User';
  return <DashboardContent userName={userName} />;
}
