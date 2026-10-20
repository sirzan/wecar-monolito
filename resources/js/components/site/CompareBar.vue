<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { GitCompare, X } from '@lucide/vue';
import { useCompareStore } from '@/stores/compare';
import Button from '@/components/ui/Button.vue';

const compare = useCompareStore();
</script>

<template>
    <div
        v-if="compare.count"
        class="bottom-offset animate-slide-up fixed left-1/2 z-40 w-[min(680px,calc(100vw-2rem))] -translate-x-1/2"
    >
        <div class="flex items-center gap-3 rounded-2xl border border-border bg-card/95 p-3 shadow-elegant backdrop-blur-md">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                <GitCompare class="h-5 w-5" />
            </div>

            <div class="wc-no-scrollbar flex flex-1 items-center gap-2 overflow-x-auto">
                <div
                    v-for="item in compare.items"
                    :key="item.id"
                    class="flex shrink-0 items-center gap-2 rounded-full border border-border bg-background px-3 py-1.5 text-xs font-semibold text-secondary"
                >
                    <img v-if="item.image" :src="item.image" alt="" class="h-6 w-6 rounded-full object-cover" />
                    <span v-else class="grid h-6 w-6 place-items-center rounded-full bg-muted text-[9px] text-muted-foreground">
                        {{ item.title.slice(0, 1) }}
                    </span>
                    <span class="max-w-36 truncate">{{ item.title }}</span>
                    <button
                        type="button"
                        aria-label="Quitar"
                        class="text-muted-foreground hover:text-destructive"
                        @click="compare.remove(item.id)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>

            <Button variant="ghost" size="sm" class="hidden sm:inline-flex" @click="compare.clear">
                Limpiar
            </Button>
            <Button
                as="a"
                :href="`/comparar?ids=${compare.ids.join(',')}`"
                variant="hero"
                size="pill"
                :disabled="compare.count < 2"
                :class="compare.count < 2 ? 'pointer-events-none opacity-50' : ''"
            >
                Comparar ({{ compare.count }})
            </Button>
        </div>
    </div>
</template>
