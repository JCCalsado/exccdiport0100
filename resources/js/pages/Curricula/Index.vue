<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { 
  BookOpen, 
  Plus, 
  Edit, 
  Eye, 
  Search,
  GraduationCap,
  Trash2,
  PowerOff,
  Power,
  Download
} from 'lucide-vue-next'

interface Program {
  id: number
  code: string
  name: string
  major: string | null
  is_active: boolean
}

interface Course {
  id: number
  code: string
  title: string
  total_units: number
  has_lab: boolean
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
  courses_count: number
  total_units: number
  total_assessment: number
  is_active: boolean
  created_at: string
}

interface Props {
  curricula: {
    data: Curriculum[]
    links: any[]
    current_page: number
    last_page: number
  }
  filters: {
    search?: string
    program?: string
    year_level?: string
    semester?: string
    school_year?: string
  }
  programs: Program[]
  yearLevels: string[]
  semesters: string[]
  schoolYears: string[]
}

const props = defineProps<Props>()

const breadcrumbs = [
  { title: 'Dashboard', href: route('dashboard') },
  { title: 'Curriculum Management' },
]

// State
const search = ref(props.filters.search || '')
const selectedProgram = ref(props.filters.program || '')
const selectedYearLevel = ref(props.filters.year_level || '')
const selectedSemester = ref(props.filters.semester || '')
const selectedSchoolYear = ref(props.filters.school_year || '')
const showDeleteDialog = ref(false)
const curriculumToDelete = ref<number | null>(null)

// Apply filters
const applyFilters = () => {
  router.get(
    route('curricula.index'),
    {
      search: search.value,
      program: selectedProgram.value,
      year_level: selectedYearLevel.value,
      semester: selectedSemester.value,
      school_year: selectedSchoolYear.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
    }
  )
}

// Clear all filters
const clearFilters = () => {
  search.value = ''
  selectedProgram.value = ''
  selectedYearLevel.value = ''
  selectedSemester.value = ''
  selectedSchoolYear.value = ''
  applyFilters()
}

// Toggle curriculum status
const toggleStatus = (curriculumId: number) => {
  router.post(
    route('curricula.toggleStatus', curriculumId),
    {},
    {
      preserveScroll: true,
      onSuccess: () => {
        // Success handled by backend
      },
    }
  )
}

// Delete curriculum
const confirmDelete = (curriculumId: number) => {
  curriculumToDelete.value = curriculumId
  showDeleteDialog.value = true
}

