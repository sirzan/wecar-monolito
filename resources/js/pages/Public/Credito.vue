<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, Clock, FileCheck, Percent, ShieldCheck } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import FinanciarAutoDialog from '@/components/credito/FinanciarAutoDialog.vue';
import FinancialPartnersCarousel from '@/components/site/FinancialPartnersCarousel.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    financieras: { id: string; slug: string; nombre: string; logo: string | null; tasa_min: number | null; tasa_max: number | null; plazo_max: number | null; enganche_pct: number | null }[];
    brands: { id: string; name: string; slug: string; models: { id: string; name: string }[] }[];
    seo: { title: string; description: string; canonical: string };
}>();

const page = usePage<{ url: string }>();
const priceFromQuery = Number(new URL(page.url, 'http://localhost').searchParams.get('precio') ?? 0);

const price = ref(priceFromQuery > 0 ? priceFromQuery : 280000);
const downPaymentPct = ref(20);
const term = ref(48);
const brand = ref('');
const model = ref('');
const year = ref(new Date().getFullYear() - 2);
const dialogOpen = ref(false);

const models = computed(() => props.brands.find((item) => item.name === brand.value)?.models ?? []);
const downPayment = computed(() => Math.round((price.value * downPaymentPct.value) / 100));
const financed = computed(() => Math.max(price.value - downPayment.value, 0));
const monthly = computed(() => {
    const rate = 0.125 / 12;
    if (financed.value <= 0) return 0;
    return Math.round((financed.value * rate) / (1 - Math.pow(1 + rate, -term.value)));
});

const formatPrice = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(value);

