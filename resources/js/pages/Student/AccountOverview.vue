<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import TransactionDetailsDialog from '@/components/TransactionDetailsDialog.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useFormatters } from '@/composables/useFormatters'
import type { PaymentMethod } from '@/types/transaction'
import {
  CreditCard,
  CheckCircle,
  AlertCircle,
  Clock,
  Receipt,
  History,
  DollarSign,
} from 'lucide-vue-next'

interface SubjectLine {
  subject_code?: string
  code?: string
  description?: string
  title?: string
  name?: string
  units?: number
  total_units?: number
  lec_units?: number
  lab_units?: number
  tuition?: number
  lab_fee?: number
  misc_fee?: number
  total?: number
  semester?: string | number
  time?: string
  day?: string
}

interface Transaction {
  id: number | string
  kind: 'payment' | 'charge'
  status?: string
  amount: number
  created_at?: string
  reference?: string
  type?: string
  meta?: any
  fee?: any
}

interface Props {
  student?: Record<string, any>
  account?: Record<string, any>
  assessment?: {
    assessment_number?: string
    school_year?: string
    semester?: string
    status?: string
    total_assessment?: number
    tuition_fee?: number
    other_fees?: number
    registration_fee?: number
    lab_fee?: number
    misc_fee?: number
    registration?: number
    upon_registration?: number
    prelim?: number
    midterm?: number
    semi_final?: number
    final?: number
    subjects?: SubjectLine[]
    total_units?: number
  }
  assessmentLines?: SubjectLine[]
  termsOfPayment?: Record<string, number> | null
  transactions?: Transaction[]
  fees?: { name: string; amount: number; category?: string }[]
  currentTerm?: { year: number; semester: string }
  tab?: 'fees' | 'history' | 'payment'
}

const props = withDefaults(defineProps<Props>(), {
  student: () => ({}),
  account: () => ({}),
  assessment: () => ({}),
  assessmentLines: () => ([]),
  termsOfPayment: null,
  transactions: () => ([]),
  fees: () => ([]),
  currentTerm: () => ({ year: new Date().getFullYear(), semester: '1st Sem' }),
  tab: 'fees',
})

const { formatCurrency, formatDate, formatPercentage } = useFormatters()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'My Account' },
]

const getTabFromUrl = (): 'fees' | 'history' | 'payment' => {
  const urlParams = new URLSearchParams(window.location.search)
  const tab = urlParams.get('tab')
  return (tab === 'payment' || tab === 'history') ? tab as any : 'fees'
}
const activeTab = ref<'fees' | 'history' | 'payment'>(props.tab || getTabFromUrl())

const showDetailsDialog = ref(false)
const selectedTransaction = ref<Transaction | null>(null)

const paymentForm = useForm({
  amount: 0,
  payment_method: 'cash' as PaymentMethod,
  reference_number: '',
  paid_at: new Date().toISOString().split('T')[0],
  description: 'Payment for fees',
})

const latestAssessment = computed(() => props.assessment || {})

const totalAssessmentFee = computed(() => {
  if (typeof latestAssessment.value.total_assessment === 'number') {
    return latestAssessment.value.total_assessment
  }
  const tuition = Number(latestAssessment.value.tuition_fee || 0)
  const other = Number(latestAssessment.value.other_fees || 0)
  const reg = Number(latestAssessment.value.registration_fee || 0)
  const lab = Number(latestAssessment.value.lab_fee || 0)
  const misc = Number(latestAssessment.value.misc_fee || 0)
  if (tuition || other || reg || lab || misc) {
    return Math.round((tuition + other + reg + lab + misc) * 100) / 100
  }
  return props.fees?.reduce((s, f) => s + Number(f.amount || 0), 0) ?? 0
})

