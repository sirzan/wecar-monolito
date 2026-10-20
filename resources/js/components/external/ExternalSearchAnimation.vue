<script setup lang="ts">
import type { ExternalPlatform } from '@/types/external';

defineProps<{ platforms: ExternalPlatform[] }>();
</script>

<template>
    <div class="mx-auto flex w-full max-w-md flex-wrap items-center justify-center gap-2" aria-label="Plataformas de búsqueda inteligente">
        <span
            v-for="platform in platforms"
            :key="platform.id"
            class="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-[11px] font-bold text-secondary shadow-sm"
        >
            <span class="grid h-5 w-5 place-items-center rounded-full text-[8px] font-black text-white" :style="{ backgroundColor: platform.color }">
                {{ platform.initials }}
            </span>
            {{ platform.name }}
        </span>
    </div>
</template>