const deleteCurriculum = () => {
  if (curriculumToDelete.value) {
    router.delete(route('curricula.destroy', curriculumToDelete.value), {
      preserveScroll: true,
      onSuccess: () => {
        showDeleteDialog.value = false
        curriculumToDelete.value = null
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

// Get status color
const getStatusColor = (isActive: boolean) => {
  return isActive ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
}

// Stats
const activeCount = computed(() => props.curricula.data.filter(c => c.is_active).length)
const totalPrograms = computed(() => new Set(props.curricula.data.map(c => c.program.id)).size)
</script>

<template>
  <Head title="Curriculum Management" />

  <AppLayout>
    <div class="space-y-6 p-6">
      <Breadcrumbs :items="breadcrumbs" />

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold">Curriculum Management</h1>
          <p class="text-gray-600 mt-2">
            Manage OBE curricula, courses, and assessment structures
          </p>
        </div>
        <Link :href="route('curricula.create')">
          <Button class="flex items-center gap-2">
            <Plus class="w-4 h-4" />
            Create Curriculum
          </Button>
        </Link>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Total Curricula</p>
              <p class="text-2xl font-bold">{{ curricula.data.length }}</p>
            </div>
            <div class="p-3 bg-blue-100 rounded-lg">
              <BookOpen class="w-6 h-6 text-blue-600" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Active Curricula</p>
              <p class="text-2xl font-bold">{{ activeCount }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
              <Power class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Programs</p>
              <p class="text-2xl font-bold">{{ programs.length }}</p>
            </div>
            <div class="p-3 bg-purple-100 rounded-lg">
              <GraduationCap class="w-6 h-6 text-purple-600" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">School Years</p>
              <p class="text-2xl font-bold">{{ schoolYears.length }}</p>
            </div>
            <div class="p-3 bg-orange-100 rounded-lg">
              <BookOpen class="w-6 h-6 text-orange-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-4 rounded-lg border shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
          <!-- Search -->
          <div class="relative md:col-span-2">
            <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" />
            <Input
              v-model="search"
              placeholder="Search curricula..."
              class="pl-10"
              @input="applyFilters"
            />
          </div>

          <!-- Program Filter -->
          <select
            v-model="selectedProgram"
            @change="applyFilters"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Programs</option>
            <option v-for="program in programs" :key="program.id" :value="program.id">
              {{ program.code }}
            </option>
          </select>

          <!-- Year Level Filter -->
          <select
            v-model="selectedYearLevel"
            @change="applyFilters"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Year Levels</option>
            <option v-for="year in yearLevels" :key="year" :value="year">
              {{ year }}
            </option>
          </select>

          <!-- Semester Filter -->
          <select
            v-model="selectedSemester"
            @change="applyFilters"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All Semesters</option>
            <option v-for="sem in semesters" :key="sem" :value="sem">
              {{ sem }}
            </option>
          </select>

          <!-- Clear Filters -->
          <Button
            variant="outline"
            @click="clearFilters"
            class="w-full"
          >
            Clear Filters
          </Button>
        </div>
      </div>

      <!-- Curricula Table -->
      <div class="bg-white rounded-lg border shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Program
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Term
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                  Courses
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                  Units
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                  Assessment
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                  Status
                </th>
                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="curricula.data.length === 0">
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                  <div class="flex flex-col items-center gap-2">
                    <BookOpen class="w-12 h-12 text-gray-300" />
                    <p>No curricula found</p>
                    <Link :href="route('curricula.create')">
                      <Button size="sm" class="mt-2">Create First Curriculum</Button>
                    </Link>
                  </div>
                </td>
              </tr>
              <tr v-for="curriculum in curricula.data" :key="curriculum.id" class="hover:bg-gray-50">
                <td class="px-6 py-4">
                  <div>
                    <p class="font-medium text-gray-900">{{ curriculum.program.code }}</p>
                    <p class="text-sm text-gray-500 truncate max-w-xs">
                      {{ curriculum.program.major }}
                    </p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div>
                    <p class="font-medium text-gray-900">{{ curriculum.year_level }}</p>
                    <p class="text-sm text-gray-500">
                      {{ curriculum.semester }} • {{ curriculum.school_year }}
                    </p>
                  </div>
                </td>
                <td class="px-6 py-4 text-center">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                    {{ curriculum.courses_count }}
                  </span>
                </td>
                <td class="px-6 py-4 text-center text-sm font-medium text-gray-900">
                  {{ curriculum.total_units }}
                </td>
                <td class="px-6 py-4 text-right font-semibold text-gray-900">
                  {{ formatCurrency(curriculum.total_assessment) }}
                </td>
                <td class="px-6 py-4 text-center">
                  <button
                    @click="toggleStatus(curriculum.id)"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full transition-colors"
                    :class="getStatusColor(curriculum.is_active)"
                  >
                    <component :is="curriculum.is_active ? Power : PowerOff" class="w-3 h-3" />
                    {{ curriculum.is_active ? 'Active' : 'Inactive' }}
                  </button>
                </td>
                <td class="px-6 py-4">
                  <div class="flex items-center justify-center gap-2">
                    <Link :href="route('curricula.show', curriculum.id)">
                      <button 
                        class="text-blue-600 hover:text-blue-900 p-2 rounded hover:bg-blue-50 transition-colors"
                        title="View Details"
                      >
                        <Eye class="w-4 h-4" />
                      </button>
                    </Link>
                    <Link :href="route('curricula.edit', curriculum.id)">
                      <button 
                        class="text-green-600 hover:text-green-900 p-2 rounded hover:bg-green-50 transition-colors"
                        title="Edit"
                      >
                        <Edit class="w-4 h-4" />
                      </button>
                    </Link>
                    <button 
                      @click="confirmDelete(curriculum.id)"
                      class="text-red-600 hover:text-red-900 p-2 rounded hover:bg-red-50 transition-colors"
                      title="Delete"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div 
          v-if="curricula.last_page > 1" 
          class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50"
        >
          <div class="text-sm text-gray-600">
            Page {{ curricula.current_page }} of {{ curricula.last_page }}
          </div>
          <div class="flex gap-2">
            <template v-for="(link, index) in curricula.links" :key="index">
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
              <span
                v-else
                :class="[
                  'px-3 py-1 rounded border text-sm',
                  'bg-gray-100 text-gray-400 border-gray-300 cursor-not-allowed'
                ]"
                v-html="link.label"
              />
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <AlertDialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Curriculum</AlertDialogTitle>
          <AlertDialogDescription>
            Are you sure you want to delete this curriculum? This action cannot be undone.
            Students enrolled in this curriculum will not be affected.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction @click="deleteCurriculum" class="bg-red-600 hover:bg-red-700">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>