'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { signOut } from 'next-auth/react';
import {
  AlertTriangle,
  BarChart3,
  BookTemplate,
  Building2,
  ClipboardCheck,
  FileText,
  FolderKanban,
  GitBranch,
  Globe,
  Handshake,
  LayoutDashboard,
  ScrollText,
  Settings,
  ShieldAlert,
  Users,
} from 'lucide-react';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarSeparator,
} from '@/components/ui/sidebar';
import { useRoles } from '@/hooks/use-roles';

interface NavItem {
  href: string;
  label: string;
  icon: React.ComponentType<{ className?: string }>;
  roles?: string[];
}

interface NavGroup {
  label: string;
  roles?: string[];
  items: NavItem[];
}

const navGroups: NavGroup[] = [
  {
    label: 'Overview',
    items: [{ href: '/', label: 'Dashboard', icon: LayoutDashboard }],
  },
  {
    label: 'Org Structure',
    items: [
      { href: '/regions', label: 'Regions', icon: Globe },
      { href: '/entities', label: 'Entities', icon: Building2 },
      { href: '/projects', label: 'Projects', icon: FolderKanban },
    ],
  },
  {
    label: 'Contracts',
    items: [
      { href: '/contracts', label: 'All Contracts', icon: FileText },
      { href: '/wiki-contracts', label: 'Templates', icon: BookTemplate },
      { href: '/obligations', label: 'Obligations', icon: ClipboardCheck },
      { href: '/merchant-agreements', label: 'Merchant Agreements', icon: Handshake },
    ],
  },
  {
    label: 'Counterparties',
    items: [
      { href: '/counterparties', label: 'All Counterparties', icon: Users },
      { href: '/override-requests', label: 'Overrides', icon: ShieldAlert, roles: ['System Admin', 'Legal'] },
    ],
  },
  {
    label: 'Workflows',
    items: [
      { href: '/workflows', label: 'Templates', icon: GitBranch },
      { href: '/escalations', label: 'Escalations', icon: AlertTriangle, roles: ['System Admin', 'Legal'] },
    ],
  },
  {
    label: 'Admin',
    roles: ['System Admin', 'Legal', 'Audit'],
    items: [
      { href: '/audit', label: 'Audit', icon: ScrollText, roles: ['System Admin', 'Legal', 'Audit'] },
      { href: '/reports', label: 'Reports', icon: BarChart3 },
    ],
  },
];

export function AppSidebar() {
  const pathname = usePathname();
  const { roles: userRoles } = useRoles();

  const visibleGroups = navGroups
    .filter((group) => !group.roles || group.roles.some((r) => userRoles.includes(r)))
    .map((group) => ({
      ...group,
      items: group.items.filter((item) => !item.roles || item.roles.some((r) => userRoles.includes(r))),
    }))
    .filter((group) => group.items.length > 0);

  return (
    <Sidebar collapsible="icon">
      <SidebarContent>
        <div className="px-4 pb-2 pt-4 text-lg font-semibold">CCRS</div>
        <nav aria-label="Main navigation">
          {visibleGroups.map((group) => (
            <SidebarGroup key={group.label}>
              <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {group.items.map((item) => {
                    const isActive =
                      item.href === '/' ? pathname === '/' : pathname === item.href || pathname.startsWith(`${item.href}/`);
                    const Icon = item.icon;
                    return (
                      <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton asChild isActive={isActive} tooltip={item.label}>
                          <Link href={item.href} aria-current={isActive ? 'page' : undefined}>
                            <Icon className="size-4" />
                            <span>{item.label}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          ))}
        </nav>
        <SidebarSeparator className="my-2" />
        <SidebarGroup>
          <SidebarGroupLabel>Settings</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton
                  asChild
                  isActive={pathname === '/settings'}
                  tooltip="Settings"
                >
                  <Link href="/settings" aria-current={pathname === '/settings' ? 'page' : undefined}>
                    <Settings className="size-4" />
                    <span>Settings</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={() => signOut({ callbackUrl: '/login' })} tooltip="Sign out">
              <span>Sign out</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  );
}