const subjects = computed<SubjectLine[]>(() => {
  if (Array.isArray(latestAssessment.value.subjects) && latestAssessment.value.subjects.length) {
    return latestAssessment.value.subjects.map((s: any) => ({
      subject_code: s.subject_code ?? s.code ?? '',
      description: s.description ?? s.title ?? s.name ?? '',
      units: Number(s.units ?? s.total_units ?? s.unit ?? 0),
      lec_units: Number(s.lec_units ?? 0),
      lab_units: Number(s.lab_units ?? 0),
      tuition: Number(s.tuition ?? 0),
      lab_fee: Number(s.lab_fee ?? s.lab ?? 0),
      misc_fee: Number(s.misc_fee ?? s.misc ?? 0),
      total: Number(s.total ?? (s.tuition ?? 0) + (s.lab_fee ?? 0) + (s.misc_fee ?? 0)),
      semester: s.semester ?? latestAssessment.value.semester ?? '',
      time: s.time ?? '',
      day: s.day ?? '',
    }))
  }
  return []
})

const totalUnits = computed(() => {
  if (typeof latestAssessment.value.total_units === 'number') return latestAssessment.value.total_units
  return subjects.value.reduce((s, r) => s + Number(r.units || 0), 0)
})

const totalPaid = computed(() => {
  return (props.transactions ?? [])
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)
})

const remainingBalance = computed(() => {
  const charges = (props.transactions ?? [])
    .filter(t => t.kind === 'charge')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)
  const payments = (props.transactions ?? [])
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)
  const diff = charges - payments
  if ((props.transactions ?? []).length === 0) {
    const assessed = totalAssessmentFee.value
    return Math.max(0, Math.round((assessed - totalPaid.value) * 100) / 100)
  }
  return Math.max(0, Math.round(diff * 100) / 100)
})

const paymentPercentage = computed(() => {
  if (totalAssessmentFee.value === 0) return 0
  return Math.min(100, Math.round((totalPaid.value / totalAssessmentFee.value) * 100))
})

const feesByCategory = computed(() => {
  const list = props.fees ?? []
  const grouped = list.reduce((acc: Record<string, any[]>, fee) => {
    const cat = fee.category || 'Other'
    acc[cat] = acc[cat] ?? []
    acc[cat].push(fee)
    return acc
  }, {})
  return Object.entries(grouped).map(([category, arr]) => ({
    category,
    fees: arr,
    total: arr.reduce((s, f) => s + Number(f.amount || 0), 0),
  }))
})

const paymentHistory = computed(() => {
  return (props.transactions ?? [])
    .filter(t => t.kind === 'payment')
    .sort((a, b) => (new Date(b.created_at || '').getTime() - new Date(a.created_at || '').getTime()))
})

const pendingCharges = computed(() => {
  return (props.transactions ?? [])
    .filter(t => t.kind === 'charge' && t.status === 'pending')
})

const canSubmitPayment = computed(() => {
  return remainingBalance.value > 0 &&
    paymentForm.amount > 0 &&
    paymentForm.amount <= remainingBalance.value &&
    !paymentForm.processing
})

const paymentFormErrors = computed(() => {
  const errors: string[] = []
  if (paymentForm.amount <= 0) errors.push('Amount must be greater than zero')
  if (paymentForm.amount > remainingBalance.value) errors.push('Amount cannot exceed remaining balance')
  if (!paymentForm.payment_method) errors.push('Please select a payment method')
  if (!paymentForm.paid_at) errors.push('Please select a payment date')
  return errors
})

watch(() => props.tab, (newTab) => {
  if (newTab) activeTab.value = newTab
})

onMounted(() => {
  const urlTab = getTabFromUrl()
  if (urlTab) activeTab.value = urlTab
})

const viewTransaction = (transaction: Transaction) => {
  selectedTransaction.value = transaction
  showDetailsDialog.value = true
}

const handlePayNow = (transaction: Transaction) => {
  paymentForm.amount = transaction.amount
  paymentForm.description = `Payment for ${transaction.type || transaction.meta?.description || 'Charge'}`
  activeTab.value = 'payment'
}

