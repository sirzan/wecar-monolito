<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        currentPage: number;
        lastPage: number;
        basePath?: string;
        baseQuery?: Record<string, string | undefined>;
    }>(),
    { basePath: '/comprar' },
);

const buildUrl = (page: number) => {
    const params = new URLSearchParams();
    Object.entries(props.baseQuery ?? {}).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') params.set(key, value);
    });
    if (page > 1) params.set('page', String(page));
    const query = params.toString();
    return `${props.basePath}${query ? `?${query}` : ''}`;
};

const pages = () => {
    const total = props.lastPage;
    const current = props.currentPage;
    const range: (number | '...')[] = [];
    for (let i = 1; i <= total; i++) {
        if (i === 1 || i === total || Math.abs(i - current) <= 1) {
            range.push(i);
        } else if (range[range.length - 1] !== '...') {
            range.push('...');
        }
    }
    return range;
};
</script>

<template>
    <nav v-if="lastPage > 1" class="mt-8 flex items-center justify-center gap-1" aria-label="Paginación">
        <Link
            v-if="currentPage > 1"
            :href="buildUrl(currentPage - 1)"
            class="grid h-9 w-9 place-items-center rounded-lg border border-border text-muted-foreground hover:bg-accent"
            aria-label="Anterior"
        >
            <ChevronLeft class="h-4 w-4" />
        </Link>

        <template v-for="(page, index) in pages()" :key="index">
            <span v-if="page === '...'" class="px-2 text-muted-foreground">…</span>
            <Link
                v-else
                :href="buildUrl(page)"
                :class="
                    cn(
                        'grid h-9 min-w-9 place-items-center rounded-lg border border-border px-2 text-sm font-semibold',
                        page === currentPage ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-accent',
                    )
                "
            >
                {{ page }}
            </Link>
        </template>

        <Link
            v-if="currentPage < lastPage"
            :href="buildUrl(currentPage + 1)"
            class="grid h-9 w-9 place-items-center rounded-lg border border-border text-muted-foreground hover:bg-accent"
            aria-label="Siguiente"
        >
            <ChevronRight class="h-4 w-4" />
        </Link>
    </nav>
</template>
