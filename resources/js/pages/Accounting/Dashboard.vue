<!-- resources/js/pages/Accounting/Dashboard.vue -->
<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { BarChart3, TrendingUp, Users, DollarSign, AlertCircle,
  Receipt, Clock, CheckCircle, ArrowUpRight, ArrowDownRight,
  Download, RefreshCw, FileText, CreditCard,
} from 'lucide-vue-next'
import StudentFeeWidget from '@/components/widgets/StudentFeeWidget.vue'

interface Props {
  stats: {
    total_students: number
    active_students: number
    total_outstanding: number
    recent_payments_30d: number
    pending_charges: number
    overdue_count: number
  }
  recentTransactions: any[]
  overdueStudents: any[]
  recentAssessments: any[]
  paymentByMethod: any[]
  studentsByYearLevel: any[]
  currentTerm: any
  studentsWithBalance: any[]  // ✅ ADD THIS
  recentPayments: any[]       // ✅ ADD THIS
  paymentTrends: any[]        // ✅ ADD THIS
}

type Stats = {
  total_students: number
  active_students: number
  total_charges: number
  total_payments: number
  total_pending: number
  collection_rate: number
  active_fees: number
  total_fee_amount: number
}

type Student = {
  id: number
  name: string
  email: string
  student_id: string
  course: string
  year_level: string
  balance: number
}

type Payment = {
  id: number
  reference: string
  student_name: string
  amount: number
  status: string
  paid_at: string
  created_at: string
}

type PaymentTrend = {
  month: string
  total: number
  count: number
}

type PaymentMethod = {
  method: string
  count: number
  total: number
}

type YearLevel = {
  year_level: string
  count: number
}

// Add Student Fee Stats type
type StudentFeeStats = {
  total_assessments: number
  total_assessment_amount: number
  pending_assessments: number
  recent_assessments: number
  recent_payments_amount: number
}

const props = defineProps<{
  stats: Stats
  studentsWithBalance: Student[]
  recentPayments: Payment[]
  paymentTrends: PaymentTrend[]
  paymentByMethod: PaymentMethod[]
  studentsByYearLevel: YearLevel[]
  currentTerm: {
    year: number
    semester: string
  }
  // Add student fee stats
  studentFeeStats?: StudentFeeStats
}>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Accounting Dashboard' },
]

const activeTab = ref<'overview' | 'payments' | 'students'>('overview')

const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

const formatMonth = (month: string) => {
  const [year, monthNum] = month.split('-')
  const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
  return `${monthNames[parseInt(monthNum) - 1]} ${year}`
}

const getCollectionRateColor = (rate: number) => {
  if (rate >= 80) return 'text-green-600'
  if (rate >= 60) return 'text-yellow-600'
  return 'text-red-600'
}

const getTrendIcon = (index: number) => {
  if (index === 0) return null
  const current = props.paymentTrends[index]?.total || 0
  const previous = props.paymentTrends[index - 1]?.total || 0
  return current > previous ? ArrowUpRight : ArrowDownRight
}

const getTrendColor = (index: number) => {
  if (index === 0) return ''
  const current = props.paymentTrends[index]?.total || 0
  const previous = props.paymentTrends[index - 1]?.total || 0
  return current > previous ? 'text-green-600' : 'text-red-600'
}

const refreshData = () => {
  router.reload({ only: ['stats', 'recentPayments', 'studentsWithBalance', 'studentFeeStats'] })
}

const viewStudent = (studentId: number) => {
  router.visit(route('students.show', studentId))
}
</script>

