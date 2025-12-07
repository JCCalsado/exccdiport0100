<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { ArrowLeft, Plus, Download, FileText } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// ✅ FIX 1: Define proper TypeScript interfaces
interface Subject {
    id?: number;
    code: string;
    title: string;
    lec_units: number;
    lab_units: number;
    total_units: number;
    has_lab: boolean;
    tuition: number;
    lab_fee: number;
    total: number;
    time?: string;
    day?: string;
}

interface FeeBreakdownItem {
    name: string;
    category?: string;
    amount: number;
}

interface PaymentTerms {
    upon_registration: number;
    prelim: number;
    midterm: number;
    semi_final: number;
    final: number;
}

interface Curriculum {
    id: number;
    program: {
        name: string;
        major?: string;
    };
}

interface Assessment {
    id: number;
    assessment_number: string;
    year_level: string;
    semester: string;
    school_year: string;
    tuition_fee: number;
    other_fees: number;
    registration_fee: number;
    lab_fee?: number;
    misc_fee?: number;
    total_assessment: number;
    subjects: Subject[];
    fee_breakdown: FeeBreakdownItem[];
    payment_terms?: PaymentTerms;
    status: string;
    curriculum?: Curriculum;
}

interface Account {
    id: number;
    balance: number;
    created_at?: string;
    updated_at?: string;
}

interface Student {
    id: number;
    account_id: string;
    student_id: string;
    name: string;
    email: string;
    course: string;
    year_level: string;
    status: string;
    birthday?: string;
    phone?: string;
    address?: string;
    account?: Account;
}

interface Transaction {
    id: number;
    reference: string;
    kind: 'charge' | 'payment';
    type: string;
    amount: number;
    status: string;
    payment_channel?: string;
    paid_at?: string;
    created_at: string;
    meta?: Record<string, any>;
    fee?: {
        id: number;
        name: string;
        category: string;
    };
}

interface Payment {
    id: number;
    amount: number;
    description: string;
    payment_method: string;
    reference_number: string;
    status: string;
    paid_at: string;
    created_at: string;
}

interface PaymentTerm {
    id: number;
    term_name: string;
    term_order: number;
    amount: number;
    paid_amount: number;
    remaining_balance: number;
    due_date?: string;
    status: 'pending' | 'paid' | 'partial';
    is_overdue?: boolean;
}

interface FeeBreakdown {
    category: string;
    total: number;
    items: number;
}

// ✅ FIX 2: Define Props interface with all required fields
interface Props {
    student: Student;
    assessment: Assessment | null;
    transactions: Transaction[];
    payments: Payment[];
    feeBreakdown: FeeBreakdown[];
    paymentTerms?: PaymentTerm[]; // Optional since not all students have payment terms
    paymentTermsStats?: {
        total_scheduled: number;
        total_paid: number;
        remaining_due: number;
    };
    account_id?: string; // Primary identifier from backend
}

const props = defineProps<Props>();

// ✅ FIX 3: Add null safety for balance calculation
const remainingBalance = computed(() => {
    if (!props.student?.account?.balance) return 0;
    return Math.abs(props.student.account.balance);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student?.name || 'Student' },
];

const showPaymentDialog = ref(false);

// ✅ FIX 4: Initialize payment form with proper defaults
const paymentForm = useForm({
    amount: '',
    payment_method: 'cash' as 'cash' | 'gcash' | 'bank_transfer' | 'credit_card' | 'debit_card',
    description: '',
    payment_date: new Date().toISOString().split('T')[0],
    term_id: null as number | null,
});

// ✅ FIX 5: Add null safety for subject calculations
const totalUnits = computed(() => {
    if (!props.assessment?.subjects || !Array.isArray(props.assessment.subjects)) {
        return { lec: 0, lab: 0, total: 0 };
    }
    
    return props.assessment.subjects.reduce((acc, subject) => ({
        lec: acc.lec + (Number(subject.lec_units) || 0),
        lab: acc.lab + (Number(subject.lab_units) || 0),
        total: acc.total + (Number(subject.total_units) || 0),
    }), { lec: 0, lab: 0, total: 0 });
});

// ✅ FIX 6: Improve OBE assessment detection with null safety
const isOBEAssessment = computed(() => {
    return !!(props.assessment?.curriculum);
});

// ✅ FIX 7: Add null safety for program name
const programName = computed(() => {
    if (isOBEAssessment.value && props.assessment?.curriculum) {
        const { name, major } = props.assessment.curriculum.program;
        return major ? `${name} - Major: ${major}` : name;
    }
    return props.student?.course || 'N/A';
});

// ✅ FIX 8: Add proper error handling for payment submission
const submitPayment = () => {
    if (!props.student?.id) {
        console.error('Student ID not found');
        return;
    }

    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.clearErrors();
        },
        onError: (errors) => {
            console.error('Payment submission errors:', errors);
        }
    });
};

