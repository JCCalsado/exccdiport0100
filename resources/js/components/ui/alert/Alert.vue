<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'
import { cva, type VariantProps } from 'class-variance-authority'

const alertVariants = cva(
  'relative w-full rounded-lg border px-4 py-3 text-sm [&>svg+div]:translate-y-[-3px] [&>svg]:absolute [&>svg]:left-4 [&>svg]:top-4 [&>svg]:text-foreground [&>svg~*]:pl-7',
  {
    variants: {
      variant: {
        default: 'bg-background text-foreground',
        info: 'border-blue-200 bg-blue-50 text-blue-900 [&>svg]:text-blue-600',
        destructive: 'border-destructive/50 text-destructive dark:border-destructive [&>svg]:text-destructive',
        success: 'border-green-200 bg-green-50 text-green-900 [&>svg]:text-green-600',
        warning: 'border-yellow-200 bg-yellow-50 text-yellow-900 [&>svg]:text-yellow-600',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

const props = defineProps<{
  class?: HTMLAttributes['class']
  variant?: VariantProps<typeof alertVariants>['variant']
}>()
</script>

<template>
  <div
    data-slot="alert"
    role="alert"
    :class="cn(alertVariants({ variant: props.variant }), props.class)"
  >
    <slot />
  </div>
</template>

<!-- resources/js/components/ui/alert/AlertDescription.vue -->
<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <div
    data-slot="alert-description"
    :class="cn('text-sm [&_p]:leading-relaxed', props.class)"
  >
    <slot />
  </div>
</template>

<!-- resources/js/components/ui/alert/AlertTitle.vue -->
<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

const props = defineProps<{
  class?: HTMLAttributes['class']
}>()
</script>

<template>
  <h5
    data-slot="alert-title"
    :class="cn('mb-1 font-medium leading-none tracking-tight', props.class)"
  >
    <slot />
  </h5>
</template>
