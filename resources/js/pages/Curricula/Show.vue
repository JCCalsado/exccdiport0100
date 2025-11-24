<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { 
  ArrowLeft, 
  Edit, 
  Trash2,
  Download,
  Power,
  PowerOff,
  Calculator,
  BookOpen,
  GraduationCap
} from 'lucide-vue-next'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'

interface Program {
  id: number
  code: string
  name: string
  major: string | null
  full_name: string
}

interface Course {
  id: number
  code: string
  title: string
  lec_units: number
  lab_units: number
  total_units: number
  has_lab: boolean
  pivot: {
    order: number
  }
}

interface Curriculum {
  id: number
  program: Program
  school_year: string
  year_level: string
  semester: string
  tuition_per_unit: number
  lab_fee: number
  registration_fee: number
  misc_fee: number
  term_count: number
  notes: string | null
  is_active: boolean
  created_at: string
  courses: Course[]
}

interface Props {
  curriculum: Curriculum
  totals: {
    total_units: number
    tuition: number
    lab_fees: number
    total_assessment: number
  }
  paymentTerms: {
    upon_registration: number
    prelim: number
    midterm: number
    semi_final: number
    final: number
  }
  enrolledStudentsCount: number
}

const props = defineProps<Props>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Curricula', href: route('curricula.index') },
  { title: 'Curriculum Details' },
]

// Toggle status
const toggleStatus = () => {
  router.post(
    route('curricula.toggleStatus', props.curriculum.id),
    {},
    {
      preserveScroll: true,
    }
  )
}

// Delete curriculum with native confirmation
const deleteCurriculum = () => {
  if (confirm(`Are you sure you want to delete this curriculum?\n\nProgram: ${props.curriculum.program.code}\nTerm: ${props.curriculum.year_level} - ${props.curriculum.semester}\n\nThis action cannot be undone.`)) {
    router.delete(route('curricula.destroy', props.curriculum.id), {
      onSuccess: () => {
        router.visit(route('curricula.index'))
      },
    })
  }
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}

// Format date
const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}
</script>

