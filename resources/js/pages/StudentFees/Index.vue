<!-- resources/js/pages/StudentFees/Index.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Search, Plus, Eye, Edit, UserPlus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Student {
    id: number;
    student_id: string;
    name: string;
    course: string;
    year_level: string;
    status: string;
    account: {
        balance: number;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    students: {
        data: Student[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search?: string;
        course?: string;
        year_level?: string;
        status?: string;
    };
    courses: string[];
    yearLevels: string[];
    statuses: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management' },
];

const search = ref(props.filters.search || '');
const selectedCourse = ref(props.filters.course || '');
const selectedYearLevel = ref(props.filters.year_level || '');
const selectedStatus = ref(props.filters.status || '');

let searchTimeout: ReturnType<typeof setTimeout>;
const performSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            route('student-fees.index'),
            {
                search: search.value,
                course: selectedCourse.value,
                year_level: selectedYearLevel.value,
                status: selectedStatus.value,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    }, 300);
};

watch([search, selectedCourse, selectedYearLevel, selectedStatus], () => {
    performSearch();
});

const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-500 text-white';
        case 'graduated':
            return 'bg-blue-500 text-white';
        case 'dropped':
            return 'bg-red-500 text-white';
        default:
            return 'bg-gray-500 text-white';
    }
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};
</script>

<template>
    <Head title="Student Fee Management" />

    <AppLayout>
        <div class="space-y-6 p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold">Student Fee Management</h1>
                    <p class="text-gray-600 mt-2">
                        Manage student assessments and fees
                    </p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.create-student')">
                        <Button variant="outline" class="flex items-center gap-2">
                            <UserPlus class="w-4 h-4" />
                            Add Student
                        </Button>
                    </Link>
                    <Link :href="route('student-fees.create')">
                        <Button class="flex items-center gap-2">
                            <Plus class="w-4 h-4" />
                            Create Assessment
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg border shadow-sm space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <Input
                            v-model="search"
                            placeholder="Search by ID or name..."
                            class="pl-10"
                        />
                    </div>

                    <!-- Course Filter -->
                    <select
                        v-model="selectedCourse"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Courses</option>
                        <option
                            v-for="course in courses"
                            :key="course"
                            :value="course"
                        >
                            {{ course }}
                        </option>
                    </select>

                    <!-- Year Level Filter -->
                    <select
                        v-model="selectedYearLevel"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Year Levels</option>
                        <option
                            v-for="year in yearLevels"
                            :key="year"
                            :value="year"
                        >
                            {{ year }}
                        </option>
                    </select>

                    <!-- Status Filter -->
                    <select
                        v-model="selectedStatus"
                        class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                        <option value="">All Statuses</option>
                        <option
                            v-for="(label, value) in statuses"
                            :key="value"
                            :value="value"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Student ID
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Name
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Course
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Year Level
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Status
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Balance
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-if="students.data.length === 0">
                                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                    No students found
                                </td>
                            </tr>
                            <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ student.student_id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ student.name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ student.course }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ student.year_level }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span 
                                        class="px-2 py-1 text-xs font-semibold rounded-full"
                                        :class="getStatusColor(student.status)"
                                    >
                                        {{ student.status }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium">
                                    <span :class="(student.account?.balance ?? 0) > 0 ? 'text-red-500' : 'text-green-500'">
                                        {{ formatCurrency(Math.abs(student.account?.balance ?? 0)) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('student-fees.show', student.id)">
                                            <button 
                                                class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                                                title="View Details"
                                            >
                                                <Eye class="w-4 h-4" />
                                            </button>
                                        </Link>
                                        <Link :href="route('student-fees.edit', student.id)">
                                            <button 
                                                class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50"
                                                title="Edit Assessment"
                                            >
                                                <Edit class="w-4 h-4" />
                                            </button>
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Fixed Pagination with null check -->
                <div 
                    v-if="students.last_page > 1" 
                    class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50"
                >
                    <div class="text-sm text-gray-600">
                        Page {{ students.current_page }} of {{ students.last_page }}
                    </div>
                    <div class="flex gap-2">
                        <template v-for="(link, index) in students.links" :key="index">
                            <!-- Render as Link if URL exists -->
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                :class="[
                                    'px-3 py-1 rounded border text-sm transition-colors',
                                    link.active 
                                        ? 'bg-blue-600 text-white border-blue-600' 
                                        : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                ]"
                                v-html="link.label"
                            />
                            <!-- Render as disabled span if URL is null -->
                            <span
                                v-else
                                :class="[
                                    'px-3 py-1 rounded border text-sm',
                                    'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed opacity-60'
                                ]"
                                v-html="link.label"
                            />
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>