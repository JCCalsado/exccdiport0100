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
  FileText,
  Calculator
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

// Filters
const search = ref(props.filters.search || '')
const selectedProgram = ref(props.filters.program || '')
const selectedYearLevel = ref(props.filters.year_level || '')
const selectedSemester = ref(props.filters.semester || '')
const selectedSchoolYear = ref(props.filters.school_year || '')

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
              <p class="text-sm text-gray-600">Active Programs</p>
              <p class="text-2xl font-bold">{{ programs.length }}</p>
            </div>
            <div class="p-3 bg-green-100 rounded-lg">
              <GraduationCap class="w-6 h-6 text-green-600" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">School Years</p>
              <p class="text-2xl font-bold">{{ schoolYears.length }}</p>
            </div>
            <div class="p-3 bg-purple-100 rounded-lg">
              <FileText class="w-6 h-6 text-purple-600" />
            </div>
          </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600">Avg Assessment</p>
              <p class="text-2xl font-bold">
                {{ formatCurrency(curricula.data.reduce((sum, c) => sum + c.total_assessment, 0) / (curricula.data.length || 1)) }}
              </p>
            </div>
            <div class="p-3 bg-yellow-100 rounded-lg">
              <Calculator class="w-6 h-6 text-yellow-600" />
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="bg-white p-4 rounded-lg border shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
          <!-- Search -->
          <div class="relative">
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
              {{ program.code }} - {{ program.major }}
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

          <!-- School Year Filter -->
          <select
            v-model="selectedSchoolYear"
            @change="applyFilters"
            class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          >
            <option value="">All School Years</option>
            <option v-for="sy in schoolYears" :key="sy" :value="sy">
              {{ sy }}
            </option>
          </select>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Courses
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Total Units
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                  Total Assessment
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                  Status
                </th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-if="curricula.data.length === 0">
                <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                  No curricula found
                </td>
              </tr>
              <tr v-for="curriculum in curricula.data" :key="curriculum.id" class="hover:bg-gray-50">
                <td class="px-6 py-4">
                  <div>
                    <p class="font-medium text-gray-900">{{ curriculum.program.code }}</p>
                    <p class="text-sm text-gray-500">{{ curriculum.program.major }}</p>
                  </div>
                </td>
                <td class="px-6 py-4">
                  <div>
                    <p class="font-medium text-gray-900">{{ curriculum.year_level }}</p>
                    <p class="text-sm text-gray-500">{{ curriculum.semester }} - {{ curriculum.school_year }}</p>
                  </div>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  {{ curriculum.courses_count }} courses
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                  {{ curriculum.total_units }} units
                </td>
                <td class="px-6 py-4 text-right font-medium text-gray-900">
                  {{ formatCurrency(curriculum.total_assessment) }}
                </td>
                <td class="px-6 py-4">
                  <span 
                    class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="getStatusColor(curriculum.is_active)"
                  >
                    {{ curriculum.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="px-6 py-4 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Link :href="route('curricula.show', curriculum.id)">
                      <button 
                        class="text-blue-600 hover:text-blue-900 p-1 rounded hover:bg-blue-50"
                        title="View Details"
                      >
                        <Eye class="w-4 h-4" />
                      </button>
                    </Link>
                    <Link :href="route('curricula.edit', curriculum.id)">
                      <button 
                        class="text-green-600 hover:text-green-900 p-1 rounded hover:bg-green-50"
                        title="Edit"
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