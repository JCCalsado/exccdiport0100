<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { ArrowLeft, User, Phone, GraduationCap, AlertCircle, CheckCircle, Loader2, Info } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import axios from 'axios';

interface Program {
    id: number;
    code: string;
    name: string;
    full_name: string;
    major?: string;
}

interface Course {
    code: string;
    title: string;
    total_units: number;
    lec_units: number;
    lab_units: number;
    has_lab: boolean;
}

interface CurriculumPreview {
    id: number;
    program: string;
    term: string;
    courses: Course[];
    totals: {
        tuition: number;
        lab_fees: number;
        registration_fee: number;
        misc_fee: number;
        total_assessment: number;
    };
}

interface Props {
    programs: Program[];
    legacyCourses: string[];
    yearLevels: string[];
    semesters: string[];
    schoolYears: string[];
}

const props = defineProps<Props>();

const breadcrumbs = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Student Fee Management', href: route('student-fees.index') },
    { title: 'Add Student' },
];

const form = useForm({
    // Personal Information
    last_name: '',
    first_name: '',
    middle_initial: '',
    email: '',
    birthday: '',
    
    // Contact Information
    phone: '',
    address: '',
    
    // Academic Information - OBE
    program_id: null as number | null,
    year_level: '',
    semester: '1st Sem',
    school_year: props.schoolYears[0] || '',
    
    // Academic Information - Legacy
    course: null as string | null, // ✅ FIX: Use null
    
    // Options
    auto_generate_assessment: true,
    student_id: '',
});

const curriculumPreview = ref<CurriculumPreview | null>(null);
const isLoadingPreview = ref(false);
const previewError = ref<string | null>(null);
const useOBECurriculum = ref(true);

// Computed properties
const totalUnits = computed(() => {
    if (!curriculumPreview.value?.courses) return 0;
    return curriculumPreview.value.courses.reduce((sum, course) => 
        sum + (course.total_units || 0), 0
    );
});

const canAutoGenerateAssessment = computed(() => {
    return useOBECurriculum.value && 
           form.program_id !== null && 
           form.year_level !== '' &&
           curriculumPreview.value !== null;
});

const isFormValid = computed(() => {
    const basicInfoValid = form.last_name && form.first_name && form.email && 
                          form.birthday && form.phone && form.address && form.year_level;
    
    if (useOBECurriculum.value) {
        return basicInfoValid && form.program_id !== null;
    } else {
        return basicInfoValid && form.course !== null && form.course !== '';
    }
});

// Curriculum preview fetcher
const fetchCurriculumPreview = async () => {
    if (!form.program_id || !form.year_level || !form.semester || !form.school_year) {
        curriculumPreview.value = null;
        previewError.value = null;
        return;
    }

    isLoadingPreview.value = true;
    previewError.value = null;
    
    try {
        const response = await axios.post(route('student-fees.curriculum.preview'), {
            program_id: form.program_id,
            year_level: form.year_level,
            semester: form.semester,
            school_year: form.school_year,
        });
        
        curriculumPreview.value = response.data.curriculum;
        
        if (!curriculumPreview.value) {
            previewError.value = 'No curriculum found for the selected term.';
        }
    } catch (error: any) {
        console.error('Failed to fetch curriculum preview:', error);
        previewError.value = error.response?.data?.message || 'Failed to load curriculum preview';
        curriculumPreview.value = null;
    } finally {
        isLoadingPreview.value = false;
    }
};

// Watchers
watch(() => form.program_id, (newVal) => {
    if (newVal) {
        useOBECurriculum.value = true;
        form.course = null; // ✅ FIX
        form.auto_generate_assessment = true;
        fetchCurriculumPreview();
    } else {
        curriculumPreview.value = null;
        previewError.value = null;
    }
});

watch([() => form.year_level, () => form.semester, () => form.school_year], () => {
    if (form.program_id && useOBECurriculum.value) {
        fetchCurriculumPreview();
    }
});

watch(() => useOBECurriculum.value, (newVal) => {
    if (newVal) {
        form.course = null; // ✅ FIX
        if (form.program_id) {
            fetchCurriculumPreview();
        }
    } else {
        form.program_id = null;
        form.course = ''; // ✅ Reset to empty string for input
        form.auto_generate_assessment = false;
        curriculumPreview.value = null;
        previewError.value = null;
    }
});

// Methods
const toggleCurriculumMode = () => {
    useOBECurriculum.value = !useOBECurriculum.value;
};