const benefits = [
    { icon: Percent, title: 'Tasas competitivas', description: 'Desde 12.5% anual, sujeto a evaluación y sin sorpresas durante el plazo.' },
    { icon: Clock, title: 'Respuesta rápida', description: 'Aprobación estimada de 24 a 48 hrs, con documentación mínima y todo en línea.' },
    { icon: ShieldCheck, title: 'Plazos y protección', description: 'Hasta 72 meses, enganches desde el 10% y seguro del auto incluido en tu mensualidad.' },
    { icon: FileCheck, title: 'Precalificación sencilla', description: 'Evaluamos tu perfil de forma rápida con IA para darte opciones reales de financiamiento.' },
];
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="relative isolate overflow-hidden px-4 py-10 sm:px-6 sm:py-16 lg:py-24">
        <img src="/images/credito-hero.jpg" alt="Crédito automotriz Wecar" class="absolute inset-0 -z-20 h-full w-full object-cover" />
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-secondary/80 via-secondary/70 to-background" />

        <div class="mx-auto max-w-4xl text-center text-white">
            <p class="text-xs font-bold tracking-wider text-primary sm:text-sm">Crédito automotriz</p>
            <h1 class="page-title text-shadow-hero-soft mt-2 text-white">Tu auto, tu plan.</h1>
            <p class="page-subtitle mt-4 text-white/90">
                Aprobación rápida, mensualidades a tu medida y el mejor financiamiento para ti.
            </p>

            <div class="mx-auto mt-6 max-w-md overflow-hidden rounded-2xl border border-primary/20 bg-card text-left shadow-elegant">
                <div class="p-4 text-foreground sm:p-5">
                    <h2 class="text-lg font-black text-secondary sm:text-xl">Simula tu crédito</h2>
                    <p class="mt-0.5 text-xs text-muted-foreground">Ajusta los valores para estimar tu mensualidad.</p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div>
                            <Label>Año</Label>
                            <Input v-model.number="year" type="number" min="1990" :max="new Date().getFullYear() + 1" class="mt-1.5" />
                        </div>
                        <div>
                            <Label>Marca</Label>
                            <select v-model="brand" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm" @change="model = ''">
                                <option value="">Marca</option>
                                <option v-for="item in brands" :key="item.id" :value="item.name">{{ item.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>Modelo</Label>
                            <select v-model="model" :disabled="!brand" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm disabled:opacity-50">
                                <option value="">Modelo</option>
                                <option v-for="item in models" :key="item.id" :value="item.name">{{ item.name }}</option>
                            </select>
                        </div>
                        <div>
                            <Label>Versión</Label>
                            <Input placeholder="Ej. Sense TM" disabled class="mt-1.5" />
                        </div>
                    </div>

                    <div class="mt-4 space-y-4">
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label>Precio del auto</Label>
                                <span class="text-sm font-black text-secondary">{{ formatPrice(price) }}</span>
                            </div>
                            <input v-model.number="price" type="range" min="100000" max="2000000" step="10000" class="mt-2 w-full accent-[hsl(var(--primary))]" />
                        </div>
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label>Enganche</Label>
                                <span class="text-sm font-black text-secondary">{{ formatPrice(downPayment) }} · {{ downPaymentPct }}%</span>
                            </div>
                            <input v-model.number="downPaymentPct" type="range" min="10" max="80" step="5" class="mt-2 w-full accent-[hsl(var(--primary))]" />
                        </div>
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <Label>Plazo</Label>
                                <span class="text-sm font-black text-secondary">{{ term }} meses</span>
                            </div>
                            <input v-model.number="term" type="range" min="12" max="72" step="12" class="mt-2 w-full accent-[hsl(var(--primary))]" />
                        </div>
                    </div>
                </div>

                <div class="bg-secondary p-4 text-secondary-foreground sm:p-5">
                    <p class="text-[11px] font-bold text-primary">Mensualidad estimada</p>
                    <div class="mt-1 flex items-end gap-2">
                        <strong class="text-3xl font-black sm:text-4xl">{{ formatPrice(monthly) }}</strong>
                        <span class="pb-1 text-xs text-secondary-foreground/70">/ mes</span>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 border-t border-secondary-foreground/15 pt-3">
                        <div>
                            <p class="text-[10px] text-secondary-foreground/60">Tasa desde</p>
                            <p class="mt-0.5 text-sm font-bold">12.5% anual</p>
                        </div>
                        <div>
                            <p class="text-[10px] text-secondary-foreground/60">Monto a financiar</p>
                            <p class="mt-0.5 text-sm font-bold">{{ formatPrice(financed) }}</p>
                        </div>
                    </div>
                    <Button variant="hero" size="lg" class="mt-4 w-full" @click="dialogOpen = true">
                        Completar perfil y comparar
                    </Button>
                    <p class="mt-2.5 text-center text-[11px] text-secondary-foreground/70">
                        Cargo por servicio desde 2.5% · Cálculo referencial sujeto a evaluación.
                    </p>
                </div>
            </div>
        </div>

        <div class="mt-8 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div v-for="(step, index) in ['Cuéntanos de ti', 'Compara ofertas', 'Elige y solicita']" :key="step" class="flex items-center gap-3 rounded-2xl border border-border bg-card/70 px-4 py-3 text-left backdrop-blur">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-primary text-sm font-black text-primary-foreground">{{ index + 1 }}</span>
                <div class="leading-tight">
                    <p class="text-sm font-extrabold text-secondary">{{ step }}</p>
                    <p class="text-xs text-muted-foreground">{{ ['Datos básicos y perfil', 'Bancos y financieras', 'La mejor para tu perfil'][index] }}</p>
                </div>
                <CheckCircle2 class="ml-auto h-4 w-4 text-primary/60" />
            </div>
        </div>

        <div class="mt-8 rounded-2xl border border-white/15 bg-secondary/90 px-4 py-5 shadow-soft backdrop-blur">
            <p class="text-center text-[10px] font-bold uppercase tracking-[0.18em] text-white/90 sm:text-xs">Bancos y financieras aliadas</p>
            <FinancialPartnersCarousel class="mt-3" :inverse="true" />
        </div>
    </section>

    <section class="bg-muted/40 px-4 py-12 sm:px-6 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <div class="text-center">
                <h2 class="section-title text-secondary">Beneficios del <span class="text-primary">crédito de auto</span></h2>
                <p class="section-subtitle mt-3 font-semibold text-muted-foreground">
                    Más de <span class="font-black text-secondary">20 bancos</span> y <span class="font-black text-secondary">30 financieras</span> afiliados
                </p>
            </div>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="benefit in benefits" :key="benefit.title" class="flex items-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:-translate-y-1 hover:shadow-elegant">
                    <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-gradient-primary text-primary-foreground shadow-soft">
                        <component :is="benefit.icon" class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-extrabold text-secondary">{{ benefit.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">{{ benefit.description }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <FinanciarAutoDialog
        :open="dialogOpen"
        :brands="brands"
        :initial="{
            downPayment: downPaymentPct,
            term,
            brand,
            model,
            year,
            price,
        }"
        @close="dialogOpen = false"
    />
</template>