const submitPayment = () => {
  if (!canSubmitPayment.value) {
    if (paymentFormErrors.value.length > 0) {
      paymentForm.setError('amount', paymentFormErrors.value[0])
    }
    return
  }

  paymentForm.post(route('account.pay-now'), {
    preserveScroll: true,
    onSuccess: () => {
      paymentForm.reset()
      paymentForm.amount = 0
      paymentForm.payment_method = 'cash'
      paymentForm.paid_at = new Date().toISOString().split('T')[0]
      paymentForm.description = 'Payment for fees'
      activeTab.value = 'history'
    },
    onError: (errors) => {
      console.error('Payment errors:', errors)
    },
  })
}

const downloadPDF = () => {
  console.log('Download PDF')
}

const setPaymentAmount = (percentage: number) => {
  paymentForm.amount = Math.round(remainingBalance.value * (percentage / 100) * 100) / 100
}
</script>

<template>
  <AppLayout>
    <Head title="My Account" />

    <div class="w-full p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold">My Account Overview</h1>
        <p v-if="currentTerm" class="text-gray-600 mt-1">
          {{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}
        </p>
        <p v-if="latestAssessment.assessment_number" class="text-sm text-gray-500 mt-1">
          Assessment No: {{ latestAssessment.assessment_number }}
        </p>
        <p v-else-if="props.account?.account_number" class="text-sm text-gray-500 mt-1">
          Account No: {{ props.account.account_number }}
        </p>
      </div>

      <!-- Balance Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-blue-100 rounded-lg">
              <Receipt :size="24" class="text-blue-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Assessment Fee</h3>
          <p class="text-3xl font-bold text-blue-600">
            {{ formatCurrency(totalAssessmentFee) }}
          </p>
          <p v-if="latestAssessment.tuition_fee || latestAssessment.other_fees" class="text-xs text-gray-500 mt-2">
            Tuition: {{ formatCurrency(latestAssessment.tuition_fee || 0) }} •
            Other: {{ formatCurrency(latestAssessment.other_fees || 0) }}
          </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Paid</h3>
          <p class="text-3xl font-bold text-green-600">
            {{ formatCurrency(totalPaid) }}
          </p>
          <p class="text-xs text-gray-500 mt-2">
            {{ paymentHistory.length }} payment(s) made
          </p>
        </div>

        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div :class="[
              'p-3 rounded-lg',
              remainingBalance > 0 ? 'bg-red-100' : 'bg-green-100'
            ]">
              <component
                :is="remainingBalance > 0 ? AlertCircle : CheckCircle"
                :size="24"
                :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'"
              />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Current Balance</h3>
          <p class="text-3xl font-bold" :class="remainingBalance > 0 ? 'text-red-600' : 'text-green-600'">
            {{ formatCurrency(remainingBalance) }}
          </p>
          <p class="text-xs text-gray-500 mt-2">
            {{ remainingBalance > 0 ? 'Amount due' : 'Fully paid' }}
          </p>
        </div>
      </div>

      <!-- Payment Progress Bar -->
      <div v-if="totalAssessmentFee > 0" class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Payment Progress</h2>
          <span class="text-2xl font-bold text-blue-600">
            {{ formatPercentage(paymentPercentage) }}
          </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div
            class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-500"
            :style="{ width: `${paymentPercentage}%` }"
          ></div>
        </div>
        <div class="flex justify-between mt-2 text-sm text-gray-600">
          <span>{{ formatCurrency(totalPaid) }} paid</span>
          <span>{{ formatCurrency(totalAssessmentFee) }} total</span>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md mb-6">
        <div class="border-b">
          <nav class="flex gap-4 px-6">
            <button
              @click="activeTab = 'fees'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'fees'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <Receipt :size="16" />
              Fees & Assessment
            </button>
            <button
              @click="activeTab = 'history'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'history'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <History :size="16" />
              Payment History
            </button>
            <button
              @click="activeTab = 'payment'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors flex items-center gap-2',
                activeTab === 'payment'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              <CreditCard :size="16" />
              Make Payment
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- ============================================ -->
          <!-- CERTIFICATE OF MATRICULATION - OPTIMIZED    -->
          <!-- ============================================ -->
          <div v-if="activeTab === 'fees'" class="space-y-6">
            <h2 class="text-2xl font-bold text-gray-900 uppercase tracking-wide border-b-2 border-blue-600 pb-2">
              Certificate of Matriculation Form
            </h2>

            <!-- Student Info Grid -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-lg p-6 border-2 border-blue-200">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</div>
                  <div class="text-base font-bold text-gray-900">{{ props.student?.full_name || props.account?.student_name || props.student?.name || 'Student Name' }}</div>
                </div>
                
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Course & Year</div>
                  <div class="text-base font-bold text-gray-900">{{ props.account?.course || props.student?.course || '-' }} - {{ props.student?.year_level || props.account?.year_level || '-' }}</div>
                </div>
                
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Semester/Summer</div>
                  <div class="text-base font-bold text-gray-900">{{ currentTerm?.semester || latestAssessment.semester || '1st Sem' }}</div>
                </div>
                
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">School Year</div>
                  <div class="text-base font-bold text-gray-900">{{ latestAssessment.school_year || `${currentTerm?.year}-${(currentTerm?.year || 2025) + 1}` }}</div>
                </div>
                
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Major</div>
                  <div class="text-base font-bold text-gray-900">{{ props.student?.major || 'N/A' }}</div>
                </div>
                
                <div class="space-y-1">
                  <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Registration Date</div>
                  <div class="text-base font-bold text-gray-900">{{ props.account?.registered_at ? formatDate(props.account.registered_at, 'short') : 'N/A' }}</div>
                </div>
              </div>
            </div>

            <!-- Subjects Table -->
            <div class="bg-white rounded-lg border-2 border-gray-300 overflow-hidden shadow-sm">
              <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-3">
                <h3 class="text-lg font-bold text-white uppercase tracking-wide">Enrolled Subjects</h3>
              </div>
              
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-100">
                    <tr>
                      <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">
                        Subject Code
                      </th>
                      <th scope="col" class="px-4 py-3 text-left text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">
                        Description
                      </th>
                      <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">
                        Units
                      </th>
                      <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider border-r border-gray-300">
                        Time
                      </th>
                      <th scope="col" class="px-4 py-3 text-center text-xs font-bold text-gray-700 uppercase tracking-wider">
                        Day
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-if="subjects.length === 0">
                      <td colspan="5" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center justify-center space-y-3">
                          <Receipt :size="48" class="text-gray-300" />
                          <p class="text-gray-500 font-medium">No subjects enrolled for this term</p>
                          <p class="text-sm text-gray-400">Subject enrollment data will appear here</p>
                        </div>
                      </td>
                    </tr>
                    
                    <tr
                      v-for="(subject, idx) in subjects"
                      :key="`subject-${idx}-${subject.subject_code}`"
                      class="hover:bg-blue-50 transition-colors duration-150"
                    >
                      <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold text-gray-900 border-r border-gray-200">
                        {{ subject.subject_code }}
                      </td>
                      <td class="px-4 py-3 text-sm text-gray-800 border-r border-gray-200">
                        {{ subject.description }}
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-center text-sm font-bold text-gray-900 border-r border-gray-200">
                        {{ subject.units }}
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700 border-r border-gray-200">
                        {{ subject.time || 'TBA' }}
                      </td>
                      <td class="px-4 py-3 whitespace-nowrap text-center text-sm text-gray-700">
                        {{ subject.day || 'TBA' }}
                      </td>
                    </tr>
                  </tbody>
                  
                  <!-- Totals Footer -->
                  <tfoot v-if="subjects.length > 0" class="bg-gradient-to-r from-gray-100 to-gray-200 border-t-2 border-gray-400">
                    <tr>
                      <td colspan="2" class="px-4 py-3 text-right text-sm font-black text-gray-900 uppercase tracking-wide border-r border-gray-300">
                        Total Units:
                      </td>
                      <td class="px-4 py-3 text-center text-base font-black text-blue-700 border-r border-gray-300">
                        {{ totalUnits }}
                      </td>
                      <td colspan="2" class="px-4 py-3"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

            <!-- Fee Breakdown -->
            <div class="bg-white rounded-lg border-2 border-gray-300 overflow-hidden shadow-sm">
              <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-3">
                <h3 class="text-lg font-bold text-white uppercase tracking-wide">Fees Assessment</h3>
              </div>
              
              <div class="p-6 space-y-3">
                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                  <span class="text-sm font-semibold text-gray-700">Registration Fee</span>
                  <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.registration_fee || latestAssessment.registration || 0) }}</span>
                </div>
                
                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                  <span class="text-sm font-semibold text-gray-700">Tuition Fee</span>
                  <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.tuition_fee || 0) }}</span>
                </div>
                
                <div class="flex justify-between items-center py-3 border-b border-gray-200">
                  <span class="text-sm font-semibold text-gray-700">Laboratory Fee</span>
                  <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.lab_fee || 0) }}</span>
                </div>
                
                <div class="flex justify-between items-center py-3 border-b-2 border-gray-300">
                  <span class="text-sm font-semibold text-gray-700">Miscellaneous Fee</span>
                  <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.misc_fee || 0) }}</span>
                </div>
                
                <div class="flex justify-between items-center pt-4 pb-2 bg-gradient-to-r from-blue-50 to-indigo-50 px-4 py-4 rounded-lg mt-4">
                  <span class="text-lg font-black text-gray-900 uppercase tracking-wide">Total Assessment Fee</span>
                  <span class="text-2xl font-black text-blue-700">{{ formatCurrency(totalAssessmentFee) }}</span>
                </div>
              </div>
            </div>

            <!-- Terms of Payment -->
            <div class="bg-white rounded-lg border-2 border-gray-300 overflow-hidden shadow-sm">
              <div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-3">
                <h3 class="text-lg font-bold text-white uppercase tracking-wide">Terms of Payment</h3>
              </div>
              
              <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="flex justify-between items-center py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <span class="text-sm font-semibold text-gray-700">Upon Registration</span>
                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.upon_registration ?? latestAssessment.registration ?? (props.termsOfPayment?.registration ?? 0)) }}</span>
                  </div>
                  
                  <div class="flex justify-between items-center py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <span class="text-sm font-semibold text-gray-700">Prelim</span>
                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.prelim ?? (props.termsOfPayment?.prelim ?? 0)) }}</span>
                  </div>
                  
                  <div class="flex justify-between items-center py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <span class="text-sm font-semibold text-gray-700">Midterm</span>
                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.midterm ?? (props.termsOfPayment?.midterm ?? 0)) }}</span>
                  </div>
                  
                  <div class="flex justify-between items-center py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <span class="text-sm font-semibold text-gray-700">Semi-Final</span>
                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.semi_final ?? (props.termsOfPayment?.semi_final ?? 0)) }}</span>
                  </div>
                  
                  <div class="flex justify-between items-center py-3 px-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                    <span class="text-sm font-semibold text-gray-700">Final</span>
                    <span class="text-base font-bold text-gray-900">{{ formatCurrency(latestAssessment.final ?? (props.termsOfPayment?.final ?? 0)) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- ============================================ -->
          <!-- END CERTIFICATE OF MATRICULATION            -->
          <!-- ============================================ -->

          <!-- Payment History -->
          <div v-if="activeTab === 'history'" class="space-y-4">
            <h2 class="text-lg font-semibold">Payment History</h2>

            <div v-if="paymentHistory.length" class="space-y-3">
              <div
                v-for="payment in paymentHistory"
                :key="payment.id"
                class="flex justify-between items-center p-4 border rounded-lg hover:bg-gray-50 transition-colors cursor-pointer"
                @click="viewTransaction(payment)"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-green-100 rounded">
                    <CheckCircle :size="20" class="text-green-600" />
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ payment.meta?.description || payment.type }}</p>
                    <p class="text-sm text-gray-600">
                      {{ formatDate(payment.created_at, 'short') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ payment.reference }}</p>
                  </div>
                </div>
                <div class="text-right">
                  <p class="text-lg font-semibold text-green-600">{{ formatCurrency(payment.amount) }}</p>
                  <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded">
                    {{ payment.status }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="text-center py-12">
              <History :size="48" class="text-gray-400 mx-auto mb-3" />
              <p class="text-gray-500">No payment history yet</p>
              <p class="text-sm text-gray-400 mt-1">Your payments will appear here after you make them</p>
            </div>
          </div>

          <!-- Payment Form -->
          <div v-if="activeTab === 'payment'" class="space-y-6">
            <h2 class="text-2xl font-bold">Add New Payment</h2>

            <div v-if="remainingBalance <= 0" class="p-4 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center gap-2">
                <CheckCircle :size="20" class="text-green-600" />
                <p class="text-green-800 font-medium">You have no outstanding balance!</p>
              </div>
              <p class="text-sm text-green-700 mt-1">All fees have been paid in full.</p>
            </div>

            <form @submit.prevent="submitPayment" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="amount">Amount *</Label>
                  <Input
                    id="amount"
                    v-model.number="paymentForm.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="remainingBalance"
                    placeholder="0.00"
                    required
                    :disabled="remainingBalance <= 0"
                  />
                  <p class="text-xs text-gray-500">Maximum: {{ formatCurrency(remainingBalance) }}</p>
                  <p v-if="paymentForm.errors.amount" class="text-red-500 text-sm">{{ paymentForm.errors.amount }}</p>
                </div>

                <div class="space-y-2">
                  <Label for="payment_method">Payment Method *</Label>
                  <select
                    id="payment_method"
                    v-model="paymentForm.payment_method"
                    :disabled="remainingBalance <= 0"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  >
                    <option value="cash">Cash</option>
                    <option value="gcash">GCash</option>
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="credit_card">Credit Card</option>
                    <option value="debit_card">Debit Card</option>
                  </select>
                  <p v-if="paymentForm.errors.payment_method" class="text-red-500 text-sm">{{ paymentForm.errors.payment_method }}</p>
                </div>

                <div class="space-y-2">
                  <Label>Reference Number <span class="text-xs text-gray-500">(Auto-generated)</span></Label>
                  <Input value="System will generate after submission" disabled class="bg-gray-100 cursor-not-allowed text-gray-500" />
                  <p class="text-xs text-gray-500">Reference number will be automatically generated</p>
                </div>

                <div class="space-y-2">
                  <Label for="paid_at">Payment Date *</Label>
                  <Input id="paid_at" v-model="paymentForm.paid_at" type="date" required :disabled="remainingBalance <= 0" />
                  <p v-if="paymentForm.errors.paid_at" class="text-red-500 text-sm">{{ paymentForm.errors.paid_at }}</p>
                </div>

                <div class="md:col-span-2 space-y-2">
                  <Label for="description">Description *</Label>
                  <Input id="description" v-model="paymentForm.description" placeholder="Payment description" required :disabled="remainingBalance <= 0" />
                  <p v-if="paymentForm.errors.description" class="text-red-500 text-sm">{{ paymentForm.errors.description }}</p>
                </div>
              </div>

              <div class="flex gap-2">
                <Button type="button" variant="ghost" class="flex-1" @click="setPaymentAmount(25)">25%</Button>
                <Button type="button" variant="ghost" class="flex-1" @click="setPaymentAmount(50)">50%</Button>
                <Button type="button" variant="ghost" class="flex-1" @click="setPaymentAmount(100)">Pay All</Button>
              </div>

              <Button type="submit" class="w-full" :disabled="!canSubmitPayment || paymentForm.processing">
                <DollarSign v-if="!paymentForm.processing" class="w-4 h-4 mr-2" />
                <span v-if="paymentForm.processing">Processing...</span>
                <span v-else-if="remainingBalance <= 0">No Balance to Pay</span>
                <span v-else>Record Payment</span>
              </Button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Details Dialog -->
    <TransactionDetailsDialog
      v-model:open="showDetailsDialog"
      :transaction="selectedTransaction"
      :show-pay-now-button="true"
      :show-download-button="true"
      @pay-now="handlePayNow"
      @download="downloadPDF"
    />
  </AppLayout>
</template>