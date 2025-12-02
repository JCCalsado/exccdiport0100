<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, computed } from 'vue';
// import { route } from 'ziggy-js';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

const backgrounds = [
  "/images/bg11.jpg",
];

const currentIndex = ref(0);
let interval: number;

onMounted(() => {
  interval = window.setInterval(() => {
    currentIndex.value = (currentIndex.value + 1) % backgrounds.length;
  }, 5000);
});

onUnmounted(() => {
  clearInterval(interval);
});

const translateX = computed(() => `-${currentIndex.value * 100}%`);
</script>

<template>
  <Head title="Welcome">
    <link rel="preconnect" href="https://rsms.me/" />
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
  </Head>

  <div class="relative min-h-screen overflow-hidden text-white">
    <!-- Slideshow container -->
    <div
      class="absolute inset-0 flex transition-transform duration-1000 ease-in-out"
      :style="{ transform: `translateX(${translateX})` }"
    >
      <div
        v-for="(bg, index) in backgrounds"
        :key="index"
        class="min-w-full min-h-screen bg-cover bg-center bg-no-repeat"
        :style="{ backgroundImage: `url(${bg})` }"
      />
    </div>

    <!-- Overlay for content -->
    <div class="relative z-10 flex flex-col min-h-screen bg-black/60 p-6 lg:p-8">
      <!-- Top-right nav -->
      <header class="flex justify-end w-full mb-6">
        <nav class="flex items-center gap-4 text-sm">
          <Link
            v-if="page.props.auth.user"
            :href="route('dashboard')"
            class="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 leading-normal text-[#eaeaea] hover:border-white/60 dark:border-[#ddddcb] dark:hover:border-[#ffffff]"
          >
            Dashboard
          </Link>
          <template v-else>
            <Link
              :href="route('login')"
              class="inline-block rounded-sm border border-transparent px-5 py-1.5 leading-normal text-white hover:border-white/50"
            >
              Log in
            </Link>
            <Link
              :href="route('register')"
              class="inline-block rounded-sm border border-white/40 px-5 py-1.5 leading-normal text-white hover:border-white"
            >
              Register
            </Link>
          </template>
        </nav>
      </header>

      <!-- Center content -->
      <main class="flex flex-1 justify-center items-start w-full">
        <div class="px-4 mx-auto max-w-screen-xl text-center py-24 lg:py-56">
          <h1 class="mb-4 text-4xl font-extrabold tracking-tight leading-none text-white drop-shadow-lg md:text-5xl lg:text-6xl">
            Computer Communication Development Institute
          </h1>
          <p class="mb-8 text-lg font-normal text-gray-100 lg:text-xl sm:px-16 lg:px-48 drop-shadow-md">
           CCDI envisions of providing a service of leadership through excellent instructions that will produce empowered and world-class I.T. graduates.
          </p>
          <div class="flex flex-col space-y-4 sm:flex-row sm:justify-center sm:space-y-0">
            <!-- Get Started: dynamic route -->
            <Link
              :href="page.props.auth.user ? route('dashboard') : route('login')"
              class="inline-flex justify-center items-center py-3 px-5 text-base font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-900 shadow"
            >
              Get Started
              <svg
                class="w-3.5 h-3.5 ms-2 rtl:rotate-180 drop-shadow"
                aria-hidden="true"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 14 10"
              >
                <path
                  stroke="currentColor"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M1 5h12m0 0L9 1m4 4L9 9"
                />
              </svg>
            </Link>
            <!-- Learn more: external link -->
            <a
              href="https://www.ccdisorsogon.edu.ph/"
              target="_blank"
              class="inline-flex justify-center items-center py-3 px-5 sm:ms-4 text-base font-medium text-center text-white rounded-lg border border-white hover:bg-white hover:text-black focus:ring-4 focus:ring-white/40 shadow"
            >
              Learn more
            </a>
          </div>
        </div>
      </main>
    </div>
  </div>
</template>