<script setup lang="ts">
import { computed, ref } from 'vue';
import {
    AlertCircle,
    Calendar,
    Car,
    CheckCircle2,
    ChevronDown,
    ClipboardCheck,
    FileCheck2,
    Handshake,
    Headphones,
    HelpCircle,
    Info,
    Percent,
    Search,
    ShieldCheck,
    Sparkles,
    Wallet,
    Wrench,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

defineProps<{
    settings?: { title?: string; description?: string; enabled?: boolean; contact_email?: string };
    seo: { title: string; description: string; canonical: string };
}>();

const includes = [
    {
        icon: Wrench,
        title: 'Validación mecánica de 160 puntos',
        description: 'Diagnóstico por técnicos certificados en motor, transmisión, frenos, suspensión y sistemas electrónicos.',
    },
    {
        icon: FileCheck2,
        title: 'Revisión legal y de documentos',
        description: 'Verificamos factura de origen, historial de tenencias, multas, REPUVE y que el auto esté libre de gravámenes.',
    },
    {
        icon: Handshake,
        title: 'Acompañamiento en el trámite',
        description: 'Un asesor experto te acompaña desde la revisión física hasta la firma del contrato y la entrega de llaves.',
    },
    {
        icon: ShieldCheck,
        title: 'Protección ante vicios ocultos',
        description: 'Cobertura especializada ante fallas mecánicas mayores para que estrenes con total seguridad.',
    },
];

const process = [
    {
        icon: Search,
        title: '1. Elige tu auto',
        description: 'Busca en nuestro catálogo certificado o tráenos el auto que encontraste en cualquier plataforma.',
    },
    {
        icon: Car,
        title: '2. Lo inspeccionamos',
        description: 'Enviamos a nuestros inspectores a realizar la revisión mecánica y legal de 160 puntos.',
    },
    {
        icon: Handshake,
        title: '3. Firma segura',
        description: 'Elaboramos contratos protegidos y gestionamos el pago seguro sin riesgo de fraude.',
    },
    {
        icon: ClipboardCheck,
        title: '4. Entrega garantizada',
        description: 'Recibe tu auto con su certificado oficial de compra segura y garantía activa.',
    },
];

const faqs = [
    {
        q: '¿Qué cubre exactamente la garantía y revisión de WeCar?',
        a: 'Incluye una inspección minuciosa de más de 160 puntos mecánicos, eléctricos y estéticos, revisión exhaustiva de antecedentes legales, facturas y multas, así como acompañamiento presencial o guiado durante el cierre de la compraventa.',
    },
    {
        q: '¿Puedo contratar el servicio para un auto que encontré por mi cuenta?',
        a: '¡Sí! Puedes contratar nuestro servicio de Compra Segura para cualquier auto seminuevo en las ciudades donde operamos. Nosotros contactamos al vendedor, revisamos el auto y te entregamos el reporte.',
    },
    {
        q: '¿Cómo se cotiza el costo del servicio de Compra Segura?',
        a: 'El cargo por servicio parte desde el 2.5% sobre el valor acordado del vehículo, lo cual cubre todo el diagnóstico, gestoría y acompañamiento legal.',
    },
    {
        q: '¿Qué pasa si el auto no pasa la inspección mecánica o legal?',
        a: 'Te entregamos el reporte detallado con las fallas o anomalías encontradas para que no compres un problema. Tú decides si renegociar el precio o buscar otra opción con nuestro catálogo.',
    },
];

// Cotizador de garantía
const autoPrecio = ref(250000);
const cargoServicio = computed(() => Math.round(autoPrecio.value * 0.025));
const totalConServicio = computed(() => autoPrecio.value + cargoServicio.value);

const formatPrice = (val: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(val);

const faqJsonLd = {
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: faqs.map((faq) => ({
        '@type': 'Question',
        name: faq.q,
        acceptedAnswer: { '@type': 'Answer', text: faq.a },
    })),
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />
    <component :is="'script'" type="application/ld+json" v-text="JSON.stringify(faqJsonLd)" />

    <!-- 1. HERO CON SENTRA-2026.JPG -->
    <section class="relative isolate overflow-hidden bg-gradient-hero">
        <div class="absolute inset-0 -z-10">
            <img
                src="/images/sentra-2026.jpg"
                alt="Nissan Sentra 2026 protegido por garantía WeCar"
                class="h-full w-full object-cover object-right opacity-60 sm:opacity-75"
                loading="eager"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-background via-background/85 to-background/20" />
        </div>

        <div class="relative mx-auto grid max-w-7xl grid-cols-1 items-center gap-10 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-accent px-4 py-1.5 text-xs font-bold tracking-wider text-accent-foreground">
                    <span class="h-2 w-2 rounded-full bg-primary animate-pulse" />
                    Garantía y Compra Segura
                </span>

                <h1 class="page-title mt-5 text-secondary text-3xl sm:text-4xl lg:text-5xl">
                    Compra tu auto <br />
                    <span class="text-primary">de forma 100% segura</span>
                </h1>

                <p class="page-subtitle mt-5 max-w-lg font-medium text-secondary/80 text-sm sm:text-base leading-relaxed">
                    Te acompañamos en todo el proceso. Validamos el vehículo mecánica y legalmente para que tomes la mejor decisión sin sorpresas ni fraudes.
                </p>

                <div class="mt-6 inline-flex flex-col items-start gap-1 rounded-2xl border border-primary/30 bg-primary/10 p-4 font-semibold text-secondary shadow-soft">
                    <span class="text-xl sm:text-2xl font-black text-primary">
                        Cargo por servicio desde 2.5%
                    </span>
                    <span class="text-xs text-muted-foreground">
                        Cotizado sobre el valor del auto. Incluye validación de 160 puntos y acompañamiento legal.
                    </span>
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <Button as="a" href="#cotizador" variant="hero" size="lg" class="shadow-elegant">
                        Simula el costo del servicio
                    </Button>
                    <Button as="a" href="/comprar" variant="outline" size="lg">
                        Ver autos verificados
                    </Button>
                </div>
            </div>
        </div>
    </section>

    <!-- 2. QUÉ INCLUYE LA GARANTÍA -->
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6">
        <div class="text-center max-w-2xl mx-auto">
            <span class="text-xs font-bold uppercase tracking-wider text-primary">Protección integral</span>
            <h2 class="section-title mt-2 text-secondary">Qué incluye nuestra garantía</h2>
            <p class="mt-2 text-sm text-muted-foreground">Un estándar de calidad creado para que compres con la misma confianza que en agencia.</p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="item in includes"
                :key="item.title"
                class="rounded-3xl border border-border bg-card p-7 shadow-soft transition-all hover:-translate-y-1 hover:border-primary/40 hover:shadow-elegant flex flex-col justify-between"
            >
                <div>
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary/10 text-primary">
                        <component :is="item.icon" class="h-6 w-6" stroke-width="2.2" />
                    </span>
                    <h3 class="mt-5 text-base font-black text-secondary">{{ item.title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm">{{ item.description }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- 3. CÓMO FUNCIONA EL PROCESO -->
    <section class="border-y border-border bg-secondary px-4 py-16 sm:px-6 text-white">
        <div class="mx-auto max-w-7xl">
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-wider text-primary">Paso a paso</span>
                <h2 class="section-title mt-2 text-white">¿Cómo funciona la compra segura?</h2>
                <p class="mt-2 text-sm text-white/70">Un proceso transparente diseñado para ahorrarte tiempo, dinero y riesgos.</p>
            </div>

            <ol class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <li
                    v-for="item in process"
                    :key="item.title"
                    class="relative rounded-3xl border border-white/10 bg-white/5 p-7 backdrop-blur"
                >
                    <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary text-primary-foreground font-black">
                        <component :is="item.icon" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-5 text-base font-bold text-white">{{ item.title }}</h3>
                    <p class="mt-2 text-xs leading-relaxed text-white/75 sm:text-sm">{{ item.description }}</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- 4. COTIZADOR INTERACTIVO DE SERVICIO -->
    <section id="cotizador" class="mx-auto max-w-3xl px-4 py-16 sm:px-6">
        <div class="rounded-3xl border border-border bg-card p-6 sm:p-10 shadow-soft">
            <div class="text-center max-w-lg mx-auto">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary uppercase">
                    <Sparkles class="h-3.5 w-3.5" /> Cotizador transparente
                </span>
                <h2 class="text-2xl font-black text-secondary mt-3">Cotiza el costo de tu Compra Segura</h2>
                <p class="text-xs text-muted-foreground mt-1">Conoce el cargo exacto de inspección y acompañamiento legal según el precio del auto.</p>
            </div>

            <div class="mt-8 space-y-5">
                <div>
                    <div class="flex justify-between items-center text-sm font-bold">
                        <Label>Precio del auto:</Label>
                        <span class="text-lg font-black text-secondary">{{ formatPrice(autoPrecio) }}</span>
                    </div>
                    <input
                        v-model.number="autoPrecio"
                        type="range"
                        min="50000"
                        max="1500000"
                        step="10000"
                        class="h-2 w-full accent-primary mt-2"
                    />
                    <div class="flex justify-between text-[11px] text-muted-foreground mt-1">
                        <span>$50,000</span>
                        <span>$750,000</span>
                        <span>$1,500,000</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-primary/30 bg-accent/50 p-5 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Cargo por servicio (2.5%):</span>
                        <span class="font-bold text-secondary">{{ formatPrice(cargoServicio) }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Inspección de 160 puntos:</span>
                        <span class="font-bold text-emerald-600">Incluida</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-muted-foreground">Validación legal y de documentos:</span>
                        <span class="font-bold text-emerald-600">Incluida</span>
                    </div>
                    <div class="border-t border-border/80 pt-3 flex justify-between items-center text-base font-black">
                        <span class="text-secondary">Inversión total del auto:</span>
                        <span class="text-xl text-primary">{{ formatPrice(totalConServicio) }}</span>
                    </div>
                </div>

                <Button as="a" href="/contacto" variant="hero" size="lg" class="w-full shadow-elegant font-bold">
                    Solicitar revisión para mi auto
                </Button>
            </div>
        </div>
    </section>

    <!-- 5. PREGUNTAS FRECUENTES -->
    <section class="mx-auto max-w-4xl px-4 pb-16 sm:px-6">
        <h2 class="section-title text-center text-secondary">Preguntas frecuentes</h2>
        <div class="mt-8 space-y-4">
            <details
                v-for="faq in faqs"
                :key="faq.q"
                class="group rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:border-primary/40"
            >
                <summary class="flex cursor-pointer items-center justify-between text-sm sm:text-base font-bold text-secondary list-none">
                    <span>{{ faq.q }}</span>
                    <ChevronDown class="h-4 w-4 text-muted-foreground transition group-open:rotate-180" />
                </summary>
                <p class="mt-3 text-xs sm:text-sm leading-relaxed text-muted-foreground">{{ faq.a }}</p>
            </details>
        </div>
    </section>
</template>