const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
    }).format(amount);
};

const validateForm = (): boolean => {
    if (useOBECurriculum.value) {
        if (!form.program_id) {
            alert('Please select an OBE program');
            return false;
        }
        if (!form.semester || !form.school_year) {
            alert('Semester and School Year are required for OBE students');
            return false;
        }
    } else {
        if (!form.course || form.course === '') {
            alert('Please select a legacy course');
            return false;
        }
    }
    
    if (!form.year_level) {
        alert('Year level is required');
        return false;
    }
    
    return true;
};

const submit = () => {
    if (!validateForm()) {
        return;
    }
    
    // ✅ FIX: Clean up payload before submission
    const payload = { ...form.data() };
    const cleaned = payload as Record<string, any>;
    
    // Remove course if in OBE mode
    if (useOBECurriculum.value) {
        delete cleaned.course;
    } else {
        // Remove OBE fields if in legacy mode
        delete cleaned.program_id;
        delete cleaned.semester;
        delete cleaned.school_year;
        cleaned.auto_generate_assessment = false;
    }
    
    form.transform(() => cleaned).post(route('student-fees.store-student'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
        },
        onError: (errors) => {
            console.error('Form submission errors:', errors);
        },
    });
};
</script>

<template>
    <Head title="Add New Student" />

    <AppLayout>
        <div class="space-y-6 max-w-5xl mx-auto p-6">
            <Breadcrumbs :items="breadcrumbs" />

            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link :href="route('student-fees.index')">
                        <Button variant="outline" size="sm" class="flex items-center gap-2">
                            <ArrowLeft class="w-4 h-4" />
                            Back
                        </Button>
                    </Link>
                    <div>
                        <h1 class="text-3xl font-bold">Add New Student</h1>
                        <p class="text-gray-600 mt-1">
                            Register a new student in the system
                        </p>
                    </div>
                </div>
            </div>

            <!-- Global Error Alert -->
            <Alert v-if="form.errors.error" variant="destructive">
                <AlertCircle class="h-4 w-4" />
                <AlertTitle>Error</AlertTitle>
                <AlertDescription>{{ form.errors.error }}</AlertDescription>
            </Alert>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Personal Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <User class="w-5 h-5" />
                            Personal Information
                        </CardTitle>
                        <CardDescription>Basic information about the student</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Last Name -->
                            <div class="space-y-2">
                                <Label for="last_name" required>Last Name</Label>
                                <Input
                                    id="last_name"
                                    v-model="form.last_name"
                                    required
                                    placeholder="Dela Cruz"
                                    :class="{ 'border-red-500': form.errors.last_name }"
                                />
                                <p v-if="form.errors.last_name" class="text-sm text-red-500">
                                    {{ form.errors.last_name }}
                                </p>
                            </div>

                            <!-- First Name -->
                            <div class="space-y-2">
                                <Label for="first_name" required>First Name</Label>
                                <Input
                                    id="first_name"
                                    v-model="form.first_name"
                                    required
                                    placeholder="Juan"
                                    :class="{ 'border-red-500': form.errors.first_name }"
                                />
                                <p v-if="form.errors.first_name" class="text-sm text-red-500">
                                    {{ form.errors.first_name }}
                                </p>
                            </div>

                            <!-- Middle Initial -->
                            <div class="space-y-2">
                                <Label for="middle_initial">Middle Initial</Label>
                                <Input
                                    id="middle_initial"
                                    v-model="form.middle_initial"
                                    maxlength="10"
                                    placeholder="P."
                                    :class="{ 'border-red-500': form.errors.middle_initial }"
                                />
                                <p v-if="form.errors.middle_initial" class="text-sm text-red-500">
                                    {{ form.errors.middle_initial }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Email -->
                            <div class="space-y-2">
                                <Label for="email" required>Email Address</Label>
                                <Input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    placeholder="student@ccdi.edu.ph"
                                    :class="{ 'border-red-500': form.errors.email }"
                                />
                                <p v-if="form.errors.email" class="text-sm text-red-500">
                                    {{ form.errors.email }}
                                </p>
                            </div>

                            <!-- Birthday -->
                            <div class="space-y-2">
                                <Label for="birthday" required>Birthday</Label>
                                <Input
                                    id="birthday"
                                    v-model="form.birthday"
                                    type="date"
                                    required
                                    :max="new Date().toISOString().split('T')[0]"
                                    :class="{ 'border-red-500': form.errors.birthday }"
                                />
                                <p v-if="form.errors.birthday" class="text-sm text-red-500">
                                    {{ form.errors.birthday }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Contact Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Phone class="w-5 h-5" />
                            Contact Information
                        </CardTitle>
                        <CardDescription>How to reach the student</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Phone -->
                            <div class="space-y-2">
                                <Label for="phone" required>Phone Number</Label>
                                <Input
                                    id="phone"
                                    v-model="form.phone"
                                    required
                                    placeholder="09171234567"
                                    :class="{ 'border-red-500': form.errors.phone }"
                                />
                                <p v-if="form.errors.phone" class="text-sm text-red-500">
                                    {{ form.errors.phone }}
                                </p>
                            </div>

                            <!-- Address -->
                            <div class="space-y-2">
                                <Label for="address" required>Address</Label>
                                <Input
                                    id="address"
                                    v-model="form.address"
                                    required
                                    placeholder="Sorsogon City"
                                    :class="{ 'border-red-500': form.errors.address }"
                                />
                                <p v-if="form.errors.address" class="text-sm text-red-500">
                                    {{ form.errors.address }}
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Academic Information Card -->
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <GraduationCap class="w-5 h-5" />
                                Academic Information
                            </div>
                            <Button 
                                type="button"
                                variant="outline" 
                                size="sm"
                                @click="toggleCurriculumMode"
                            >
                                {{ useOBECurriculum ? 'Switch to Irregular Student' : 'Switch to Regular (OBE) Student' }}
                            </Button>
                        </CardTitle>
                        <CardDescription>
                            {{ useOBECurriculum ? 'OBE Curriculum-based enrollment with automatic assessment generation' : 'Manual course selection for irregular students' }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <!-- OBE Curriculum Mode -->
                        <div v-if="useOBECurriculum" class="space-y-4">
                            <!-- Program Selection -->
                            <div class="space-y-2">
                                <Label for="program_id" required>Program (OBE Curriculum)</Label>
                                <select
                                    id="program_id"
                                    v-model="form.program_id"
                                    required
                                    class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    :class="{ 'border-red-500': form.errors.program_id }"
                                >
                                    <option :value="null">Select Program</option>
                                    <option v-for="program in programs" :key="program.id" :value="program.id">
                                        {{ program.full_name }}
                                    </option>
                                </select>
                                <p v-if="form.errors.program_id" class="text-sm text-red-500">
                                    {{ form.errors.program_id }}
                                </p>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <!-- Year Level -->
                                <div class="space-y-2">
                                    <Label for="year_level" required>Year Level</Label>
                                    <select
                                        id="year_level"
                                        v-model="form.year_level"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.year_level }"
                                    >
                                        <option value="">Select Year Level</option>
                                        <option v-for="level in yearLevels" :key="level" :value="level">
                                            {{ level }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.year_level" class="text-sm text-red-500">
                                        {{ form.errors.year_level }}
                                    </p>
                                </div>

                                <!-- Semester -->
                                <div class="space-y-2">
                                    <Label for="semester" required>Semester</Label>
                                    <select
                                        id="semester"
                                        v-model="form.semester"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option v-for="sem in semesters" :key="sem" :value="sem">
                                            {{ sem }}
                                        </option>
                                    </select>
                                </div>

                                <!-- School Year -->
                                <div class="space-y-2">
                                    <Label for="school_year" required>School Year</Label>
                                    <select
                                        id="school_year"
                                        v-model="form.school_year"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                        <option v-for="sy in schoolYears" :key="sy" :value="sy">
                                            {{ sy }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <!-- Auto-generate Assessment Toggle -->
                            <div class="flex items-center space-x-2 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <input
                                    id="auto_generate"
                                    v-model="form.auto_generate_assessment"
                                    type="checkbox"
                                    class="rounded"
                                    :disabled="!canAutoGenerateAssessment"
                                />
                                <Label for="auto_generate" class="cursor-pointer">
                                    <span class="font-medium">Automatically generate assessment from OBE curriculum</span>
                                    <p class="text-sm text-gray-600 mt-1">
                                        {{ canAutoGenerateAssessment 
                                            ? 'Assessment will be generated based on the curriculum preview below' 
                                            : 'Select all required fields to enable automatic assessment generation' 
                                        }}
                                    </p>
                                </Label>
                            </div>

                            <!-- Curriculum Preview Loading -->
                            <div v-if="isLoadingPreview" class="p-4 bg-gray-50 rounded-lg border">
                                <div class="flex items-center justify-center gap-2">
                                    <Loader2 class="h-5 w-5 animate-spin text-blue-600" />
                                    <p class="text-gray-600">Loading curriculum preview...</p>
                                </div>
                            </div>

                            <!-- Curriculum Preview Success -->
                            <div v-else-if="curriculumPreview" class="p-4 bg-green-50 rounded-lg border border-green-200">
                                <div class="flex items-start gap-2 mb-3">
                                    <CheckCircle class="w-5 h-5 text-green-600 mt-0.5" />
                                    <div>
                                        <h4 class="font-semibold text-green-900">Assessment Preview</h4>
                                        <p class="text-sm text-green-700">Curriculum found for {{ curriculumPreview.term }}</p>
                                    </div>
                                </div>
                                <div class="space-y-2 text-sm">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <p class="text-gray-600">Program:</p>
                                            <p class="font-medium">{{ curriculumPreview.program }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Total Units:</p>
                                            <p class="font-medium">{{ totalUnits }}</p>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 pt-2 mt-2 border-t">
                                        <div>
                                            <p class="text-gray-600">Tuition:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.tuition) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Lab:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.lab_fees) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Registration:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.registration_fee) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600">Misc:</p>
                                            <p class="font-medium">{{ formatCurrency(curriculumPreview.totals.misc_fee) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-gray-600 font-semibold">Total:</p>
                                            <p class="font-bold text-green-700">{{ formatCurrency(curriculumPreview.totals.total_assessment) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Curriculum Preview Error -->
                            <Alert v-if="previewError && useOBECurriculum" variant="warning">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    {{ previewError }}
                                    <br><small>The student will be created without an initial assessment.</small>
                                </AlertDescription>
                            </Alert>

                            <!-- No Preview Yet -->
                            <Alert v-else-if="form.program_id && form.year_level">
                                <Info class="h-4 w-4" />
                                <AlertDescription>
                                    Select all academic details to preview the curriculum and fees.
                                </AlertDescription>
                            </Alert>
                        </div>

                        <!-- Legacy Course Mode -->
                        <div v-else class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Legacy Course -->
                                <div class="space-y-2">
                                    <Label for="course" required>Course (Legacy)</Label>
                                    <select
                                        id="course"
                                        v-model="form.course"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.course }"
                                    >
                                        <option value="">Select Course</option>
                                        <option v-for="course in legacyCourses" :key="course" :value="course">
                                            {{ course }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.course" class="text-sm text-red-500">
                                        {{ form.errors.course }}
                                    </p>
                                </div>

                                <!-- Year Level -->
                                <div class="space-y-2">
                                    <Label for="year_level_legacy" required>Year Level</Label>
                                    <select
                                        id="year_level_legacy"
                                        v-model="form.year_level"
                                        required
                                        class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        :class="{ 'border-red-500': form.errors.year_level }"
                                    >
                                        <option value="">Select Year Level</option>
                                        <option v-for="level in yearLevels" :key="level" :value="level">
                                            {{ level }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.year_level" class="text-sm text-red-500">
                                        {{ form.errors.year_level }}
                                    </p>
                                </div>
                            </div>

                            <Alert>
                                <AlertCircle class="h-4 w-4" />
                                <AlertTitle>Manual Assessment Required</AlertTitle>
                                <AlertDescription>
                                    For irregular students, you'll need to manually create the assessment after registration by selecting individual subjects and fees.
                                </AlertDescription>
                            </Alert>
                        </div>
                    </CardContent>
                </Card>

                <!-- Password Information Alert -->
                <Alert>
                    <Info class="h-4 w-4" />
                    <AlertTitle>Default Login Credentials</AlertTitle>
                    <AlertDescription>
                        The student's initial password will be set to <code class="px-2 py-0.5 bg-gray-100 rounded font-mono">password</code>. 
                        They should change it after their first login for security.
                    </AlertDescription>
                </Alert>

                <!-- Form Actions -->
                <div class="flex items-center justify-end gap-4 pt-4 border-t">
                    <Link :href="route('student-fees.index')">
                        <Button type="button" variant="outline">
                            Cancel
                        </Button>
                    </Link>
                    <Button 
                        type="submit" 
                        :disabled="form.processing || !isFormValid"
                        class="min-w-[200px]"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        <span v-if="form.processing">Adding Student...</span>
                        <span v-else>Add Student</span>
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>