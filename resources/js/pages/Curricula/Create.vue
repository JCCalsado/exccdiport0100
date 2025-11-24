<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { 
  ArrowLeft, 
  Save, 
  Plus, 
  X,
  Calculator,
  Info
} from 'lucide-vue-next'

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
}

interface Props {
  programs: Program[]
  courses: Course[]
  yearLevels: string[]
  semesters: string[]
  schoolYears: string[]
}

const props = defineProps<Props>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Curricula', href: route('curricula.index') },
  { title: 'Create Curriculum' },
]

// Form state
const form = useForm({
  program_id: '',
  school_year: '',
  year_level: '',
  semester: '',
  tuition_per_unit: 364.00,
  lab_fee: 1656.00,
  registration_fee: 200.00,
  misc_fee: 0.00,
  term_count: 5,
  courses: [] as number[],
  notes: '',
})

// Available courses based on selected program
const availableCourses = ref<Course[]>([])
const loadingCourses = ref(false)

// Watch program selection to load courses
watch(() => form.program_id, async (newProgramId) => {
  if (newProgramId) {
    loadingCourses.value = true
    try {
      const response = await fetch(route('curricula.get-courses', { program_id: newProgramId }))
      availableCourses.value = await response.json()
    } catch (error) {
      console.error('Failed to load courses:', error)
    } finally {
      loadingCourses.value = false
    }
  } else {
    availableCourses.value = []
  }
})

// Selected courses with details
const selectedCourses = computed(() => {
  return availableCourses.value.filter(course => 
    form.courses.includes(course.id)
  )
})

// Calculate totals
const totalUnits = computed(() => {
  return selectedCourses.value.reduce((sum, course) => sum + course.total_units, 0)
})

const totalLabCourses = computed(() => {
  return selectedCourses.value.filter(course => course.has_lab).length
})

const tuitionFee = computed(() => {
  return totalUnits.value * form.tuition_per_unit
})

const labFees = computed(() => {
  return totalLabCourses.value * form.lab_fee
})

const totalAssessment = computed(() => {
  return tuitionFee.value + labFees.value + form.registration_fee + form.misc_fee
})

// Miscellaneous Fee Breakdown
const miscBreakdown = [
  { name: 'Laboratory Fee', amount: 1656.00, included: true },
  { name: 'Entrep Fee', amount: 600.00, included: true },
  { name: 'Registration Fee', amount: 600.00, included: false }, // Separate
  { name: 'LMS', amount: 450.00, included: true },
  { name: 'Library Fee', amount: 450.00, included: true },
  { name: 'Athletic Fee', amount: 550.00, included: true },
  { name: 'PRISAA', amount: 300.00, included: true },
  { name: 'Publication Fee', amount: 200.00, included: true },
  { name: 'Audio-Visual Fee', amount: 250.00, included: true },
  { name: 'ID', amount: 150.00, included: true },
  { name: 'BICCS/PCCL/League', amount: 250.00, included: true },
  { name: 'Faculty Development', amount: 250.00, included: true },
  { name: 'Guidance Services', amount: 200.00, included: true },
  { name: 'Medical', amount: 300.00, included: true },
  { name: 'Insurance Fee', amount: 100.00, included: true },
  { name: 'Cultural Arts Fee', amount: 175.00, included: true },
  { name: 'Maintenance Fee', amount: 400.00, included: true },
]

const calculatedMiscFee = computed(() => {
  return miscBreakdown
    .filter(item => item.included)
    .reduce((sum, item) => sum + item.amount, 0)
})

// Auto-calculate misc fee
watch(calculatedMiscFee, (newValue) => {
  form.misc_fee = newValue
})

// Toggle course selection
const toggleCourse = (courseId: number) => {
  const index = form.courses.indexOf(courseId)
  if (index === -1) {
    form.courses.push(courseId)
  } else {
    form.courses.splice(index, 1)
  }
}

// Select all courses
const selectAllCourses = () => {
  form.courses = availableCourses.value.map(c => c.id)
}

// Deselect all courses
const deselectAllCourses = () => {
  form.courses = []
}

// Submit form
const submitForm = () => {
  form.post(route('curricula.store'), {
    preserveScroll: true,
  })
}

// Format currency
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
  }).format(amount)
}
</script>

