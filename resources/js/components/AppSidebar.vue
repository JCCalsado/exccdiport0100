<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, CreditCard, History, Users, Receipt, Banknote, GraduationCap, User } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { computed } from 'vue';
import { StudentUser } from '@/types/user';

// Access the authenticated user's role from Inertia props
const page = usePage();
const userRole = computed(() => page.props.auth?.user?.role || 'student');

// Define all possible navigation items with required roles
const allNavItems: NavItem[] = [
    {
        title: 'Student Dashboard',
        href: route('student.dashboard'),
        icon: GraduationCap,
        roles: ['student'], // Student-specific dashboard
    },
    {
        title: 'My Account',
        href: route('student.account'),
        icon: CreditCard,
        roles: ['student'], // Only students
    },
    {
        title: 'Transaction History',
        href: route('transactions.index'),
        icon: History,
        roles: ['student'], // Only students
    },
    {
        title: 'Accounting Dashboard',
        href: route('accounting.dashboard'),
        icon: Banknote,
        roles: ['accounting'], // Accounting and above
    },
    {
        title: 'Fee Management',
        href: route('fees.index'),
        icon: Receipt,
        roles: ['accounting', 'admin'],
    },
    {
        title: 'Subject Management',
        href: route('subjects.index'),
        icon: BookOpen,
        roles: ['accounting', 'admin'],
    },
    {
        title: 'User Management',
        href: route('users.index'),
        icon: Users,
        roles: ['admin'], // Only super admin
    },
    {
        title: 'Archives',
        href: route('students.index'), // Make sure this route exists
        icon: GraduationCap, // Using the graduation cap icon
        roles: ['admin', 'accounting'], // Only for staff
    },
    {
        title: 'My Profile',
        href: route('student.profile'),
        icon: User,
        roles: ['student'],
    },
    {
        title: 'Student Fee Management',
        href: route('student-fees.index'),
        icon: Receipt, // Make sure to import Receipt from lucide-vue-next
        roles: ['accounting', 'admin'],
    },
    {
        title: 'Curriculum Management',
        href: route('curricula.index'),
        icon: BookOpen, // Import from lucide-vue-next
        roles: ['admin', 'accounting'],
    },
];

// Filter navigation items based on the user's role
const mainNavItems = computed(() => {
  return allNavItems.filter((item) => {
    if (!item.roles) return true;
    return item.roles.includes(userRole.value);
  });
});

const footerNavItems: NavItem[] = [
  
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>