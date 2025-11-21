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


interface Subject {
    id: number;
    code: string;
    title: string;
    lec_units: number;
    lab_units: number;
    total_units: number;
    has_lab: boolean;
    tuition: number;
    lab_fee: number;
    total: number;
}

interface FeeBreakdownItem {
    name: string;
    amount: number;
}

interface PaymentTerms {
    upon_registration: number;
    prelim: number;
    midterm: number;
    semi_final: number;
    final: number;
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
    total_assessment: number;
    subjects: Subject[];
    fee_breakdown: FeeBreakdownItem[];
    payment_terms?: PaymentTerms;
    status: string;
    curriculum?: {
        id: number;
        program: {
            name: string;
            major: string;
        };
    };
}

interface Props {
    student: any;
    assessment: Assessment | null;
    transactions: any[];
    payments: any[];
    feeBreakdown: Array<{
        category: string;
        total: number;
        items: number;
    }>;
}

const props = defineProps<Props>();

// Calculate remaining balance
const remainingBalance = computed(() => {
    return Math.abs(props.student.account?.balance || 0);
});

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: props.student.name },
];

const showPaymentDialog = ref(false);

const paymentForm = useForm({
    amount: '',
    payment_method: 'cash',
    description: '',
    payment_date: new Date().toISOString().split('T')[0],
});

// Computed: Total units
const totalUnits = computed(() => {
    if (!props.assessment?.subjects) return { lec: 0, lab: 0, total: 0 };
    
    return props.assessment.subjects.reduce((acc, subject) => ({
        lec: acc.lec + (subject.lec_units || 0),
        lab: acc.lab + (subject.lab_units || 0),
        total: acc.total + (subject.total_units || 0),
    }), { lec: 0, lab: 0, total: 0 });
});

// Computed: Has OBE curriculum
const isOBEAssessment = computed(() => {
    return props.assessment?.curriculum !== undefined && props.assessment?.curriculum !== null;
});

// Computed: Program name
const programName = computed(() => {
    if (isOBEAssessment.value && props.assessment?.curriculum) {
        const major = props.assessment.curriculum.program.major;
        return major ? `${props.assessment.curriculum.program.name} - Major: ${major}` : props.assessment.curriculum.program.name;
    }
    return props.student.course;
});

const submitPayment = () => {
    paymentForm.post(route('student-fees.payments.store', props.student.id), {
        preserveScroll: true,
        onSuccess: () => {
            showPaymentDialog.value = false;
            paymentForm.reset();
            paymentForm.clearErrors();
        },
        onError: () => {
            // Errors will be displayed in the form
        }
    });
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};
</script>

<template>
    <Head :title="`Fee Details - ${student.name}`" />

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
                            {{ student.name }}
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('student-fees.export-pdf', student.id)" target="_blank">
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
                                    Add a payment for {{ student.name }}
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

            <!-- Student Information -->
            <Card>
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

            <!-- Certificate of Matriculation -->
            <Card v-if="assessment">
                <CardHeader>
                    <div class="flex justify-between items-start">
                        <div>
                            <CardTitle>Certificate of Matriculation</CardTitle>
                            <CardDescription>Assessment No: {{ assessment.assessment_number }}</CardDescription>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600">{{ assessment.semester }} - {{ assessment.school_year }}</p>
                            <p class="text-sm font-medium">{{ assessment.year_level }}</p>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-6">
                    <!-- Subjects Table (OBE Format) -->
                    <div v-if="assessment.subjects && assessment.subjects.length > 0">
                        <h3 class="font-semibold mb-3 text-gray-900">SUBJECTS</h3>
                        <div class="overflow-x-auto border rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Subject Code
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            UNIT(S)
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Time
                                        </th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Day
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="subject in assessment.subjects"
                                        :key="subject.id"
                                        class="hover:bg-gray-50"
                                    >
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ subject.code }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-900">
                                            {{ subject.title }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center font-medium text-gray-900">
                                            {{ subject.total_units }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            08:00 AM - 10:00 AM
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-right text-gray-900">
                                            MTWTHF
                                        </td>
                                    </tr>
                                    <!-- Total Row -->
                                    <tr class="bg-gray-100 font-semibold">
                                        <td colspan="2" class="px-4 py-3 text-sm text-right text-gray-900">
                                            TOTAL:
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            {{ totalUnits.lec }}
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center text-gray-900">
                                            
                                        </td>
                                        <td colspan="3" class="px-4 py-3 text-sm text-right"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Fees Breakdown -->
                    <div>
                        <h3 class="font-semibold mb-3 text-gray-900">FEES</h3>
                        <div class="space-y-2 bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-gray-700">Registration Fee:</span>
                                <span class="font-medium">{{ formatCurrency(assessment.registration_fee || 0) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-gray-700">Tuition Fee:</span>
                                <span class="font-medium">{{ formatCurrency(assessment.tuition_fee) }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-gray-700">Lab. Fee:</span>
                                <span class="font-medium"> </span>
                            </div>
                            <div class="flex justify-between py-2 border-b">
                                <span class="text-gray-700">Misc. Fee:</span>
                                <span class="font-medium"> </span>
                            </div>
                            <div class="flex justify-between py-3 font-bold text-lg border-t-2 border-gray-300 mt-2">
                                <span class="text-gray-900">Total Assessment Fee:</span>
                                <span class="text-blue-600">{{ formatCurrency(assessment.total_assessment) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Terms of Payment -->
                    <div v-if="assessment.payment_terms">
                        <h3 class="font-semibold mb-3 text-gray-900">TERMS OF PAYMENT</h3>
                        <div class="space-y-2 bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between py-2">
                                <span class="text-gray-700">Upon Registration</span>
                                <span class="font-medium">{{ formatCurrency(assessment.payment_terms.upon_registration) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-700">Prelim</span>
                                <span class="font-medium">{{ formatCurrency(assessment.payment_terms.prelim) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-700">Midterm</span>
                                <span class="font-medium">{{ formatCurrency(assessment.payment_terms.midterm) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-700">Semi-Final</span>
                                <span class="font-medium">{{ formatCurrency(assessment.payment_terms.semi_final) }}</span>
                            </div>
                            <div class="flex justify-between py-2">
                                <span class="text-gray-700">Final</span>
                                <span class="font-medium">{{ formatCurrency(assessment.payment_terms.final) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Current Balance -->
                    <div class="pt-4 border-t-2">
                        <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <span class="font-medium text-lg">Current Balance</span>
                            <span
                                class="text-2xl font-bold"
                                :class="(student.account?.balance || 0) > 0 ? 'text-red-600' : 'text-green-600'"
                            >
                                {{ formatCurrency(Math.abs(student.account?.balance || 0)) }}
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Payment History -->
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
                                <tr v-if="payments.length === 0">
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

            <!-- Transaction History -->
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
                                <tr v-if="transactions.length === 0">
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