<template>
  <Head title="Create Curriculum" />

  <AppLayout>
    <div class="space-y-6 p-6 max-w-7xl mx-auto">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">Create New Curriculum</h1>
          <p class="text-gray-600 mt-2">
            Set up a new OBE curriculum with courses and fee structure
          </p>
        </div>
        <Link :href="route('curricula.index')">
          <Button variant="outline" class="flex items-center gap-2">
            <ArrowLeft class="w-4 h-4" />
            Back to List
          </Button>
        </Link>
      </div>

      <form @submit.prevent="submitForm" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column: Basic Info -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Basic Information -->
            <Card>
              <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>
                  Select the program and term for this curriculum
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-4">
                <!-- Program -->
                <div>
                  <Label for="program_id">Program *</Label>
                  <select
                    id="program_id"
                    v-model="form.program_id"
                    class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                    :class="{ 'border-red-500': form.errors.program_id }"
                    required
                  >
                    <option value="">Select a program</option>
                    <option v-for="program in programs" :key="program.id" :value="program.id">
                      {{ program.code }} - {{ program.major }}
                    </option>
                  </select>
                  <p v-if="form.errors.program_id" class="text-sm text-red-600 mt-1">
                    {{ form.errors.program_id }}
                  </p>
                </div>

                <!-- Grid: Year Level, Semester, School Year -->
                <div class="grid grid-cols-3 gap-4">
                  <div>
                    <Label for="year_level">Year Level *</Label>
                    <select
                      id="year_level"
                      v-model="form.year_level"
                      class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg"
                      :class="{ 'border-red-500': form.errors.year_level }"
                      required
                    >
                      <option value="">Select</option>
                      <option v-for="year in yearLevels" :key="year" :value="year">
                        {{ year }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <Label for="semester">Semester *</Label>
                    <select
                      id="semester"
                      v-model="form.semester"
                      class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg"
                      :class="{ 'border-red-500': form.errors.semester }"
                      required
                    >
                      <option value="">Select</option>
                      <option v-for="sem in semesters" :key="sem" :value="sem">
                        {{ sem }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <Label for="school_year">School Year *</Label>
                    <select
                      id="school_year"
                      v-model="form.school_year"
                      class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg"
                      :class="{ 'border-red-500': form.errors.school_year }"
                      required
                    >
                      <option value="">Select</option>
                      <option v-for="sy in schoolYears" :key="sy" :value="sy">
                        {{ sy }}
                      </option>
                    </select>
                  </div>
                </div>

                <!-- Notes (using regular textarea) -->
                <div>
                  <Label for="notes">Notes (Optional)</Label>
                  <textarea
                    id="notes"
                    v-model="form.notes"
                    placeholder="Additional information about this curriculum..."
                    rows="3"
                    class="w-full mt-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                  />
                </div>
              </CardContent>
            </Card>

            <!-- Fee Structure -->
            <Card>
              <CardHeader>
                <CardTitle>Fee Structure</CardTitle>
                <CardDescription>
                  Configure tuition, lab, and miscellaneous fees
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <Label for="tuition_per_unit">Tuition per Unit *</Label>
                    <Input
                      id="tuition_per_unit"
                      v-model.number="form.tuition_per_unit"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                    />
                  </div>

                  <div>
                    <Label for="lab_fee">Laboratory Fee (per subject) *</Label>
                    <Input
                      id="lab_fee"
                      v-model.number="form.lab_fee"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                    />
                  </div>

                  <div>
                    <Label for="registration_fee">Registration Fee *</Label>
                    <Input
                      id="registration_fee"
                      v-model.number="form.registration_fee"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                    />
                  </div>

                  <div>
                    <Label for="misc_fee">Miscellaneous Fee *</Label>
                    <Input
                      id="misc_fee"
                      v-model.number="form.misc_fee"
                      type="number"
                      step="0.01"
                      min="0"
                      required
                      disabled
                      class="bg-gray-50"
                    />
                    <p class="text-xs text-gray-500 mt-1">Auto-calculated from breakdown</p>
                  </div>

                  <div>
                    <Label for="term_count">Payment Terms *</Label>
                    <Input
                      id="term_count"
                      v-model.number="form.term_count"
                      type="number"
                      min="1"
                      max="10"
                      required
                    />
                    <p class="text-xs text-gray-500 mt-1">
                      Number of payment periods (typically 5)
                    </p>
                  </div>
                </div>

                <!-- Miscellaneous Breakdown Info -->
                <Alert>
                  <Info class="h-4 w-4" />
                  <AlertDescription>
                    <strong>Miscellaneous Fee Breakdown ({{ formatCurrency(calculatedMiscFee) }}):</strong>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 mt-2 text-xs">
                      <div v-for="item in miscBreakdown.filter(i => i.included)" :key="item.name" class="flex justify-between">
                        <span>{{ item.name }}:</span>
                        <span class="font-medium">{{ formatCurrency(item.amount) }}</span>
                      </div>
                    </div>
                  </AlertDescription>
                </Alert>
              </CardContent>
            </Card>

            <!-- Course Selection -->
            <Card>
              <CardHeader>
                <div class="flex items-center justify-between">
                  <div>
                    <CardTitle>Select Courses</CardTitle>
                    <CardDescription>
                      Choose courses to include in this curriculum
                    </CardDescription>
                  </div>
                  <div class="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      @click="selectAllCourses"
                      :disabled="!availableCourses.length"
                    >
                      Select All
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      @click="deselectAllCourses"
                      :disabled="!form.courses.length"
                    >
                      Deselect All
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div v-if="!form.program_id" class="text-center py-8 text-gray-500">
                  Please select a program first
                </div>
                <div v-else-if="loadingCourses" class="text-center py-8 text-gray-500">
                  Loading courses...
                </div>
                <div v-else-if="!availableCourses.length" class="text-center py-8 text-gray-500">
                  No courses available for this program
                </div>
                <div v-else class="space-y-2">
                  <div
                    v-for="course in availableCourses"
                    :key="course.id"
                    class="flex items-center gap-3 p-3 border rounded-lg hover:bg-gray-50 transition-colors"
                  >
                    <Checkbox
                      :id="`course-${course.id}`"
                      :checked="form.courses.includes(course.id)"
                      @update:checked="() => toggleCourse(course.id)"
                    />
                    <label
                      :for="`course-${course.id}`"
                      class="flex-1 flex items-center justify-between cursor-pointer"
                    >
                      <div>
                        <p class="font-medium">{{ course.code }} - {{ course.title }}</p>
                        <p class="text-sm text-gray-500">
                          Lec: {{ course.lec_units }} • Lab: {{ course.lab_units }} • Total: {{ course.total_units }} units
                          <span v-if="course.has_lab" class="ml-2 text-blue-600">● Lab</span>
                        </p>
                      </div>
                    </label>
                  </div>
                </div>
                <p v-if="form.errors.courses" class="text-sm text-red-600 mt-2">
                  {{ form.errors.courses }}
                </p>
              </CardContent>
            </Card>
          </div>

          <!-- Right Column: Summary -->
          <div class="space-y-6">
            <Card class="sticky top-6">
              <CardHeader>
                <CardTitle class="flex items-center gap-2">
                  <Calculator class="w-5 h-5" />
                  Assessment Summary
                </CardTitle>
              </CardHeader>
              <CardContent class="space-y-4">
                <!-- Course Count -->
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Selected Courses:</span>
                  <span class="font-medium">{{ selectedCourses.length }}</span>
                </div>

                <!-- Total Units -->
                <div class="flex justify-between text-sm">
                  <span class="text-gray-600">Total Units:</span>
                  <span class="font-medium">{{ totalUnits }}</span>
                </div>

                <div class="border-t pt-4 space-y-3">
                  <!-- Tuition -->
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Tuition Fee:</span>
                    <span class="font-medium">{{ formatCurrency(tuitionFee) }}</span>
                  </div>

                  <!-- Lab Fees -->
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Lab Fees ({{ totalLabCourses }} subjects):</span>
                    <span class="font-medium">{{ formatCurrency(labFees) }}</span>
                  </div>

                  <!-- Registration -->
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Registration Fee:</span>
                    <span class="font-medium">{{ formatCurrency(form.registration_fee) }}</span>
                  </div>

                  <!-- Miscellaneous -->
                  <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Miscellaneous Fee:</span>
                    <span class="font-medium">{{ formatCurrency(form.misc_fee) }}</span>
                  </div>
                </div>

                <div class="border-t pt-4">
                  <div class="flex justify-between">
                    <span class="font-semibold text-gray-900">Total Assessment:</span>
                    <span class="font-bold text-lg text-blue-600">
                      {{ formatCurrency(totalAssessment) }}
                    </span>
                  </div>
                </div>

                <!-- Payment Terms -->
                <div class="border-t pt-4">
                  <p class="text-sm font-medium text-gray-700 mb-2">Payment Terms:</p>
                  <div class="space-y-1 text-xs text-gray-600">
                    <div class="flex justify-between">
                      <span>Per Term:</span>
                      <span>{{ formatCurrency(totalAssessment / form.term_count) }}</span>
                    </div>
                  </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                  <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing || !form.program_id || !selectedCourses.length"
                  >
                    <Save class="w-4 h-4 mr-2" />
                    {{ form.processing ? 'Creating...' : 'Create Curriculum' }}
                  </Button>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </form>
    </div>
  </AppLayout>
</template>