// ✅ FIX 9: Add proper number formatting with null safety
const formatCurrency = (amount: number | string | undefined | null): string => {
    const numericAmount = Number(amount) || 0;
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(numericAmount);
};

// ✅ FIX 10: Add safe date formatting
const formatDate = (date: string | undefined | null): string => {
    if (!date) return 'N/A';
    
    try {
        return new Date(date).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch (error) {
        console.error('Date formatting error:', error);
        return 'Invalid Date';
    }
};
</script>

<template>
    <Head :title="`Fee Details - ${student?.name || 'Student'}`" />

    <AppLayout>
        <div class="space-y-6 max-w-6xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Student Fee Details</h1>
                        <p class="text-gray-600 mt-2">
                            {{ student?.name || 'N/A' }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <!-- ✅ FIX 11: Add null safety for export link -->
                    <Link 
                        v-if="student?.id" 
                        :href="route('student-fees.export-pdf', student.id)" 
                        target="_blank"
                    >
                        <Button variant="outline">
                            <Download class="w-4 h-4 mr-2" />
                            Export PDF
                        </Button>
                    </Link>
                    <Dialog v-model:open="showPaymentDialog">
                        <DialogTrigger as-child>
                            <Button>
                                <Plus class="w-4 h-4 mr-2" />
                                Record Payment
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Record New Payment</DialogTitle>
                                <DialogDescription>
                                    Add a payment for {{ student?.name || 'this student' }}
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitPayment" class="space-y-4">
                                <div class="space-y-2">
                                    <Label for="amount">Amount *</Label>
                                    <Input
                                        id="amount"
                                        v-model="paymentForm.amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        required
                                        placeholder="0.00"
                                    />
                                    <p v-if="paymentForm.errors.amount" class="text-sm text-red-500">
                                        {{ paymentForm.errors.amount }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_method">Payment Method *</Label>
                                    <select
                                        id="payment_method"
                                        v-model="paymentForm.payment_method"
                                        required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option value="cash">Cash</option>
                                        <option value="gcash">GCash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="credit_card">Credit Card</option>
                                        <option value="debit_card">Debit Card</option>
                                    </select>
                                    <p v-if="paymentForm.errors.payment_method" class="text-sm text-red-500">
                                        {{ paymentForm.errors.payment_method }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="payment_date">Payment Date *</Label>
                                    <Input
                                        id="payment_date"
                                        v-model="paymentForm.payment_date"
                                        type="date"
                                        required
                                    />
                                    <p v-if="paymentForm.errors.payment_date" class="text-sm text-red-500">
                                        {{ paymentForm.errors.payment_date }}
                                    </p>
                                </div>

                                <!-- ✅ FIX 12: Add conditional rendering for payment terms -->
                                <div v-if="paymentTerms && paymentTerms.length > 0" class="space-y-2">
                                    <Label for="term_id">Apply to Payment Term (Optional)</Label>
                                    <select
                                        id="term_id"
                                        v-model="paymentForm.term_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option :value="null">Apply to earliest unpaid term</option>
                                        <option
                                            v-for="term in paymentTerms"
                                            :key="term.id"
                                            :value="term.id"
                                            :disabled="term.status === 'paid'"
                                        >
                                            {{ term.term_name }} - {{ formatCurrency(term.remaining_balance) }} remaining
                                        </option>
                                    </select>
                                    <p class="text-xs text-gray-500">
                                        Leave blank to automatically apply to earliest unpaid term(s)
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <Label for="description">Description</Label>
                                    <Input
                                        id="description"
                                        v-model="paymentForm.description"
                                        placeholder="e.g., Prelim, Midterm, Full Payment"
                                    />
                                    <p v-if="paymentForm.errors.description" class="text-sm text-red-500">
                                        {{ paymentForm.errors.description }}
                                    </p>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="showPaymentDialog = false"
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" :disabled="paymentForm.processing">
                                        {{ paymentForm.processing ? 'Recording...' : 'Record Payment' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </div>

            <!-- OBE Curriculum Badge -->
            <div v-if="isOBEAssessment" class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-lg p-4 text-white">
                <div class="flex items-center gap-3">
                    <FileText class="w-6 h-6" />
                    <div>
                        <p class="font-semibold">OBE Curriculum Assessment</p>
                        <p class="text-sm text-blue-100">Outcome-Based Education System</p>
                    </div>
                </div>
            </div>

            <!-- ✅ FIX 13: Student Information with null safety -->
            <Card v-if="student">
                <CardHeader>
                    <CardTitle>Personal Information</CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <Label class="text-sm text-gray-600">Full Name</Label>
                            <p class="font-medium">{{ student.name }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Email</Label>
                            <p class="font-medium">{{ student.email }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Birthday</Label>
                            <p class="font-medium">{{ student.birthday ? formatDate(student.birthday) : 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Phone</Label>
                            <p class="font-medium">{{ student.phone || 'N/A' }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Student ID</Label>
                            <p class="font-medium">{{ student.student_id }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Program/Course</Label>
                            <p class="font-medium">{{ programName }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Year Level</Label>
                            <p class="font-medium">{{ student.year_level }}</p>
                        </div>
                        <div>
                            <Label class="text-sm text-gray-600">Status</Label>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                {{ student.status }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ✅ FIX 14: Certificate of Matriculation with comprehensive null checks -->
            <Card v-if="assessment">
                <CardHeader>
                    <div class="flex justify-between items-start">
                        <div>
                            <CardTitle class="text-2xl font-bold uppercase tracking-wide text-blue-900">
                                Certificate of Matriculation
                            </CardTitle>
                            <CardDescription class="text-base mt-2">
                                Assessment No: <span class="font-semibold text-gray-900">{{ assessment.assessment_number }}</span>
                            </CardDescription>
                        </div>
                        <div class="text-right bg-blue-50 px-4 py-2 rounded-lg border border-blue-200">
                            <p class="text-sm font-semibold text-gray-700">{{ assessment.semester }} - {{ assessment.school_year }}</p>
                            <p class="text-base font-bold text-blue-700">{{ assessment.year_level }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-8">
                    <!-- ✅ FIX 15: Subjects Table with proper array checks -->
                    <div v-if="assessment.subjects && Array.isArray(assessment.subjects) && assessment.subjects.length > 0" class="space-y-4">
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-bold text-white uppercase tracking-wider">Enrolled Subjects</h3>
                        </div>
                        <div class="border-2 border-gray-300 rounded-b-lg overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200">
                                        <tr>
                                            <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider border-r border-gray-300">
                                                Subject Code
                                            </th>
                                            <th class="px-6 py-4 text-left text-xs font-black text-gray-700 uppercase tracking-wider border-r border-gray-300">
                                                Description
                                            </th>
                                            <th class="px-6 py-4 text-center text-xs font-black text-gray-700 uppercase tracking-wider border-r border-gray-300">
                                                UNIT(S)
                                            </th>
                                            <th class="px-6 py-4 text-center text-xs font-black text-gray-700 uppercase tracking-wider border-r border-gray-300">
                                                Time
                                            </th>
                                            <th class="px-6 py-4 text-center text-xs font-black text-gray-700 uppercase tracking-wider">
                                                Day
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr
                                            v-for="subject in assessment.subjects"
                                            :key="subject.id || subject.code"
                                            class="hover:bg-blue-50 transition-colors duration-150"
                                        >
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 border-r border-gray-200">
                                                {{ subject.code }}
                                            </td>
                                            <td class="px-6 py-4 text-sm font-medium text-gray-800 border-r border-gray-200">
                                                {{ subject.title }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-base font-black text-gray-900 border-r border-gray-200">
                                                {{ subject.total_units }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700 border-r border-gray-200">
                                                {{ subject.time || '08:00 AM - 10:00 AM' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700">
                                                {{ subject.day || 'MTWTHF' }}
                                            </td>
                                        </tr>
                                        <!-- Total Row -->
                                        <tr class="bg-gradient-to-r from-blue-100 to-indigo-100 border-t-4 border-blue-600">
                                            <td colspan="2" class="px-6 py-4 text-right text-base font-black text-gray-900 uppercase tracking-wide border-r border-gray-300">
                                                TOTAL:
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center text-xl font-black text-blue-700 border-r border-gray-300">
                                                {{ totalUnits.total }}
                                            </td>
                                            <td colspan="2" class="px-6 py-4"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ FIX 16: Fees Breakdown with null safety -->
                    <div class="space-y-4">
                        <div class="bg-gradient-to-r from-green-600 to-teal-600 px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-bold text-white uppercase tracking-wider">FEES</h3>
                        </div>
                        <div class="border-2 border-gray-300 rounded-b-lg overflow-hidden shadow-sm">
                            <div class="p-6 space-y-3 bg-gradient-to-br from-gray-50 to-white">
                                <div class="flex justify-between py-3 border-b-2 border-gray-200 hover:bg-gray-100 px-3 rounded transition-colors">
                                    <span class="text-base font-semibold text-gray-800">Registration Fee:</span>
                                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(assessment.registration_fee || 0) }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-b-2 border-gray-200 hover:bg-gray-100 px-3 rounded transition-colors">
                                    <span class="text-base font-semibold text-gray-800">Tuition Fee:</span>
                                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(assessment.tuition_fee) }}</span>
                                </div>
                                <div class="flex justify-between py-3 border-b-2 border-gray-200 hover:bg-gray-100 px-3 rounded transition-colors">
                                    <span class="text-base font-semibold text-gray-800">Lab. Fee:</span>
                                    <span class="text-base font-bold text-gray-900">
                                        {{ assessment.lab_fee ? formatCurrency(assessment.lab_fee) : '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-3 border-b-2 border-gray-200 hover:bg-gray-100 px-3 rounded transition-colors">
                                    <span class="text-base font-semibold text-gray-800">Misc. Fee:</span>
                                    <span class="text-base font-bold text-gray-900">
                                        {{ assessment.misc_fee ? formatCurrency(assessment.misc_fee) : '-' }}
                                    </span>
                                </div>
                                <div class="flex justify-between py-5 font-black text-xl border-t-4 border-blue-600 mt-4 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 rounded-lg">
                                    <span class="text-gray-900 uppercase tracking-wide">Total Assessment Fee:</span>
                                    <span class="text-blue-700">{{ formatCurrency(assessment.total_assessment) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ FIX 17: Terms of Payment with null safety -->
                    <div v-if="assessment.payment_terms" class="space-y-4">
                        <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-4 py-3 rounded-t-lg">
                            <h3 class="text-lg font-bold text-white uppercase tracking-wider">TERMS OF PAYMENT</h3>
                        </div>
                        <div class="border-2 border-gray-300 rounded-b-lg overflow-hidden shadow-sm">
                            <div class="p-6 bg-gradient-to-br from-purple-50 to-pink-50">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex justify-between items-center py-4 px-5 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                                        <span class="text-sm font-bold text-gray-700 uppercase">Upon Registration</span>
                                        <span class="text-lg font-black text-gray-900">{{ formatCurrency(assessment.payment_terms.upon_registration) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-4 px-5 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                                        <span class="text-sm font-bold text-gray-700 uppercase">Prelim</span>
                                        <span class="text-lg font-black text-gray-900">{{ formatCurrency(assessment.payment_terms.prelim) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-4 px-5 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                                        <span class="text-sm font-bold text-gray-700 uppercase">Midterm</span>
                                        <span class="text-lg font-black text-gray-900">{{ formatCurrency(assessment.payment_terms.midterm) }}</span>
                                    </div>
                                    <div class="flex justify-between items-center py-4 px-5 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow border border-gray-200">
                                        <span class="text-sm font-bold text-gray-700 uppercase">Semi-Final</span>
                                        <span class="text-lg font-black text-gray-900">{{ formatCurrency(assessment.payment_terms.semi_final) }}</span>
                                    </div>
                                    <div class="md:col-span-2 flex justify-between items-center py-4 px-5 bg-gradient-to-r from-purple-100 to-pink-100 rounded-lg shadow-sm hover:shadow-md transition-shadow border-2 border-purple-300">
                                        <span class="text-base font-black text-gray-900 uppercase">Final</span>
                                        <span class="text-xl font-black text-purple-700">{{ formatCurrency(assessment.payment_terms.final) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ FIX 18: Current Balance with proper null checks -->
                    <div class="pt-6 border-t-4 border-gray-300">
                        <div class="flex justify-between items-center p-6 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl shadow-lg text-white">
                            <div>
                                <p class="text-sm font-semibold uppercase tracking-wider opacity-90">Current Balance</p>
                                <p class="text-4xl font-black mt-1">{{ formatCurrency(remainingBalance) }}</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ✅ FIX 19: Payment History with array checks -->
            <Card>
                <CardHeader>
                    <CardTitle>Payment History</CardTitle>
                    <CardDescription>All recorded payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="!payments || payments.length === 0">
                                    <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                        No payment history found
                                    </td>
                                </tr>
                                <tr v-for="payment in payments" :key="payment.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(payment.paid_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ payment.reference_number }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                            {{ payment.payment_method }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">{{ payment.description }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                                        {{ formatCurrency(payment.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- ✅ FIX 20: Transaction History with comprehensive checks -->
            <Card>
                <CardHeader>
                    <CardTitle>Transaction History</CardTitle>
                    <CardDescription>All charges and payments</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-if="!transactions || transactions.length === 0">
                                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                        No transactions found
                                    </td>
                                </tr>
                                <tr v-for="transaction in transactions" :key="transaction.id" class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ formatDate(transaction.created_at) }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">{{ transaction.reference }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span 
                                            class="px-2 py-1 text-xs rounded-full"
                                            :class="transaction.kind === 'charge' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'"
                                        >
                                            {{ transaction.kind }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">{{ transaction.type }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                            {{ transaction.status }}
                                        </span>
                                    </td>
                                    <td 
                                        class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium"
                                        :class="transaction.kind === 'charge' ? 'text-red-600' : 'text-green-600'"
                                    >
                                        {{ transaction.kind === 'charge' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>