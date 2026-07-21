<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    BarChart3,
    Bell,
    BookMarked,
    Boxes,
    Building2,
    CalendarClock,
    Car,
    ClipboardCheck,
    Contact,
    FileCheck2,
    Globe,
    Handshake,
    Headphones,
    KeyRound,
    Landmark,
    LayoutGrid,
    Network,
    PackageCheck,
    PhoneCall,
    ScrollText,
    ShoppingCart,
    Store,
    Truck,
    Users,
    UsersRound,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
        icon: LayoutGrid,
    },
];

const purchaseNavItems: NavItem[] = [
    { title: 'Purchase Leads', href: '/admin/purchase-leads', icon: ShoppingCart, permission: 'purchase-leads.view' },
    { title: 'Inspections', href: '/admin/inspections', icon: ClipboardCheck, permission: 'inspections.view' },
    { title: 'Vendor Submissions', href: '/admin/vendor-submissions', icon: PackageCheck, permission: 'vendor-submissions.view' },
    { title: 'Vendor Partners', href: '/admin/vendor-partners', icon: Handshake, permission: 'vendor-partners.view' },
];

const inventoryNavItems: NavItem[] = [
    { title: 'Inventory', href: '/admin/inventory', icon: Boxes, permission: 'vehicles.view' },
    { title: 'Workshop', href: '/admin/workshop', icon: Wrench, permission: 'refurbishment.view' },
    { title: 'Vendors', href: '/admin/vendors', icon: Store, permission: 'vendors.view' },
];

const crmNavItems: NavItem[] = [
    { title: 'Sales Leads', href: '/admin/sales-leads', icon: PhoneCall, permission: 'sales-leads.view' },
    { title: 'Visits', href: '/admin/visits', icon: CalendarClock, permission: 'visits.view' },
    { title: 'Test Drives', href: '/admin/test-drives', icon: Car, permission: 'test-drives.view' },
    { title: 'Bookings', href: '/admin/bookings', icon: BookMarked, permission: 'bookings.view' },
    { title: 'Customers', href: '/admin/customers', icon: Contact, permission: 'customers.view' },
    { title: 'Telecaller Report', href: '/admin/reports/telecaller', icon: Headphones, permission: 'telecalling.view' },
];

const financeNavItems: NavItem[] = [
    { title: 'Finance', href: '/admin/finance', icon: Wallet, permission: 'finance.view' },
    { title: 'Lenders', href: '/admin/lenders', icon: Landmark, permission: 'finance.view' },
];

const deliveryNavItems: NavItem[] = [
    { title: 'Deliveries', href: '/admin/deliveries', icon: Truck, permission: 'deliveries.view' },
    { title: 'RTO Cases', href: '/admin/rto-cases', icon: FileCheck2, permission: 'rto-cases.view' },
];

const visiblePurchaseItems = computed(() => purchaseNavItems.filter((item) => !item.permission || can(item.permission)));
const visibleInventoryItems = computed(() => inventoryNavItems.filter((item) => !item.permission || can(item.permission)));
const visibleCrmItems = computed(() => crmNavItems.filter((item) => !item.permission || can(item.permission)));
const visibleFinanceItems = computed(() => financeNavItems.filter((item) => !item.permission || can(item.permission)));
const visibleDeliveryItems = computed(() => deliveryNavItems.filter((item) => !item.permission || can(item.permission)));

const insightsNavItems: NavItem[] = [
    { title: 'Reports', href: '/admin/reports', icon: BarChart3, permission: 'reports.access-reports' },
    { title: 'Notifications', href: '/admin/notifications', icon: Bell },
];

const visibleInsightsItems = computed(() => insightsNavItems.filter((item) => !item.permission || can(item.permission)));

const adminNavItems: NavItem[] = [
    { title: 'Website Enquiries', href: '/admin/website-enquiries', icon: Globe, permission: 'public-website.view' },
    { title: 'Branches', href: '/admin/branches', icon: Building2, permission: 'branches.view' },
    { title: 'Departments', href: '/admin/departments', icon: Network, permission: 'departments.view' },
    { title: 'Teams', href: '/admin/teams', icon: UsersRound, permission: 'teams.view' },
    { title: 'Employees', href: '/admin/employees', icon: Users, permission: 'employees.view' },
    { title: 'Roles & Permissions', href: '/admin/roles', icon: KeyRound, permission: 'roles.view' },
    { title: 'Audit Logs', href: '/admin/audit/activity', icon: ScrollText, permission: 'audit.view' },
];

const visibleAdminItems = computed(() => adminNavItems.filter((item) => !item.permission || can(item.permission)));
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain v-if="visiblePurchaseItems.length" :items="visiblePurchaseItems" label="Purchase" />
            <NavMain v-if="visibleInventoryItems.length" :items="visibleInventoryItems" label="Inventory" />
            <NavMain v-if="visibleCrmItems.length" :items="visibleCrmItems" label="Sales &amp; CRM" />
            <NavMain v-if="visibleFinanceItems.length" :items="visibleFinanceItems" label="Finance" />
            <NavMain v-if="visibleDeliveryItems.length" :items="visibleDeliveryItems" label="Delivery &amp; RTO" />
            <NavMain v-if="visibleInsightsItems.length" :items="visibleInsightsItems" label="Insights" />
            <NavMain v-if="visibleAdminItems.length" :items="visibleAdminItems" label="Administration" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
