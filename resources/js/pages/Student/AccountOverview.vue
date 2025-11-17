<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { CreditCard, Calendar, CheckCircle, XCircle, Clock, AlertCircle } from 'lucide-vue-next'

type Fee = {
  name: string
  amount: number
  category?: string
}

type Transaction = {
  id: number
  reference: string
  type: string
  kind: string
  amount: number
  status: string
  created_at: string
  fee?: {
    name: string
    category: string
  }
  meta?: {
    fee_name?: string
    description?: string
    assessment_id?: number
    subject_code?: string
    subject_name?: string
  }
}

type Account = {
  id: number
  balance: number
  user_id: number
}

type CurrentTerm = {
  year: number
  semester: string
}

type Assessment = {
  id: number
  assessment_number: string
  year_level: string
  semester: string
  school_year: string
  tuition_fee: number
  other_fees: number
  total_assessment: number
  status: string
  created_at: string
}

const props = withDefaults(defineProps<{
  account: Account
  transactions: Transaction[]
  fees: Fee[]
  currentTerm?: CurrentTerm
  tab?: string
  latestAssessment?: Assessment
}>(), {
  currentTerm: () => ({
    year: new Date().getFullYear(),
    semester: '1st Sem'
  }),
  tab: 'fees'
})

// Get URL parameters for auto-payment
const urlParams = computed(() => {
  if (typeof window === 'undefined') return {};
  const params = new URLSearchParams(window.location.search);
  return {
    pay: params.get('pay'),
    tab: params.get('tab'),
    transaction_id: params.get('transaction_id'),
    reference: params.get('reference'),
    amount: params.get('amount'),
    category: params.get('category')
  };
});

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'My Account' },
]

// Get tab from URL if prop is not working
const getTabFromUrl = (): 'fees' | 'history' | 'payment' => {
  const tab = urlParams.value.tab
  
  if (tab === 'payment') return 'payment'
  if (tab === 'history') return 'history'
  return 'fees'
}

// Set initial tab - try prop first, then URL
const getInitialTab = (): 'fees' | 'history' | 'payment' => {
  if (props.tab === 'payment' || props.tab === 'history') {
    return props.tab
  }
  return getTabFromUrl()
}

const activeTab = ref<'fees' | 'history' | 'payment'>(getInitialTab())

// Watch for prop changes (in case of navigation)
watch(() => props.tab, (newTab) => {
  if (newTab === 'payment' || newTab === 'history') {
    activeTab.value = newTab
  }
})

// Auto-open payment tab and populate form if URL params exist
onMounted(() => {
  const params = urlParams.value;
  
  // Check if we should auto-switch to payment tab
  if (params.pay === 'true' && params.amount && params.reference) {
    activeTab.value = 'payment';
    
    // Auto-populate the payment form
    paymentForm.amount = parseFloat(params.amount);
    paymentForm.reference_number = params.reference;
    paymentForm.description = params.category || 'Payment';
    
    // Clear URL parameters after populating
    clearUrlParams();
  } else {
    // Otherwise use tab from URL if available
    const urlTab = getTabFromUrl();
    if (urlTab === 'payment' || urlTab === 'history') {
      activeTab.value = urlTab;
    }
  }
});

// Clear URL parameters
const clearUrlParams = () => {
  if (typeof window !== 'undefined') {
    const url = new URL(window.location.href);
    url.searchParams.delete('pay');
    url.searchParams.delete('transaction_id');
    url.searchParams.delete('reference');
    url.searchParams.delete('amount');
    url.searchParams.delete('category');
    window.history.replaceState({}, '', url.toString());
  }
};

const paymentForm = useForm({
  amount: 0,
  payment_method: 'cash',
  reference_number: '',
  paid_at: new Date().toISOString().split('T')[0],
  description: 'Payment for fees',
})

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

// Use latest assessment if available, otherwise calculate from fees
const totalAssessmentFee = computed(() => {
  if (props.latestAssessment) {
    return props.latestAssessment.total_assessment
  }
  return props.fees.reduce((sum, fee) => sum + Number(fee.amount), 0)
})

const totalPaid = computed(() => {
  return props.transactions
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount), 0)
})