<template>
  <AppLayout>
    <Head title="Accounting Dashboard" />

    <div class="w-full p-6 space-y-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold">Accounting Dashboard</h1>
          <p class="text-gray-600 mt-1">
            {{ currentTerm.semester }} - {{ currentTerm.year }}-{{ currentTerm.year + 1 }}
          </p>
        </div>
        <div class="flex gap-2">
          <button
            @click="refreshData"
            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 flex items-center gap-2"
          >
            <RefreshCw :size="16" />
            Refresh
          </button>
          <Link
            :href="route('fees.index')"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2"
          >
            <Receipt :size="16" />
            Manage Fees
          </Link>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Students -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 mb-1">Total Students</p>
              <p class="text-3xl font-bold text-gray-900">{{ stats.total_students }}</p>
              <p class="text-xs text-green-600 mt-2">
                {{ stats.active_students }} active
              </p>
            </div>
            <div class="p-3 bg-blue-100 rounded-lg">
              <Users :size="24" class="text-blue-600" />
            </div>
          </div>
        </div>

        <!-- Total Collections -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 mb-1">Total Collections</p>
              <p class="text-2xl font-bold text-green-600">
                {{ formatCurrency(stats.total_payments) }}
              </p>
              <p class="text-xs text-gray-500 mt-2">All-time</p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
              <CheckCircle :size="24" class="text-green-600" />
            </div>
          </div>
        </div>

        <!-- Pending Payments -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 mb-1">Pending Payments</p>
              <p class="text-2xl font-bold text-red-600">
                {{ formatCurrency(stats.total_pending) }}
              </p>
              <p class="text-xs text-gray-500 mt-2">Outstanding</p>
            </div>
            <div class="p-3 bg-red-100 rounded-lg">
              <Clock :size="24" class="text-red-600" />
            </div>
          </div>
        </div>

        <!-- Collection Rate -->
        <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 mb-1">Collection Rate</p>
              <p class="text-3xl font-bold" :class="getCollectionRateColor(stats.collection_rate)">
                {{ stats.collection_rate }}%
              </p>
              <p class="text-xs text-gray-500 mt-2">Overall efficiency</p>
            </div>
            <div class="p-3 bg-purple-100 rounded-lg">
              <TrendingUp :size="24" class="text-purple-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- Student Fee Management Widget (NEW) -->
      <div v-if="studentFeeStats" class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-md p-6 border-2 border-blue-200">
        <div class="flex items-center justify-between mb-6">
          <div>
            <h2 class="text-xl font-bold text-gray-900 flex items-center gap-2">
              <FileText :size="24" class="text-blue-600" />
              Student Fee Management
            </h2>
            <p class="text-sm text-gray-600 mt-1">Assessment and fee tracking overview</p>
          </div>
          <Link
            :href="route('student-fees.index')"
            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center gap-2 transition-colors"
          >
            <Receipt :size="16" />
            Manage Assessments
          </Link>
        </div>

        <!-- Student Fee Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          <!-- Total Assessments -->
          <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="text-sm text-gray-600 mb-1">Total Assessments</p>
                <p class="text-2xl font-bold text-gray-900">{{ studentFeeStats.total_assessments }}</p>
                <p class="text-xs text-blue-600 mt-1">Active enrollments</p>
              </div>
              <div class="p-2 bg-blue-100 rounded-lg">
                <Users :size="20" class="text-blue-600" />
              </div>
            </div>
          </div>

          <!-- Total Assessment Amount -->
          <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="text-sm text-gray-600 mb-1">Total Assessment</p>
                <p class="text-xl font-bold text-indigo-600">
                  {{ formatCurrency(studentFeeStats.total_assessment_amount) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Current term</p>
              </div>
              <div class="p-2 bg-indigo-100 rounded-lg">
                <TrendingUp :size="20" class="text-indigo-600" />
              </div>
            </div>
          </div>

          <!-- Pending Assessments -->
          <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="text-sm text-gray-600 mb-1">Pending Balance</p>
                <p class="text-xl font-bold text-red-600">
                  {{ formatCurrency(studentFeeStats.pending_assessments) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Outstanding</p>
              </div>
              <div class="p-2 bg-red-100 rounded-lg">
                <AlertCircle :size="20" class="text-red-600" />
              </div>
            </div>
          </div>

          <!-- Recent Payments -->
          <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between">
              <div class="flex-1">
                <p class="text-sm text-gray-600 mb-1">Recent Payments</p>
                <p class="text-xl font-bold text-green-600">
                  {{ formatCurrency(studentFeeStats.recent_payments_amount) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Last 30 days</p>
              </div>
              <div class="p-2 bg-green-100 rounded-lg">
                <DollarSign :size="20" class="text-green-600" />
              </div>
            </div>
          </div>
        </div>

        <!-- Quick Actions for Student Fees -->
        <div class="mt-4 pt-4 border-t border-blue-200">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <Link
              :href="route('student-fees.create')"
              class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
            >
              <div class="p-2 bg-blue-500 rounded">
                <FileText :size="16" class="text-white" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Create Assessment</p>
                <p class="text-xs text-gray-600">New student fee</p>
              </div>
            </Link>

            <Link
              :href="route('student-fees.index')"
              class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
            >
              <div class="p-2 bg-indigo-500 rounded">
                <Users :size="16" class="text-white" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">View All Students</p>
                <p class="text-xs text-gray-600">Manage fees</p>
              </div>
            </Link>

            <Link
              :href="route('student-fees.index', { filter: 'outstanding' })"
              class="flex items-center gap-2 p-3 bg-white rounded-lg hover:bg-blue-50 transition-colors border border-blue-200"
            >
              <div class="p-2 bg-red-500 rounded">
                <CreditCard :size="16" class="text-white" />
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Outstanding Balance</p>
                <p class="text-xs text-gray-600">{{ studentFeeStats.pending_assessments > 0 ? 'Needs attention' : 'All clear' }}</p>
              </div>
            </Link>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="bg-white rounded-lg shadow-md">
        <div class="border-b">
          <nav class="flex gap-4 px-6">
            <button
              @click="activeTab = 'overview'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'overview'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Overview
            </button>
            <button
              @click="activeTab = 'payments'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'payments'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Recent Payments
            </button>
            <button
              @click="activeTab = 'students'"
              :class="[
                'py-4 px-2 border-b-2 font-medium text-sm transition-colors',
                activeTab === 'students'
                  ? 'border-blue-600 text-blue-600'
                  : 'border-transparent text-gray-500 hover:text-gray-700',
              ]"
            >
              Outstanding Balances
            </button>
          </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="space-y-6">

            <!-- Payment Methods & Year Levels -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <!-- Payment by Method -->
              <div>
                <h3 class="text-lg font-semibold mb-4">Payment Methods</h3>
                <div class="space-y-3">
                  <div
                    v-for="method in paymentByMethod"
                    :key="method.method"
                    class="bg-gray-50 rounded-lg p-4"
                  >
                    <div class="flex justify-between items-center mb-2">
                      <span class="font-medium">{{ method.method }}</span>
                      <span class="text-sm text-gray-600">{{ method.count }} transactions</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <div class="flex-1 bg-gray-200 rounded-full h-2">
                        <div
                          class="bg-blue-500 h-2 rounded-full"
                          :style="{
                            width: `${(method.total / paymentByMethod.reduce((sum, m) => sum + m.total, 0)) * 100}%`,
                          }"
                        ></div>
                      </div>
                      <span class="text-sm font-semibold">{{ formatCurrency(method.total) }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Students by Year Level -->
              <div>
                <h3 class="text-lg font-semibold mb-4">Students by Year Level</h3>
                <div class="space-y-3">
                  <div
                    v-for="level in studentsByYearLevel"
                    :key="level.year_level"
                    class="bg-gray-50 rounded-lg p-4"
                  >
                    <div class="flex justify-between items-center mb-2">
                      <span class="font-medium">{{ level.year_level }}</span>
                      <span class="text-lg font-bold text-blue-600">{{ level.count }}</span>
                    </div>
                    <div class="flex-1 bg-gray-200 rounded-full h-2">
                      <div
                        class="bg-green-500 h-2 rounded-full"
                        :style="{
                          width: `${(level.count / stats.total_students) * 100}%`,
                        }"
                      ></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Payments Tab -->
          <div v-if="activeTab === 'payments'">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Recent Payments</h3>
              <Link
                :href="route('transactions.index')"
                class="text-sm text-blue-600 hover:text-blue-800"
              >
                View All →
              </Link>
            </div>

            <div class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Reference
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Student
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Amount
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Status
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                      Date
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                  <tr
                    v-for="payment in recentPayments"
                    :key="payment.id"
                    class="hover:bg-gray-50"
                  >
                    <td class="px-4 py-3 text-sm font-medium">{{ payment.reference }}</td>
                    <td class="px-4 py-3 text-sm">{{ payment.student_name }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-green-600">
                      {{ formatCurrency(payment.amount) }}
                    </td>
                    <td class="px-4 py-3 text-sm">
                      <span
                        class="px-2 py-1 text-xs rounded-full"
                        :class="
                          payment.status === 'paid'
                            ? 'bg-green-100 text-green-800'
                            : 'bg-yellow-100 text-yellow-800'
                        "
                      >
                        {{ payment.status }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                      {{ formatDate(payment.created_at) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-if="!recentPayments.length" class="text-center py-8 text-gray-500">
              No recent payments found
            </div>
          </div>

          <!-- Students Tab -->
          <div v-if="activeTab === 'students'">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-semibold">Students with Outstanding Balances</h3>
              <Link
                :href="route('students.index')"
                class="text-sm text-blue-600 hover:text-blue-800"
              >
                View All Students →
              </Link>
            </div>

            <div class="space-y-3">
              <div
                v-for="student in studentsWithBalance"
                :key="student.id"
                class="bg-gray-50 rounded-lg p-4 hover:bg-gray-100 transition-colors cursor-pointer"
                @click="viewStudent(student.id)"
              >
                <div class="flex justify-between items-start">
                  <div class="flex-1">
                    <p class="font-medium text-gray-900">{{ student.name }}</p>
                    <p class="text-sm text-gray-600">{{ student.student_id }} • {{ student.email }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                      {{ student.course }} - {{ student.year_level }}
                    </p>
                  </div>
                  <div class="text-right">
                    <p class="text-lg font-bold text-red-600">{{ formatCurrency(student.balance) }}</p>
                    <p class="text-xs text-gray-500">Outstanding</p>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="!studentsWithBalance.length" class="text-center py-8 text-gray-500">
              No students with outstanding balances
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <Link
            :href="route('fees.create')"
            class="flex items-center gap-3 p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
          >
            <div class="p-2 bg-blue-500 rounded">
              <Receipt :size="20" class="text-white" />
            </div>
            <div>
              <p class="font-medium text-gray-900">Create Fee</p>
              <p class="text-xs text-gray-600">Add new fee</p>
            </div>
          </Link>

          <Link
            :href="route('subjects.create')"
            class="flex items-center gap-3 p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors"
          >
            <div class="p-2 bg-green-500 rounded">
              <BarChart3 :size="20" class="text-white" />
            </div>
            <div>
              <p class="font-medium text-gray-900">Create Subject</p>
              <p class="text-xs text-gray-600">Add new subject</p>
            </div>
          </Link>

          <Link
            :href="route('students.index')"
            class="flex items-center gap-3 p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors"
          >
            <div class="p-2 bg-purple-500 rounded">
              <Users :size="20" class="text-white" />
            </div>
            <div>
              <p class="font-medium text-gray-900">Manage Students</p>
              <p class="text-xs text-gray-600">View all students</p>
            </div>
          </Link>

          <Link
            :href="route('transactions.index')"
            class="flex items-center gap-3 p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors"
          >
            <div class="p-2 bg-orange-500 rounded">
              <DollarSign :size="20" class="text-white" />
            </div>
            <div>
              <p class="font-medium text-gray-900">View Transactions</p>
              <p class="text-xs text-gray-600">All transactions</p>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </AppLayout>
</template>