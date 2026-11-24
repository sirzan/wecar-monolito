<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ExternalLink, Search } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Pagination from '@/components/site/Pagination.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({ layout: AdminLayout });

interface WarrantyQuoteItem {
    id: string;
    consecutive: number;
    folio: string;
    created_at: string | null;
    valid_until: string | null;
    status: string;
    full_name: string;
    email: string;
    phone: string | null;
    car: string;
    car_year: number;
    car_km: number | null;
    car_price: number;
    service_fee_rate: number;
    service_fee: number;
    total: number;
    currency: string;
    pdf_url: string;
}

const props = defineProps<{
    quotes: { data: WarrantyQuoteItem[]; current_page: number; last_page: number; total: number };
    filters: Record<string, string>;
    statuses: string[];
}>();

const folio = ref(props.filters.folio ?? '');
const email = ref(props.filters.email ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(value);

const formatRate = (rate: number) => `${(rate * 100).toFixed(2).replace(/\.?0+$/, '')}%`;

const formatDate = (value: string | null) =>
    value ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium' }).format(new Date(value)) : '—';

const applyFilters = () => {
    const params: Record<string, string> = {};

    if (folio.value) params.folio = folio.value;
    if (email.value) params.email = email.value;
    if (from.value) params.from = from.value;
    if (to.value) params.to = to.value;

    router.get('/admin/cotizaciones-garantia', params, { preserveState: true, replace: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Cotizaciones de garantía</h1>
        <p class="mt-1 text-sm text-muted-foreground">{{ quotes.total }} cotizaciones registradas.</p>

        <div class="mt-6 grid gap-3 rounded-2xl border border-border bg-card/50 p-4 md:grid-cols-[1fr_1fr_160px_160px_auto]">
            <Input v-model="folio" placeholder="Folio (WG-000001)" @keyup.enter="applyFilters" />
            <Input v-model="email" placeholder="Correo del cliente" @keyup.enter="applyFilters" />
            <Input v-model="from" type="date" aria-label="Desde" />
            <Input v-model="to" type="date" aria-label="Hasta" />
            <Button @click="applyFilters">
                <Search class="h-4 w-4" />
                Filtrar
            </Button>
        </div>

        <div class="mt-6 overflow-x-auto rounded-2xl border border-border bg-card/50">
            <table class="w-full min-w-[1100px] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="p-4">Folio</th>
                        <th class="p-4">Cliente</th>
                        <th class="p-4">Vehículo</th>
                        <th class="p-4">Precio</th>
                        <th class="p-4">Cargo</th>
                        <th class="p-4">Total</th>
                        <th class="p-4">Vigencia</th>
                        <th class="p-4 text-right">PDF</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="quote in quotes.data" :key="quote.id">
                        <td class="p-4">
                            <p class="font-mono text-xs font-bold text-primary">{{ quote.folio }}</p>
                            <p class="text-xs text-muted-foreground">{{ formatDate(quote.created_at) }}</p>
                        </td>
                        <td class="p-4">
                            <p class="font-medium">{{ quote.full_name }}</p>
                            <p class="text-xs text-muted-foreground">{{ quote.email }}</p>
                            <p v-if="quote.phone" class="text-xs text-muted-foreground">{{ quote.phone }}</p>
                        </td>
                        <td class="p-4">
                            <p>{{ quote.car }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ quote.car_year }}
                                <span v-if="quote.car_km !== null"> · {{ quote.car_km.toLocaleString('es-MX') }} km</span>
                            </p>
                        </td>
                        <td class="p-4 tabular-nums">{{ formatPrice(quote.car_price) }}</td>
                        <td class="p-4 tabular-nums">
                            {{ formatPrice(quote.service_fee) }}
                            <span class="block text-xs text-muted-foreground">{{ formatRate(quote.service_fee_rate) }}</span>
                        </td>
                        <td class="p-4 font-bold tabular-nums">{{ formatPrice(quote.total) }}</td>
                        <td class="p-4 text-xs text-muted-foreground">{{ formatDate(quote.valid_until) }}</td>
                        <td class="p-4 text-right">
                            <Button as="a" :href="quote.pdf_url" target="_blank" rel="noopener noreferrer" variant="ghost" size="sm">
                                <ExternalLink class="h-4 w-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!quotes.data.length">
                        <td colspan="8" class="p-8 text-center text-muted-foreground">Sin cotizaciones registradas.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            :current-page="quotes.current_page"
            :last-page="quotes.last_page"
            base-path="/admin/cotizaciones-garantia"
            :base-query="filters"
        />
    </div>
</template>