// Robust calculation: compute from transactions regardless of backend sign convention
const remainingBalance = computed(() => {
  const txs = props.transactions ?? []

  const charges = txs
    .filter(t => t.kind === 'charge')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)

  const payments = txs
    .filter(t => t.kind === 'payment' && t.status === 'paid')
    .reduce((sum, t) => sum + Number(t.amount || 0), 0)

  const diff = charges - payments
  const rounded = Math.round(diff * 100) / 100

  return rounded > 0 ? rounded : 0
})

// Group fees by category for better display
const feesByCategory = computed(() => {
  const grouped = props.fees.reduce((acc, fee) => {
    const category = fee.category || 'Other'
    if (!acc[category]) {
      acc[category] = []
    }
    acc[category].push(fee)
    return acc
  }, {} as Record<string, Fee[]>)
  
  return Object.entries(grouped).map(([category, fees]) => ({
    category,
    fees,
    total: fees.reduce((sum, f) => sum + Number(f.amount), 0)
  }))
})

const paymentHistory = computed(() => {
  return props.transactions
    .filter(t => t.kind === 'payment')
    .sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
})

const pendingCharges = computed(() => {
  return props.transactions
    .filter(t => t.kind === 'charge' && t.status === 'pending')
})

const canSubmitPayment = computed(() => {
  return remainingBalance.value > 0 && paymentForm.amount > 0
})

const submitPayment = () => {
  // Validate amount
  if (paymentForm.amount <= 0) {
    paymentForm.setError('amount', 'Amount must be greater than zero')
    return
  }

  if (paymentForm.amount > remainingBalance.value) {
    paymentForm.setError('amount', 'Amount cannot exceed remaining balance')
    return
  }

  paymentForm.post(route('account.pay-now'), {
    preserveScroll: true,
    onSuccess: () => {
      // Reset form after successful payment
      paymentForm.reset()
      paymentForm.amount = 0
      paymentForm.payment_method = 'cash'
      paymentForm.reference_number = ''
      paymentForm.paid_at = new Date().toISOString().split('T')[0]
      paymentForm.description = 'Payment for fees'
      
      // Switch to payment history tab to see the new payment
      activeTab.value = 'history'
    },
    onError: (errors) => {
      console.error('Payment errors:', errors)
    },
  })
}
</script>

