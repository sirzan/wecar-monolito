<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    BadgeCheck,
    CalendarDays,
    Check,
    ChevronDown,
    FileText,
    MapPin,
    ShieldCheck,
    Wrench,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import AppointmentDialog from '@/components/certificaciones/AppointmentDialog.vue';

defineOptions({ layout: PublicLayout });

interface Pricing {
    base_price: number;
    tax_rate: number;
    tax_amount: number;
    total: number;
    currency: string;
    label: string;
}

interface CertificationInfo {
    service: string;
    points: number;
    timezone: string;
    duration_minutes: number;
    min_notice_hours: number;
    capacity_per_slot: number;
    pricing: Pricing;
    workshop: { name: string; address: string; note: string };
    schedule_configured: boolean;
}

const props = defineProps<{
    brands: { id: string; name: string; slug: string; models: { id: string; name: string }[] }[];
    certification: CertificationInfo;
    profile: { full_name: string; email: string; whatsapp: string; state: string } | null;
    seo: { title: string; description: string; canonical: string };
}>();

const dialogOpen = ref(false);
const openFaq = ref<number | null>(0);

const benefits = [
    { icon: Wrench, title: 'Inspección mecánica', description: '160 puntos revisados por técnicos certificados.' },
    { icon: ShieldCheck, title: 'Validación legal', description: 'REPUVE, ICV, factura y libre de gravámenes.' },
    { icon: FileText, title: 'Reporte detallado', description: 'Recibe un informe completo del estado del auto.' },
];

const included = [
    'Revisión mecánica completa por especialistas.',
    'Verificación legal e historial del vehículo.',
    'Certificado de aprobación para una compra segura.',
];

const faqs = [
    {
        question: '¿Cómo validan el auto mecánicamente?',
        answer: 'Realizamos una inspección de 160 puntos en el taller Motorevs, revisada por técnicos certificados. Al finalizar recibes un reporte con el estado del vehículo.',
    },
    {
        question: '¿Qué incluye la revisión legal?',
        answer: 'Validamos REPUVE, ICV, factura y que el auto esté libre de gravámenes, para que compres con la tranquilidad de saber exactamente qué estás comprando.',
    },
    {
        question: '¿Puedo ver los autos físicamente?',
        answer: 'La revisión se realiza en el Taller Motorevs. Al agendar tu cita coordinamos la visita para que puedas conocer el auto junto con el informe de la revisión.',
    },
    {
        question: '¿Quién prepara los contratos?',
        answer: 'WeCar te acompaña durante el proceso de compra y venta. La formalización del contrato se realiza con las partes involucradas y, cuando corresponde, con la agencia o financiera.',
    },
    {
        question: '¿Hasta dónde llega el acompañamiento?',
        answer: 'Un asesor te guía desde la elección del auto hasta la revisión, la firma del contrato y la entrega de llaves.',
    },
];

const priceFormatter = computed(
    () =>
        new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: props.certification.pricing.currency || 'MXN',
            maximumFractionDigits: 0,
        }),
);

const formatPrice = (value: number) => priceFormatter.value.format(value);

