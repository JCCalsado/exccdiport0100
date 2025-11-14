<!-- resources/js/Pages/StudentFees/Create.vue -->
<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Plus, Trash2, ArrowLeft, Search, User } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Student {
    id: number;
    student_id: string;
    name: string;
    email: string;
    course: string;
    year_level: string;
    status: string;
}

interface Subject {
    id: number;
    code: string;
    name: string;
    units: number;
    price_per_unit: number;
    has_lab: boolean;
    lab_fee: number;
    total_cost: number;
}

interface Fee {
    id: number;
    name: string;
    category: string;
    amount: number;
}

interface SelectedSubject {
    id: number;
    units: number;
    amount: number;
}

interface SelectedFee {
    id: number;
    amount: number;
}

interface Props {
    students: Student[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Create Assessment' },
];

// Step management
const currentStep = ref<1 | 2>(1);
const selectedStudent = ref<Student | null>(null);
const studentSearch = ref('');

// Available subjects and fees (loaded after student selection)
const availableSubjects = ref<Subject[]>([]);
const availableFees = ref<Fee[]>([]);
const isLoadingData = ref(false);

// Selected items
const selectedSubjects = ref<SelectedSubject[]>([]);
const selectedFees = ref<SelectedFee[]>([]);

// Filter students based on search
const filteredStudents = computed(() => {
    if (!studentSearch.value) return props.students;
    
    const search = studentSearch.value.toLowerCase();
    return props.students.filter(student => 
        student.student_id.toLowerCase().includes(search) ||
        student.name.toLowerCase().includes(search) ||
        student.email.toLowerCase().includes(search) ||
        student.course.toLowerCase().includes(search)
    );
});

// Form for assessment data
const form: any = useForm({
    user_id: null,
    year_level: '',
    semester: '',
    school_year: props.schoolYears[0] || '',
    subjects: [],
    other_fees: [],
});

// Calculate totals
const tuitionTotal = computed(() => {
    return selectedSubjects.value.reduce((sum, s) => {
        const amount = parseFloat(s.amount) || 0;
        return sum + amount;
    }, 0);
});

const otherFeesTotal = computed(() => {
    return selectedFees.value.reduce((sum, f) => {
        const amount = parseFloat(f.amount) || 0;
        return sum + amount;
    }, 0);
});

const grandTotal = computed(() => {
    return tuitionTotal.value + otherFeesTotal.value;
});

// Select student and move to next step
const selectStudent = async (student: Student) => {
    selectedStudent.value = student;
    form.user_id = student.id;
    form.year_level = student.year_level;
    
    // Load subjects and fees for this student
    await loadStudentData(student);
    
    currentStep.value = 2;
};

// Load subjects and fees based on student
const loadStudentData = async (student: Student) => {
    isLoadingData.value = true;
    
    try {
        // In a real scenario, you'd make an API call here
        // For now, we'll simulate it with a route call
        const response = await fetch(route('student-fees.create', { 
            student_id: student.id,
            get_data: true 
        }));
        
        if (response.ok) {
            const data = await response.json();
            availableSubjects.value = data.subjects || [];
            availableFees.value = data.fees || [];
        }
    } catch (error) {
        console.error('Failed to load student data:', error);
    } finally {
        isLoadingData.value = false;
    }
};

// Go back to student selection
const backToStudentSelection = () => {
    currentStep.value = 1;
    selectedStudent.value = null;
    selectedSubjects.value = [];
    selectedFees.value = [];
    form.user_id = null;
    form.year_level = '';
};

// Subject management
const addSubject = (subject: Subject) => {
    const exists = selectedSubjects.value.find(s => s.id === subject.id);
    if (!exists) {
        selectedSubjects.value.push({
            id: subject.id,
            units: subject.units,
            amount: parseFloat(String(subject.total_cost)) || 0,
        });
    }
};

const removeSubject = (subjectId: number) => {
    selectedSubjects.value = selectedSubjects.value.filter(s => s.id !== subjectId);
};

const getSubjectDetails = (subjectId: number) => {
    return availableSubjects.value.find(s => s.id === subjectId);
};

// Fee management
const addFee = (fee: Fee) => {
    const exists = selectedFees.value.find(f => f.id === fee.id);
    if (!exists) {
        selectedFees.value.push({
            id: fee.id,
            amount: parseFloat(String(fee.amount)) || 0,
        });
    }
};

const removeFee = (feeId: number) => {
    selectedFees.value = selectedFees.value.filter(f => f.id !== feeId);
};

const getFeeDetails = (feeId: number) => {
    return availableFees.value.find(f => f.id === feeId);
};

// Watch for changes to update form
watch([selectedSubjects, selectedFees], () => {
    form.subjects = selectedSubjects.value;
    form.other_fees = selectedFees.value;
}, { deep: true });

// Submit form
const submit = () => {
    form.post(route('student-fees.store'), {
        preserveScroll: true,
    });
};

// Format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

// Get status color
const getStatusColor = (status: string) => {
    switch (status) {
        case 'active':
            return 'bg-green-100 text-green-800';
        case 'graduated':
            return 'bg-blue-100 text-blue-800';
        case 'dropped':
            return 'bg-red-100 text-red-800';
        default:
            return 'bg-gray-100 text-gray-800';
    }
};
</script>

<template>
    <Head title="Create Student Assessment" />

