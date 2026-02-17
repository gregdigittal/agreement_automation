import { AppNav } from '@/components/app-nav';

export default function DashboardLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <>
      <AppNav />
      <main className="container mx-auto p-4">{children}</main>
    </>
  );
}