const toggleFaq = (index: number) => {
    openFaq.value = openFaq.value === index ? null : index;
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <!-- Hero -->
    <section class="relative isolate overflow-hidden">
        <img
            src="/images/certificaciones-hero.jpg"
            alt="Revisión mecánica y legal de un automóvil"
            class="absolute inset-0 -z-20 h-full w-full object-cover"
        />
        <div class="absolute inset-0 -z-10 bg-white/80" aria-hidden="true" />

        <div class="mx-auto max-w-5xl px-4 py-16 text-center sm:px-6 sm:py-20 lg:py-24">
            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-primary sm:text-sm">
                Servicio gestionado por talleres certificados por wecar.mx
            </p>
            <h1 class="page-title mt-4 text-secondary">
                Certificaciones <span class="text-primary">WeCar</span>
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                Servicio de revisión mecánica y legal de {{ certification.points }} puntos. Compra con la tranquilidad
                de saber exactamente qué estás comprando.
            </p>
        </div>
    </section>

    <!-- Beneficios -->
    <section class="mx-auto max-w-6xl px-4 pb-14 sm:px-6">
        <div class="grid gap-5 md:grid-cols-3">
            <div
                v-for="benefit in benefits"
                :key="benefit.title"
                class="flex h-full items-start gap-4 rounded-[22px] border border-border bg-card p-6 shadow-soft"
            >
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-primary/10 text-primary">
                    <component :is="benefit.icon" class="h-6 w-6" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-secondary">{{ benefit.title }}</h2>
                    <p class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ benefit.description }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Servicio -->
    <section class="bg-muted/30 px-4 py-14 sm:px-6 lg:py-20">
        <div class="mx-auto max-w-6xl">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Servicio</p>
                <h2 class="section-title mt-3 text-secondary">
                    Revisión <span class="text-primary">mecánica y legal.</span>
                </h2>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground sm:text-base">
                    Validamos cualquier auto que estés considerando, sin necesidad de contratar la garantía completa.
                </p>
            </div>

            <div class="mt-10 overflow-hidden rounded-[28px] border border-border bg-card shadow-elegant md:grid md:grid-cols-[1.35fr_1fr]">
                <div class="p-6 sm:p-9">
                    <span class="inline-flex rounded-full bg-primary/10 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                        Servicio único
                    </span>
                    <h3 class="mt-4 text-2xl font-black text-secondary">{{ certification.service }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-muted-foreground sm:text-base">
                        Inspección de {{ certification.points }} puntos y verificación legal (REPUVE, ICV y más) del
                        auto que quieres comprar.
                    </p>

                    <ul class="mt-6 space-y-4">
                        <li v-for="item in included" :key="item" class="flex items-start gap-3">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-primary text-primary-foreground">
                                <Check class="h-4 w-4" />
                            </span>
                            <span class="text-sm font-medium leading-relaxed text-secondary sm:text-base">{{ item }}</span>
                        </li>
                    </ul>
                </div>

                <div class="flex flex-col justify-center border-t border-border bg-muted/40 p-6 sm:p-9 md:border-l md:border-t-0">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-muted-foreground">Precio</p>
                    <p class="mt-2 text-4xl font-black text-secondary">
                        {{ formatPrice(certification.pricing.base_price) }}
                        <span class="text-base font-bold">{{ certification.pricing.currency }}</span>
                    </p>
                    <p class="mt-1 text-sm font-semibold text-muted-foreground">+ IVA</p>

                    <Button
                        variant="hero"
                        size="lg"
                        class="mt-6 w-full gap-2 rounded-full shadow-glow"
                        @click="dialogOpen = true"
                    >
                        <CalendarDays class="h-5 w-5" /> Agenda ahora
                    </Button>

                    <p class="mt-3 text-center text-[11px] leading-relaxed text-muted-foreground">
                        Cita sujeta a confirmación del taller.
                    </p>

                    <div class="mt-6 flex items-start gap-2 border-t border-border pt-5 text-xs text-muted-foreground">
                        <MapPin class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                        <span>
                            <strong class="text-secondary">{{ certification.workshop.name }}</strong><br />
                            {{ certification.workshop.address }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Preguntas frecuentes -->
    <section class="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:py-20">
        <div class="text-center">
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Preguntas frecuentes</p>
            <h2 class="section-title mt-3 text-secondary">Resolvemos tus <span class="text-primary">dudas.</span></h2>
        </div>

        <div class="mt-8 space-y-3">
            <div
                v-for="(faq, index) in faqs"
                :key="faq.question"
                class="overflow-hidden rounded-[22px] border border-border bg-card shadow-soft"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left sm:px-6"
                    :aria-expanded="openFaq === index"
                    @click="toggleFaq(index)"
                >
                    <span class="text-sm font-semibold text-secondary sm:text-base">{{ faq.question }}</span>
                    <ChevronDown
                        class="h-5 w-5 shrink-0 text-primary transition-transform duration-300"
                        :class="openFaq === index ? 'rotate-180' : ''"
                    />
                </button>
                <div
                    class="grid transition-all duration-300 ease-out"
                    :class="openFaq === index ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                >
                    <div class="overflow-hidden">
                        <p class="px-5 pb-5 text-sm leading-relaxed text-muted-foreground sm:px-6">{{ faq.answer }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-10 flex justify-center">
            <Button variant="hero" size="lg" class="gap-2 rounded-full shadow-glow" @click="dialogOpen = true">
                <BadgeCheck class="h-5 w-5" /> Agendar mi revisión
            </Button>
        </div>
    </section>

    <AppointmentDialog
        :open="dialogOpen"
        :certification="certification"
        :brands="brands"
        :profile="profile"
        @close="dialogOpen = false"
    />
</template>