    <AppLayout>
        <div class="space-y-6 max-w-7xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center gap-4">
                <Link :href="route('student-fees.index')">
                    <Button variant="outline" size="sm" class="flex items-center gap-2">
                        <ArrowLeft class="w-4 h-4" />
                        Back
                    </Button>
                </Link>
                <div>
                    <h1 class="text-3xl font-bold">Create Student Assessment</h1>
                    <p class="text-gray-600 mt-2">
                        {{ currentStep === 1 ? 'Step 1: Select Student' : 'Step 2: Create Assessment' }}
                    </p>
                </div>
            </div>

            <!-- Step Indicator -->
            <div class="flex items-center justify-center gap-4">
                <div class="flex items-center gap-2">
                    <div :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center font-semibold',
                        currentStep >= 1 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'
                    ]">
                        1
                    </div>
                    <span class="font-medium">Select Student</span>
                </div>
                <div class="w-24 h-1 bg-gray-200">
                    <div :class="[
                        'h-full transition-all duration-300',
                        currentStep >= 2 ? 'bg-blue-600 w-full' : 'w-0'
                    ]"></div>
                </div>
                <div class="flex items-center gap-2">
                    <div :class="[
                        'w-10 h-10 rounded-full flex items-center justify-center font-semibold',
                        currentStep >= 2 ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600'
                    ]">
                        2
                    </div>
                    <span class="font-medium">Create Assessment</span>
                </div>
            </div>

            <!-- STEP 1: Student Selection -->
            <div v-if="currentStep === 1" class="space-y-6">
                <!-- Search Bar -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                        <Input
                            v-model="studentSearch"
                            placeholder="Search by Student ID, Name, Email, or Course..."
                            class="pl-10"
                        />
                    </div>
                </div>

                <!-- Student List -->
                <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
                    <div class="p-6 border-b">
                        <h2 class="text-lg font-semibold">Select a Student</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            Choose an active student to create an assessment for
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Student ID
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Name
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Course
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Year Level
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Status
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="filteredStudents.length === 0">
                                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                        <User class="w-12 h-12 mx-auto mb-3 text-gray-300" />
                                        <p class="text-lg font-medium">No students found</p>
                                        <p class="text-sm mt-1">Try adjusting your search criteria</p>
                                    </td>
                                </tr>
                                <tr 
                                    v-for="student in filteredStudents" 
                                    :key="student.id" 
                                    class="hover:bg-gray-50 transition-colors cursor-pointer"
                                    @click="selectStudent(student)"
                                >
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ student.student_id }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>
                                            <div class="font-medium">{{ student.name }}</div>
                                            <div class="text-xs text-gray-500">{{ student.email }}</div>
                                        </div>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <Button size="sm" @click.stop="selectStudent(student)">
                                            Select
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Assessment Form -->
            <form v-if="currentStep === 2" @submit.prevent="submit" class="space-y-6">
                <!-- Selected Student Info -->
                <div class="bg-blue-50 rounded-lg border-2 border-blue-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">Selected Student</h3>
                            <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Student ID:</span>
                                    <p class="font-medium">{{ selectedStudent?.student_id }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Name:</span>
                                    <p class="font-medium">{{ selectedStudent?.name }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Course:</span>
                                    <p class="font-medium">{{ selectedStudent?.course }}</p>
                                </div>
                                <div>
                                    <span class="text-gray-600">Year Level:</span>
                                    <p class="font-medium">{{ selectedStudent?.year_level }}</p>
                                </div>
                            </div>
                        </div>
                        <Button 
                            type="button" 
                            variant="outline" 
                            size="sm"
                            @click="backToStudentSelection"
                        >
                            Change Student
                        </Button>
                    </div>
                </div>

                <!-- Term Information -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Term Information</h2>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="space-y-2">
                            <Label for="year_level">Year Level</Label>
                            <select
                                id="year_level"
                                v-model="form.year_level"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select year level</option>
                                <option
                                    v-for="year in yearLevels"
                                    :key="year"
                                    :value="year"
                                >
                                    {{ year }}
                                </option>
                            </select>
                            <p v-if="form.errors?.year_level" class="text-sm text-red-500">
                                {{ form.errors.year_level }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select semester</option>
                                <option
                                    v-for="sem in semesters"
                                    :key="sem"
                                    :value="sem"
                                >
                                    {{ sem }}
                                </option>
                            </select>
                            <p v-if="form.errors?.semester" class="text-sm text-red-500">
                                {{ form.errors.semester }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="school_year">School Year</Label>
                            <select
                                id="school_year"
                                v-model="form.school_year"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select school year</option>
                                <option
                                    v-for="sy in schoolYears"
                                    :key="sy"
                                    :value="sy"
                                >
                                    {{ sy }}
                                </option>
                            </select>
                            <p v-if="form.errors?.school_year" class="text-sm text-red-500">
                                {{ form.errors.school_year }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Subjects Section -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Subjects</h2>
                    
                    <!-- Available Subjects -->
                    <div class="space-y-2 mb-4">
                        <Label>Available Subjects</Label>
                        <div v-if="isLoadingData" class="text-center py-8">
                            <p class="text-gray-500">Loading subjects...</p>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                            <div
                                v-for="subject in availableSubjects"
                                :key="subject.id"
                                class="flex items-center justify-between p-3 hover:bg-gray-50 rounded cursor-pointer border"
                                @click="addSubject(subject)"
                            >
                                <div>
                                    <p class="font-medium">{{ subject.code }} - {{ subject.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ subject.units }} units × {{ formatCurrency(subject.price_per_unit) }}
                                        <span v-if="subject.has_lab">+ Lab Fee {{ formatCurrency(subject.lab_fee) }}</span>
                                    </p>
                                </div>
                                <div class="font-medium text-blue-600">
                                    {{ formatCurrency(subject.total_cost) }}
                                </div>
                            </div>
                            <div v-if="availableSubjects.length === 0 && !isLoadingData" class="text-center py-4 text-gray-500">
                                No subjects available for this student
                            </div>
                        </div>
                    </div>

                    <!-- Selected Subjects -->
                    <div class="space-y-2">
                        <Label>Selected Subjects</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedSubjects"
                                :key="selected.id"
                                class="flex items-center justify-between p-3 border rounded-lg bg-gray-50"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">
                                        {{ getSubjectDetails(selected.id)?.code }} - 
                                        {{ getSubjectDetails(selected.id)?.name }}
                                    </p>
                                    <p class="text-sm text-gray-600">
                                        {{ selected.units }} units
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="text-red-500 hover:text-red-700"
                                        @click="removeSubject(selected.id)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedSubjects.length === 0" class="text-center py-8 text-gray-500 border rounded-lg bg-gray-50">
                                No subjects selected. Click on subjects above to add them.
                            </div>
                        </div>
                        <p v-if="form.errors?.subjects" class="text-sm text-red-500">
                            {{ form.errors.subjects }}
                        </p>
                    </div>

                    <!-- Tuition Total -->
                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg mt-4 border border-blue-200">
                        <span class="font-medium text-lg">Total Tuition Fee</span>
                        <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(tuitionTotal) }}</span>
                    </div>
                </div>

                <!-- Other Fees Section -->
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold mb-4">Other Fees</h2>
                    
                    <!-- Available Fees -->
                    <div class="space-y-2 mb-4">
                        <Label>Available Fees</Label>
                        <div v-if="isLoadingData" class="text-center py-8">
                            <p class="text-gray-500">Loading fees...</p>
                        </div>
                        <div v-else class="grid grid-cols-1 gap-2 max-h-48 overflow-y-auto border rounded-lg p-2">
                            <div
                                v-for="fee in availableFees"
                                :key="fee.id"
                                class="flex items-center justify-between p-3 hover:bg-gray-50 rounded cursor-pointer border"
                                @click="addFee(fee)"
                            >
                                <div>
                                    <p class="font-medium">{{ fee.name }}</p>
                                    <p class="text-sm text-gray-600">{{ fee.category }}</p>
                                </div>
                                <div class="font-medium text-blue-600">
                                    {{ formatCurrency(fee.amount) }}
                                </div>
                            </div>
                            <div v-if="availableFees.length === 0 && !isLoadingData" class="text-center py-4 text-gray-500">
                                No fees available
                            </div>
                        </div>
                    </div>

                    <!-- Selected Fees -->
                    <div class="space-y-2">
                        <Label>Selected Fees</Label>
                        <div class="space-y-2">
                            <div
                                v-for="selected in selectedFees"
                                :key="selected.id"
                                class="flex items-center justify-between p-3 border rounded-lg bg-gray-50"
                            >
                                <div class="flex-1">
                                    <p class="font-medium">{{ getFeeDetails(selected.id)?.name }}</p>
                                    <p class="text-sm text-gray-600">
                                        {{ getFeeDetails(selected.id)?.category }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="font-medium">{{ formatCurrency(selected.amount) }}</span>
                                    <button
                                        type="button"
                                        class="text-red-500 hover:text-red-700"
                                        @click="removeFee(selected.id)"
                                    >
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                            <div v-if="selectedFees.length === 0" class="text-center py-8 text-gray-500 border rounded-lg bg-gray-50">
                                No fees selected. Click on fees above to add them.
                            </div>
                        </div>
                    </div>

                    <!-- Other Fees Total -->
                    <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg mt-4 border border-blue-200">
                        <span class="font-medium text-lg">Total Other Fees</span>
                        <span class="text-2xl font-bold text-blue-600">{{ formatCurrency(otherFeesTotal) }}</span>
                    </div>
                </div>

                <!-- Grand Total -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-lg p-6 text-white shadow-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-blue-100 text-sm uppercase tracking-wide mb-1">Total Assessment Fee Amount</p>
                            <p class="text-4xl font-bold">{{ formatCurrency(grandTotal) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-blue-100 text-sm">Tuition: {{ formatCurrency(tuitionTotal) }}</p>
                            <p class="text-blue-100 text-sm">Other Fees: {{ formatCurrency(otherFeesTotal) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-between gap-4 pt-4">
                    <Button 
                        type="button" 
                        variant="outline"
                        @click="backToStudentSelection"
                    >
                        Back to Student Selection
                    </Button>
                    <div class="flex gap-4">
                        <Link :href="route('student-fees.index')">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button 
                            type="submit" 
                            :disabled="form.processing || !form.user_id || selectedSubjects.length === 0"
                            class="min-w-[200px]"
                        >
                            {{ form.processing ? 'Creating...' : 'Create Assessment' }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>