<template>
  <AppLayout>
    <Head title="My Account" />

    <div class="w-full p-6 space-y-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold">My Account Overview</h1>
        <p v-if="currentTerm" class="text-gray-600 mt-1">
          {{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}
        </p>
        <p v-if="latestAssessment" class="text-sm text-gray-500 mt-1">
          Assessment No: {{ latestAssessment.assessment_number }}
        </p>
      </div>

      <!-- Balance Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Total Assessment -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-blue-100 rounded-lg">
              <CreditCard :size="24" class="text-blue-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Assessment Fee</h3>
          <p class="text-3xl font-bold text-blue-600">{{ formatCurrency(totalAssessmentFee) }}</p>
          <p v-if="latestAssessment" class="text-xs text-gray-500 mt-2">
            Tuition: {{ formatCurrency(latestAssessment.tuition_fee) }} • 
            Other: {{ formatCurrency(latestAssessment.other_fees) }}
          </p>
        </div>

        <!-- Total Paid -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
          <h3 class="text-sm font-medium text-gray-600 mb-2">Total Paid</h3>
          <p class="text-3xl font-bold text-green-600">{{ formatCurrency(totalPaid) }}</p>
          <p class="text-xs text-gray-500 mt-2">
            {{ paymentHistory.length }} payment(s) made
          </p>
        </div>

        <!-- Current Balance -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-2">
            <div :class="[
              'p-3 rounded-lg',
              remainingBalance > 0 ? 'bg-red-100' : 'bg-green-100'
            ]">
              <component :is="remainingBalance > 0 ? AlertCircle : CheckCircle" 
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

      <!-- Payment Progress Bar (if there's a balance) -->
      <div v-if="totalAssessmentFee > 0" class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Payment Progress</h2>
          <span class="text-2xl font-bold text-blue-600">
            {{ Math.round((totalPaid / totalAssessmentFee) * 100) }}%
          </span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-4">
          <div
            class="bg-gradient-to-r from-blue-500 to-green-500 h-4 rounded-full transition-all duration-500"
            :style="{ width: `${Math.min((totalPaid / totalAssessmentFee) * 100, 100)}%` }"
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
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'fees'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Fees & Assessment
            </button>
            <button
              @click="activeTab = 'history'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'history'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Payment History
            </button>
            <button
              @click="activeTab = 'payment'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'payment'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Make Payment
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Fees Tab -->
          <div v-if="activeTab === 'fees'">
            <h2 class="text-lg font-semibold mb-4">CURRENT ASSESSMENT</h2>
            
            <!-- Assessment Info Banner -->
            <div v-if="latestAssessment" class="bg-blue-50 rounded-lg p-4 mb-6 border border-blue-200">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                  <span class="text-gray-600">Assessment No:</span>
                  <p class="font-semibold">{{ latestAssessment.assessment_number }}</p>
                </div>
                <div>
                  <span class="text-gray-600">School Year:</span>
                  <p class="font-semibold">{{ latestAssessment.school_year }}</p>
                </div>
                <div>
                  <span class="text-gray-600">Semester:</span>
                  <p class="font-semibold">{{ latestAssessment.semester }}</p>
                </div>
                <div>
                  <span class="text-gray-600">Status:</span>
                  <span :class="[
                    'px-2 py-1 text-xs font-semibold rounded-full',
                    latestAssessment.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                  ]">
                    {{ latestAssessment.status }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Fees by Category -->
            <div v-if="feesByCategory.length" class="space-y-6">
              <div v-for="categoryGroup in feesByCategory" :key="categoryGroup.category" class="space-y-2">
                <h3 class="font-semibold text-gray-700 uppercase text-sm border-b pb-2">
                  {{ categoryGroup.category }}
                </h3>
                <div
                  v-for="(fee, index) in categoryGroup.fees"
                  :key="index"
                  class="flex justify-between py-2 pl-4"
                >
                  <span class="text-gray-700">{{ fee.name }}</span>
                  <span class="font-medium">{{ formatCurrency(fee.amount) }}</span>
                </div>
                <div class="flex justify-between font-semibold text-sm pt-2 pl-4 border-t">
                  <span>{{ categoryGroup.category }} Subtotal</span>
                  <span>{{ formatCurrency(categoryGroup.total) }}</span>
                </div>
              </div>

              <div class="flex justify-between font-bold border-t-2 pt-4 text-lg">
                <span>TOTAL ASSESSMENT FEE</span>
                <span class="text-blue-600">{{ formatCurrency(totalAssessmentFee) }}</span>
              </div>
            </div>

            <p v-else class="text-gray-500 text-center py-4">
              No fees assigned yet.
            </p>

            <!-- Pending Charges -->
            <div v-if="pendingCharges.length" class="mt-8 border-t pt-6">
              <h3 class="text-md font-semibold mb-4 text-red-700 flex items-center gap-2">
                <Clock :size="20" />
                PENDING CHARGES
              </h3>
              <div class="space-y-3">
                <div
                  v-for="charge in pendingCharges"
                  :key="charge.id"
                  class="flex justify-between items-center p-3 bg-red-50 rounded border border-red-200"
                >
                  <div class="flex-1">
                    <p class="font-medium text-gray-900">
                      {{ charge.fee?.name || charge.meta?.fee_name || charge.meta?.subject_name || charge.type }}
                    </p>
                    <p class="text-xs text-gray-600">{{ charge.reference }}</p>
                    <p v-if="charge.meta?.subject_code" class="text-xs text-gray-500">
                      {{ charge.meta.subject_code }}
                    </p>
                  </div>
                  <div class="flex items-center gap-3">
                    <div class="text-right">
                      <p class="text-lg font-semibold text-red-600">{{ formatCurrency(charge.amount) }}</p>
                      <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Pending</span>
                    </div>
                    <button
                      @click="() => {
                        activeTab = 'payment';
                        paymentForm.amount = charge.amount;
                        paymentForm.reference_number = charge.reference;
                        paymentForm.description = charge.fee?.name || charge.meta?.fee_name || charge.meta?.subject_name || charge.type;
                      }"
                      class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors text-sm font-medium whitespace-nowrap"
                    >
                      Pay Now
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Payment History Tab -->
          <div v-if="activeTab === 'history'">
            <h2 class="text-lg font-semibold mb-4">Payment History</h2>
            
            <div v-if="paymentHistory.length" class="space-y-3">
              <div
                v-for="payment in paymentHistory"
                :key="payment.id"
                class="flex justify-between items-center p-4 border rounded-lg hover:bg-gray-50 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-green-100 rounded">
                    <CheckCircle :size="20" class="text-green-600" />
                  </div>
                  <div>
                    <p class="font-medium text-gray-900">{{ payment.meta?.description || payment.type }}</p>
                    <p class="text-sm text-gray-600">
                      {{ formatDate(payment.created_at) }}
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
              <XCircle :size="48" class="text-gray-400 mx-auto mb-3" />
              <p class="text-gray-500">No payment history yet</p>
              <p class="text-sm text-gray-400 mt-1">Your payments will appear here after you make them</p>
            </div>
          </div>

          <!-- Payment Form Tab -->
          <div v-if="activeTab === 'payment'">
            <div class="flex items-center justify-between mb-6">
              <h2 class="text-2xl font-bold">Add New Payment</h2>
              <div class="text-sm text-gray-600 bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
                <p class="font-medium text-blue-900">💡 Quick Tip</p>
                <p class="text-blue-700">You can pay pending charges directly from the <span class="font-semibold">"Fees & Assessment"</span> tab</p>
              </div>
            </div>
            
            <!-- No Balance Message -->
            <div v-if="remainingBalance <= 0" class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
              <div class="flex items-center gap-2">
                <CheckCircle :size="20" class="text-green-600" />
                <p class="text-green-800 font-medium">You have no outstanding balance!</p>
              </div>
              <p class="text-sm text-green-700 mt-1">All fees have been paid in full.</p>
            </div>

            <form @submit.prevent="submitPayment">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Amount -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Amount</label>
                  <input
                    v-model="paymentForm.amount"
                    type="number"
                    step="0.01"
                    min="0"
                    :max="remainingBalance"
                    placeholder="0.00"
                    required
                    :disabled="remainingBalance <= 0"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    Maximum: {{ formatCurrency(remainingBalance) }}
                  </p>
                  <div v-if="paymentForm.errors.amount" class="text-red-500 text-sm mt-1">
                    {{ paymentForm.errors.amount }}
                  </div>
                </div>

                <!-- Payment Method -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                  <select
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
                  <div v-if="paymentForm.errors.payment_method" class="text-red-500 text-sm mt-1">
                    {{ paymentForm.errors.payment_method }}
                  </div>
                </div>

                <!-- Reference Number -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reference Number
                    <span class="text-xs text-gray-500">(Optional)</span>
                  </label>
                  <input
                    v-model="paymentForm.reference_number"
                    :disabled="remainingBalance <= 0"
                    placeholder="e.g., Transaction reference"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  />
                  <p class="text-xs text-gray-500 mt-1">
                    Optional reference for your records
                  </p>
                </div>

                <!-- Payment Date -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-1">Payment Date</label>
                  <input
                    v-model="paymentForm.paid_at"
                    type="date"
                    required
                    :disabled="remainingBalance <= 0"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  />
                  <div v-if="paymentForm.errors.paid_at" class="text-red-500 text-sm mt-1">
                    {{ paymentForm.errors.paid_at }}
                  </div>
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                  <input
                    v-model="paymentForm.description"
                    placeholder="Payment description"
                    required
                    :disabled="remainingBalance <= 0"
                    class="w-full px-4 py-2 border rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
                  />
                  <div v-if="paymentForm.errors.description" class="text-red-500 text-sm mt-1">
                    {{ paymentForm.errors.description }}
                  </div>
                </div>

                <!-- Submit Button -->
                <div class="md:col-span-2">
                  <button
                    type="submit"
                    class="w-full px-5 py-3 bg-indigo-600 text-white rounded-lg shadow hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:bg-gray-400 font-medium"
                    :disabled="!canSubmitPayment || paymentForm.processing"
                  >
                    <span v-if="paymentForm.processing">Processing...</span>
                    <span v-else-if="remainingBalance <= 0">No Balance to Pay</span>
                    <span v-else>Record Payment</span>
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>