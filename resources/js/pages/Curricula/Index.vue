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
  Power
} from 'lucide-vue-next'

interface Program {
  id: number
  code: string
  name: string
  major: string | null
  is_active: boolean
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

// Clear filters
const clearFilters = () => {
  search.value = ''
  selectedProgram.value = ''
  selectedYearLevel.value = ''
  selectedSemester.value = ''
  selectedSchoolYear.value = ''
  applyFilters()
}

// Toggle status
const toggleStatus = (curriculumId: number) => {
  router.post(
    route('curricula.toggleStatus', curriculumId),
    {},
    { preserveScroll: true }
  )
}

// Delete curriculum
const confirmDelete = (curriculumId: number) => {
  if (confirm('Are you sure you want to delete this curriculum? This action cannot be undone.')) {
    router.delete(route('curricula.destroy', curriculumId), {
      preserveScroll: true,
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

// Stats
const activeCount = computed(() => props.curricula.data.filter(c => c.is_active).length)
</script>

<template>
  <Head title="Curriculum Management" />

  <AppLayout>
    <div class="min-h-screen bg-gray-50">
      <div class="mx-auto max-w-[1600px] space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <Breadcrumbs :items="breadcrumbs" />

        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
              Curriculum Management
            </h1>
            <p class="mt-2 text-sm text-gray-600">
              Manage OBE curricula, courses, and assessment structures
            </p>
          </div>
          <Link :href="route('curricula.create')">
            <Button class="w-full sm:w-auto">
              <Plus class="mr-2 h-4 w-4" />
              Create Curriculum
            </Button>
          </Link>
        </div>

        <!-- Stats Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Total Curricula</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">
                  {{ props.curricula.data.length }}
                </p>
              </div>
              <div class="rounded-full bg-blue-100 p-3">
                <BookOpen class="h-6 w-6 text-blue-600" />
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Active</p>
                <p class="mt-2 text-3xl font-bold text-green-600">{{ activeCount }}</p>
              </div>
              <div class="rounded-full bg-green-100 p-3">
                <Power class="h-6 w-6 text-green-600" />
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">Programs</p>
                <p class="mt-2 text-3xl font-bold text-purple-600">{{ props.programs.length }}</p>
              </div>
              <div class="rounded-full bg-purple-100 p-3">
                <GraduationCap class="h-6 w-6 text-purple-600" />
              </div>
            </div>
          </div>

          <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-gray-600">School Years</p>
                <p class="mt-2 text-3xl font-bold text-orange-600">{{ props.schoolYears.length }}</p>
              </div>
              <div class="rounded-full bg-orange-100 p-3">
                <BookOpen class="h-6 w-6 text-orange-600" />
              </div>
            </div>
          </div>
        </div>

        <!-- Filters Card -->
        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <!-- Search -->
            <div class="relative sm:col-span-2">
              <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
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
              class="rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">All Programs</option>
              <option v-for="program in props.programs" :key="program.id" :value="program.id">
                {{ program.code }}
              </option>
            </select>

            <!-- Year Level Filter -->
            <select
              v-model="selectedYearLevel"
              @change="applyFilters"
              class="rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">All Year Levels</option>
              <option v-for="year in props.yearLevels" :key="year" :value="year">
                {{ year }}
              </option>
            </select>

            <!-- Semester Filter -->
            <select
              v-model="selectedSemester"
              @change="applyFilters"
              class="rounded-md border border-gray-300 px-3 py-2 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            >
              <option value="">All Semesters</option>
              <option v-for="sem in props.semesters" :key="sem" :value="sem">
                {{ sem }}
              </option>
            </select>

            <!-- Clear Button -->
            <Button variant="outline" @click="clearFilters" class="w-full">
              Clear Filters
            </Button>
          </div>
        </div>

        <!-- Table Card -->
        <div class="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Program
                  </th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                    Term
                  </th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                    Courses
                  </th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                    Units
                  </th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">
                    Assessment
                  </th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                    Status
                  </th>
                  <th scope="col" class="px-6 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white">
                <tr v-if="props.curricula.data.length === 0">
                  <td colspan="7" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center gap-2">
                      <BookOpen class="h-12 w-12 text-gray-300" />
                      <p class="text-sm text-gray-500">No curricula found</p>
                      <Link :href="route('curricula.create')">
                        <Button size="sm" class="mt-2">Create First Curriculum</Button>
                      </Link>
                    </div>
                  </td>
                </tr>
                <tr v-for="curriculum in props.curricula.data" :key="curriculum.id" class="hover:bg-gray-50">
                  <td class="whitespace-nowrap px-6 py-4">
                    <div class="text-sm">
                      <p class="font-medium text-gray-900">{{ curriculum.program.code }}</p>
                      <p class="text-gray-500">{{ curriculum.program.major }}</p>
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <div class="text-sm">
                      <p class="font-medium text-gray-900">{{ curriculum.year_level }}</p>
                      <p class="text-gray-500">{{ curriculum.semester }} • {{ curriculum.school_year }}</p>
                    </div>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    <span class="inline-flex rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-800">
                      {{ curriculum.courses_count }}
                    </span>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center text-sm font-medium text-gray-900">
                    {{ curriculum.total_units }}
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-semibold text-gray-900">
                    {{ formatCurrency(curriculum.total_assessment) }}
                  </td>
                  <td class="whitespace-nowrap px-6 py-4 text-center">
                    <button
                      @click="toggleStatus(curriculum.id)"
                      :class="[
                        'inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold transition-colors',
                        curriculum.is_active
                          ? 'bg-green-100 text-green-800 hover:bg-green-200'
                          : 'bg-gray-100 text-gray-800 hover:bg-gray-200'
                      ]"
                    >
                      <component :is="curriculum.is_active ? Power : PowerOff" class="h-3 w-3" />
                      {{ curriculum.is_active ? 'Active' : 'Inactive' }}
                    </button>
                  </td>
                  <td class="whitespace-nowrap px-6 py-4">
                    <div class="flex items-center justify-center gap-2">
                      <Link :href="route('curricula.show', curriculum.id)">
                        <button 
                          class="rounded p-2 text-blue-600 transition-colors hover:bg-blue-50 hover:text-blue-900"
                          title="View Details"
                        >
                          <Eye class="h-4 w-4" />
                        </button>
                      </Link>
                      <Link :href="route('curricula.edit', curriculum.id)">
                        <button 
                          class="rounded p-2 text-green-600 transition-colors hover:bg-green-50 hover:text-green-900"
                          title="Edit"
                        >
                          <Edit class="h-4 w-4" />
                        </button>
                      </Link>
                      <button 
                        @click="confirmDelete(curriculum.id)"
                        class="rounded p-2 text-red-600 transition-colors hover:bg-red-50 hover:text-red-900"
                        title="Delete"
                      >
                        <Trash2 class="h-4 w-4" />
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div 
            v-if="props.curricula.last_page > 1" 
            class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6"
          >
            <div class="flex flex-1 justify-between sm:hidden">
              <Link
                v-if="props.curricula.current_page > 1"
                :href="props.curricula.links[0].url"
                class="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Previous
              </Link>
              <Link
                v-if="props.curricula.current_page < props.curricula.last_page"
                :href="props.curricula.links[props.curricula.links.length - 1].url"
                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
              >
                Next
              </Link>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
              <div>
                <p class="text-sm text-gray-700">
                  Page <span class="font-medium">{{ props.curricula.current_page }}</span> of 
                  <span class="font-medium">{{ props.curricula.last_page }}</span>
                </p>
              </div>
              <div>
                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                  <template v-for="(link, index) in props.curricula.links" :key="index">
                    <Link
                      v-if="link.url"
                      :href="link.url"
                      :class="[
                        'relative inline-flex items-center px-4 py-2 text-sm font-medium transition-colors',
                        link.active
                          ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                          : 'text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0',
                        index === 0 && 'rounded-l-md',
                        index === props.curricula.links.length - 1 && 'rounded-r-md'
                      ]"
                      v-html="link.label"
                    />
                    <span
                      v-else
                      :class="[
                        'relative inline-flex items-center px-4 py-2 text-sm font-medium text-gray-400',
                        'ring-1 ring-inset ring-gray-300 cursor-not-allowed',
                        index === 0 && 'rounded-l-md',
                        index === props.curricula.links.length - 1 && 'rounded-r-md'
                      ]"
                      v-html="link.label"
                    />
                  </template>
                </nav>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>