<template>
  <Head :title="`Curriculum - ${curriculum.program.code} ${curriculum.year_level} ${curriculum.semester}`" />

  <AppLayout>
    <div class="space-y-6 p-6 max-w-7xl mx-auto">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-3xl font-bold">
              {{ curriculum.program.code }} - {{ curriculum.year_level }}
            </h1>
            <span
              class="inline-flex items-center gap-1 px-3 py-1 text-sm font-semibold rounded-full"
              :class="curriculum.is_active 
                ? 'bg-green-100 text-green-800' 
                : 'bg-gray-100 text-gray-800'"
            >
              <component :is="curriculum.is_active ? Power : PowerOff" class="w-3 h-3" />
              {{ curriculum.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <p class="text-gray-600 mt-2">
            {{ curriculum.program.major }} • {{ curriculum.semester }} • {{ curriculum.school_year }}
          </p>
        </div>
        <div class="flex items-center gap-3">
          <Button
            variant="outline"
            @click="toggleStatus"
          >
            <component :is="curriculum.is_active ? PowerOff : Power" class="w-4 h-4 mr-2" />
            {{ curriculum.is_active ? 'Deactivate' : 'Activate' }}
          </Button>
          <Link :href="route('curricula.edit', curriculum.id)">
            <Button variant="outline">
              <Edit class="w-4 h-4 mr-2" />
              Edit
            </Button>
          </Link>
          <Button
            variant="outline"
            class="text-red-600 hover:bg-red-50 hover:text-red-700"
            @click="deleteCurriculum"
          >
            <Trash2 class="w-4 h-4 mr-2" />
            Delete
          </Button>
          <Link :href="route('curricula.index')">
            <Button variant="ghost">
              <ArrowLeft class="w-4 h-4 mr-2" />
              Back
            </Button>
          </Link>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Total Courses</p>
                <p class="text-2xl font-bold">{{ curriculum.courses.length }}</p>
              </div>
              <div class="p-3 bg-blue-100 rounded-lg">
                <BookOpen class="w-6 h-6 text-blue-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Total Units</p>
                <p class="text-2xl font-bold">{{ totals.total_units }}</p>
              </div>
              <div class="p-3 bg-green-100 rounded-lg">
                <Calculator class="w-6 h-6 text-green-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Total Assessment</p>
                <p class="text-xl font-bold">{{ formatCurrency(totals.total_assessment) }}</p>
              </div>
              <div class="p-3 bg-purple-100 rounded-lg">
                <Calculator class="w-6 h-6 text-purple-600" />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent class="pt-6">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm text-gray-600">Enrolled Students</p>
                <p class="text-2xl font-bold">{{ enrolledStudentsCount }}</p>
              </div>
              <div class="p-3 bg-orange-100 rounded-lg">
                <GraduationCap class="w-6 h-6 text-orange-600" />
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Courses -->
          <Card>
            <CardHeader>
              <CardTitle>Course List</CardTitle>
              <CardDescription>
                All courses included in this curriculum
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-12">#</TableHead>
                    <TableHead>Code</TableHead>
                    <TableHead>Course Title</TableHead>
                    <TableHead class="text-center">Lec</TableHead>
                    <TableHead class="text-center">Lab</TableHead>
                    <TableHead class="text-center">Total</TableHead>
                    <TableHead class="text-right">Cost</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="(course, index) in curriculum.courses" :key="course.id">
                    <TableCell class="font-medium">{{ index + 1 }}</TableCell>
                    <TableCell class="font-mono text-sm">{{ course.code }}</TableCell>
                    <TableCell>
                      {{ course.title }}
                      <span v-if="course.has_lab" class="ml-2 text-xs text-blue-600">● Lab</span>
                    </TableCell>
                    <TableCell class="text-center">{{ course.lec_units }}</TableCell>
                    <TableCell class="text-center">{{ course.lab_units }}</TableCell>
                    <TableCell class="text-center font-medium">{{ course.total_units }}</TableCell>
                    <TableCell class="text-right">
                      {{ formatCurrency(course.total_units * curriculum.tuition_per_unit + (course.has_lab ? curriculum.lab_fee : 0)) }}
                    </TableCell>
                  </TableRow>
                  <TableRow class="bg-gray-50 font-semibold">
                    <TableCell colspan="5" class="text-right">Total:</TableCell>
                    <TableCell class="text-center">{{ totals.total_units }}</TableCell>
                    <TableCell class="text-right">{{ formatCurrency(totals.tuition + totals.lab_fees) }}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>

          <!-- Notes -->
          <Card v-if="curriculum.notes">
            <CardHeader>
              <CardTitle>Notes</CardTitle>
            </CardHeader>
            <CardContent>
              <p class="text-gray-700 whitespace-pre-line">{{ curriculum.notes }}</p>
            </CardContent>
          </Card>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Fee Structure -->
          <Card>
            <CardHeader>
              <CardTitle>Fee Structure</CardTitle>
              <CardDescription>Per-student fees for this curriculum</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Tuition per Unit:</span>
                <span class="font-medium">{{ formatCurrency(curriculum.tuition_per_unit) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Lab Fee (per subject):</span>
                <span class="font-medium">{{ formatCurrency(curriculum.lab_fee) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Registration Fee:</span>
                <span class="font-medium">{{ formatCurrency(curriculum.registration_fee) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Miscellaneous Fee:</span>
                <span class="font-medium">{{ formatCurrency(curriculum.misc_fee) }}</span>
              </div>
            </CardContent>
          </Card>

          <!-- Assessment Summary -->
          <Card>
            <CardHeader>
              <CardTitle>Assessment Summary</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Tuition Fee:</span>
                <span class="font-medium">{{ formatCurrency(totals.tuition) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Lab Fees:</span>
                <span class="font-medium">{{ formatCurrency(totals.lab_fees) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Registration:</span>
                <span class="font-medium">{{ formatCurrency(curriculum.registration_fee) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Miscellaneous:</span>
                <span class="font-medium">{{ formatCurrency(curriculum.misc_fee) }}</span>
              </div>
              <div class="border-t pt-3">
                <div class="flex justify-between">
                  <span class="font-semibold">Total Assessment:</span>
                  <span class="font-bold text-lg text-blue-600">
                    {{ formatCurrency(totals.total_assessment) }}
                  </span>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Payment Terms -->
          <Card>
            <CardHeader>
              <CardTitle>Payment Terms</CardTitle>
              <CardDescription>{{ curriculum.term_count }} payment periods</CardDescription>
            </CardHeader>
            <CardContent class="space-y-2">
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Upon Registration:</span>
                <span class="font-medium">{{ formatCurrency(paymentTerms.upon_registration) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Prelim:</span>
                <span class="font-medium">{{ formatCurrency(paymentTerms.prelim) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Midterm:</span>
                <span class="font-medium">{{ formatCurrency(paymentTerms.midterm) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Semi-Final:</span>
                <span class="font-medium">{{ formatCurrency(paymentTerms.semi_final) }}</span>
              </div>
              <div class="flex justify-between text-sm">
                <span class="text-gray-600">Final:</span>
                <span class="font-medium">{{ formatCurrency(paymentTerms.final) }}</span>
              </div>
            </CardContent>
          </Card>

          <!-- Metadata -->
          <Card>
            <CardHeader>
              <CardTitle>Metadata</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
              <div>
                <p class="text-gray-600">Created:</p>
                <p class="font-medium">{{ formatDate(curriculum.created_at) }}</p>
              </div>
              <div>
                <p class="text-gray-600">Curriculum ID:</p>
                <p class="font-mono text-xs">{{ curriculum.id }}</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>