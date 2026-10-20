<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    Building2,
    Camera,
    Car as CarIcon,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleDollarSign,
    ClipboardCheck,
    Crown,
    FileCheck2,
    HelpCircle,
    ImagePlus,
    Info,
    LoaderCircle,
    ShieldCheck,
    Sparkles,
    Star,
    TicketPercent,
    Trash2,
    Upload,
    X,
    Zap,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    brands: { id: string; name: string; slug: string; models: { id: string; name: string }[] }[];
    plans: { id: string; key_name: string; name: string; price: number; max_cars: number; highlight: boolean; duration?: string }[];
    filters: { bodies: string[]; transmissions: string[]; fuels: string[]; colors: string[] };
    seo: { title: string; description: string; canonical: string };
}>();

const page = usePage<{ auth: { user: { id: string; name: string; email: string } | null }; currentAgency: { name: string; slug: string } | null }>();

const step = ref(1);
const steps = [
    { n: 1, label: 'Datos del auto', icon: CarIcon },
    { n: 2, label: 'Precio y detalles', icon: CircleDollarSign },
    { n: 3, label: 'Fotos (hasta 12)', icon: Camera },
    { n: 4, label: 'Elige tu plan', icon: ClipboardCheck },
];

const ANIOS = Array.from({ length: 16 }, (_, i) => new Date().getFullYear() + 1 - i);

const form = useForm({
    brand_id: '',
    model_id: '',
    version: '',
    year: new Date().getFullYear() - 2,
    km: 45000,
    price: 250000,
    description: '',
    transmission: 'Automática',
    fuel: 'Gasolina',
    body: 'Sedán',
    color: 'Blanco',
    city: 'Monterrey, NL',
    state: 'Nuevo León',
    accepts_trade: false,
    trade_preference: '',
    vin: '',
    plan_key: props.plans.find(p => p.price === 0)?.key_name || props.plans[0]?.key_name || 'basico',
    images: [] as File[],
});

// Coupons
const couponCode = ref('');
const appliedCoupon = ref<{ code: string; discountPct: number } | null>(null);
const couponError = ref('');

const applyCoupon = () => {
    couponError.value = '';
    const code = couponCode.value.trim().toUpperCase();
    if (code === 'WECAR10') {
        appliedCoupon.value = { code, discountPct: 10 };
    } else if (code === 'WECAR20') {
        appliedCoupon.value = { code, discountPct: 20 };
    } else {
        couponError.value = 'Cupón no válido o expirado.';
    }
};

const removeCoupon = () => {
    appliedCoupon.value = null;
    couponCode.value = '';
};

const models = computed(() => props.brands.find((brand) => brand.id === form.brand_id)?.models ?? []);
const selectedPlan = computed(() => props.plans.find((plan) => plan.key_name === form.plan_key) ?? null);

const finalPlanPrice = computed(() => {
    if (!selectedPlan.value) return 0;
    const base = selectedPlan.value.price;
    if (!appliedCoupon.value || base <= 0) return base;
    return Math.max(0, Math.round(base * (1 - appliedCoupon.value.discountPct / 100)));
});

// Libro azul synthetic estimate based on year and price
const libroAzulSuggested = computed(() => {
    if (form.price > 0) {
        return {
            min: Math.round(form.price * 0.92),
            max: Math.round(form.price * 1.08),
        };
    }
    return null;
});

const canContinue = computed(() => {
    if (step.value === 1) return form.brand_id !== '' && form.year > 0 && form.transmission !== '';
    if (step.value === 2) return form.price >= 1000 && form.km >= 0 && form.description.length >= 20;
    if (step.value === 3) return form.images.length >= 1;
    return true;
});

const onFiles = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files) return;
    const newFiles = Array.from(input.files);
    // Max 12 images
    const combined = [...form.images, ...newFiles].slice(0, 12);
    form.images = combined;
};

const removeImage = (index: number) => {
    form.images = form.images.filter((_, i) => i !== index);
};

const previewUrls = computed(() => {
    return form.images.map((file) => URL.createObjectURL(file));
});

const formatPrice = (val: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 0 }).format(val);

const submit = () => {
    form.post('/vende', { forceFormData: true });
};
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="bg-gradient-dark py-12 px-4 sm:px-6 text-white text-center">
        <div class="mx-auto max-w-4xl">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/20 px-3.5 py-1 text-xs font-bold text-primary uppercase tracking-wider">
                <Sparkles class="h-3.5 w-3.5" /> Vende sin complicaciones
            </span>
            <h1 class="text-3xl font-black mt-3 sm:text-4xl text-white">Publica tu auto en WeCar</h1>
            <p class="mt-2 text-sm text-white/70 max-w-xl mx-auto">
                Llega a miles de compradores calificados en todo México. Proceso seguro, rápido y transparente.
            </p>
        </div>
    </section>

    <!-- Requiere autenticación -->
    <section v-if="!page.props.auth.user" class="mx-auto max-w-lg px-4 py-20 text-center sm:px-6">
        <div class="rounded-3xl border border-border bg-card p-8 shadow-soft">
            <div class="grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-primary mx-auto">
                <CarIcon class="h-8 w-8" />
            </div>
            <h2 class="mt-6 text-2xl font-black text-secondary">Inicia sesión para publicar</h2>
            <p class="mt-2 text-sm text-muted-foreground">
                Para garantizar la seguridad de compradores y vendedores, necesitas una cuenta activa en WeCar.
            </p>
            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-3">
                <Button as="a" href="/login" variant="outline" size="lg" class="w-full sm:w-auto">
                    Iniciar sesión
                </Button>
                <Button as="a" href="/registro" variant="hero" size="lg" class="w-full sm:w-auto shadow-elegant">
                    Crear cuenta gratis
                </Button>
            </div>
        </div>
    </section>

    <!-- Wizard Form -->
    <section v-else class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        <!-- Indicador de agencia si aplica -->
        <div v-if="page.props.currentAgency" class="mb-6 flex items-center gap-3 rounded-2xl border border-primary/40 bg-accent/50 p-4 text-sm text-secondary">
            <Building2 class="h-5 w-5 text-primary shrink-0" />
            <p>
                Publicando como agencia: <strong>{{ page.props.currentAgency.name }}</strong>. El auto se registrará directamente en tu inventario.
            </p>
        </div>

        <!-- Barra de Progreso de 4 Pasos -->
        <div class="mb-10">
            <ol class="grid grid-cols-4 gap-2 sm:gap-4">
                <li
                    v-for="item in steps"
                    :key="item.n"
                    class="flex flex-col items-center gap-2 text-center cursor-pointer"
                    @click="item.n < step ? step = item.n : null"
                >
                    <div
                        class="grid h-11 w-11 place-items-center rounded-2xl border-2 transition-all font-black text-sm"
                        :class="step === item.n
                            ? 'border-primary bg-primary text-primary-foreground shadow-soft scale-105'
                            : step > item.n
                            ? 'border-emerald-500 bg-emerald-500 text-white'
                            : 'border-border bg-card text-muted-foreground'"
                    >
                        <Check v-if="step > item.n" class="h-5 w-5" />
                        <component :is="item.icon" v-else class="h-5 w-5" />
                    </div>
                    <span
                        class="text-[11px] font-bold tracking-tight sm:text-xs"
                        :class="step >= item.n ? 'text-secondary' : 'text-muted-foreground'"
                    >
                        {{ item.label }}
                    </span>
                </li>
            </ol>
        </div>

        <form @submit.prevent="submit">
            <!-- ════════════ PASO 1: DATOS DEL VEHÍCULO ════════════ -->
            <div v-if="step === 1" class="rounded-3xl border border-border bg-card p-6 sm:p-8 shadow-soft space-y-6">
                <div>
                    <h2 class="text-xl font-black text-secondary">Paso 1: Identificación del auto</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Ingresa los datos generales de tu vehículo.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>Marca *</Label>
                        <select
                            v-model="form.brand_id"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none"
                            @change="form.model_id = ''"
                        >
                            <option value="">Selecciona la marca</option>
                            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <div>
                        <Label>Modelo *</Label>
                        <select
                            v-model="form.model_id"
                            :disabled="!form.brand_id"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none disabled:opacity-50"
                        >
                            <option value="">Selecciona el modelo</option>
                            <option v-for="m in models" :key="m.id" :value="m.id">{{ m.name }}</option>
                        </select>
                    </div>

                    <div>
                        <Label>Año *</Label>
                        <select
                            v-model.number="form.year"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none"
                        >
                            <option v-for="a in ANIOS" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>

                    <div>
                        <Label>Versión (opcional)</Label>
                        <Input v-model="form.version" placeholder="Ej. Advance, Touring, XLE" class="mt-1.5 h-11" />
                    </div>

                    <div>
                        <Label>Carrocería</Label>
                        <select
                            v-model="form.body"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none"
                        >
                            <option v-for="body in (filters.bodies.length ? filters.bodies : ['Sedán', 'SUV', 'Hatchback', 'Pickup', 'Coupé'])" :key="body" :value="body">
                                {{ body }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <Label>Transmisión</Label>
                        <select
                            v-model="form.transmission"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none"
                        >
                            <option value="Automática">Automática</option>
                            <option value="Manual">Manual</option>
                            <option value="CVT">CVT</option>
                        </select>
                    </div>

                    <div>
                        <Label>Combustible</Label>
                        <select
                            v-model="form.fuel"
                            class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm focus:border-primary outline-none"
                        >
                            <option value="Gasolina">Gasolina</option>
                            <option value="Híbrido">Híbrido</option>
                            <option value="Diésel">Diésel</option>
                            <option value="Eléctrico">Eléctrico</option>
                        </select>
                    </div>

                    <div>
                        <Label>Color exterior</Label>
                        <Input v-model="form.color" placeholder="Ej. Blanco perla, Gris oxford" class="mt-1.5 h-11" />
                    </div>

                    <div>
                        <Label>Número de serie / VIN (opcional)</Label>
                        <Input v-model="form.vin" placeholder="17 caracteres del VIN" maxlength="17" class="mt-1.5 h-11" />
                    </div>

                    <div class="flex items-center gap-3 pt-6">
                        <input
                            id="accepts_trade"
                            v-model="form.accepts_trade"
                            type="checkbox"
                            class="h-5 w-5 rounded border-gray-300 text-primary focus:ring-primary"
                        />
                        <Label for="accepts_trade" class="text-xs cursor-pointer font-bold">
                            ¿Aceptas auto a cuenta o cambio?
                        </Label>
                    </div>
                </div>

                <div v-if="form.accepts_trade" class="mt-2">
                    <Label>Preferencia de cambio</Label>
                    <Input v-model="form.trade_preference" placeholder="Ej. Acepto sedán menor valor y diferencia a favor" class="mt-1.5 h-11" />
                </div>
            </div>

            <!-- ════════════ PASO 2: PRECIO Y CONDICIÓN ════════════ -->
            <div v-if="step === 2" class="rounded-3xl border border-border bg-card p-6 sm:p-8 shadow-soft space-y-6">
                <div>
                    <h2 class="text-xl font-black text-secondary">Paso 2: Precio y descripción</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Define el valor de tu auto y cuéntanos sobre su estado.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label>Kilometraje actual *</Label>
                        <div class="relative mt-1.5">
                            <Input v-model.number="form.km" type="number" min="0" class="h-11 pr-12" />
                            <span class="absolute right-3.5 top-3 text-xs font-bold text-muted-foreground">km</span>
                        </div>
                    </div>

                    <div>
                        <Label>Precio deseado de venta (MXN) *</Label>
                        <div class="relative mt-1.5">
                            <Input v-model.number="form.price" type="number" min="1000" class="h-11 pr-12" />
                            <span class="absolute right-3.5 top-3 text-xs font-bold text-muted-foreground">MXN</span>
                        </div>
                    </div>

                    <div>
                        <Label>Ciudad *</Label>
                        <Input v-model="form.city" placeholder="Ej. Monterrey, NL" class="mt-1.5 h-11" />
                    </div>

                    <div>
                        <Label>Estado *</Label>
                        <Input v-model="form.state" placeholder="Ej. Nuevo León" class="mt-1.5 h-11" />
                    </div>
                </div>

                <!-- Libro Azul Hint -->
                <div v-if="libroAzulSuggested" class="rounded-2xl border border-primary/30 bg-accent/50 p-4 text-xs text-secondary flex items-start gap-3">
                    <Info class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold">Estimación de referencia sugerida:</p>
                        <p class="mt-0.5 text-muted-foreground">
                            Para un auto con este rango de precio, el valor promedio en el mercado suele situarse entre
                            <strong>{{ formatPrice(libroAzulSuggested.min) }}</strong> y
                            <strong>{{ formatPrice(libroAzulSuggested.max) }}</strong>. Un precio competitivo acelera la venta en un 40%.
                        </p>
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center">
                        <Label>Descripción detallada del vehículo *</Label>
                        <span class="text-[11px] text-muted-foreground" :class="form.description.length < 20 ? 'text-destructive font-bold' : ''">
                            {{ form.description.length }} / mín. 20 caracteres
                        </span>
                    </div>
                    <textarea
                        v-model="form.description"
                        rows="5"
                        placeholder="Menciona servicios de mantenimiento, equipamiento especial, estado de llantas, interiores, si tiene factura original de agencia, etc."
                        class="mt-1.5 w-full rounded-2xl border border-input bg-background p-3.5 text-sm outline-none focus:border-primary"
                    />
                </div>
            </div>

            <!-- ════════════ PASO 3: FOTOS ════════════ -->
            <div v-if="step === 3" class="rounded-3xl border border-border bg-card p-6 sm:p-8 shadow-soft space-y-6">
                <div>
                    <h2 class="text-xl font-black text-secondary">Paso 3: Fotografías del auto</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Sube hasta 12 fotos nítidas (la primera foto será la portada).</p>
                </div>

                <div class="rounded-2xl border-2 border-dashed border-border bg-muted/20 p-8 text-center hover:border-primary/50 transition">
                    <Upload class="h-10 w-10 text-muted-foreground mx-auto" />
                    <p class="mt-3 text-sm font-bold text-secondary">Arrastra y suelta tus fotos aquí, o selecciónalas</p>
                    <p class="mt-1 text-xs text-muted-foreground">Formatos JPG, PNG o WebP. Máximo 8 MB por imagen.</p>
                    <label class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-xs font-bold text-primary-foreground shadow-soft cursor-pointer hover:bg-primary/90 transition">
                        <ImagePlus class="h-4 w-4" /> Seleccionar fotos
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFiles" />
                    </label>
                </div>

                <div v-if="form.images.length" class="mt-6">
                    <p class="text-xs font-bold text-muted-foreground mb-3">
                        Fotos seleccionadas ({{ form.images.length }} de 12):
                    </p>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div
                            v-for="(url, idx) in previewUrls"
                            :key="idx"
                            class="relative aspect-[4/3] rounded-2xl overflow-hidden border border-border bg-muted group shadow-soft"
                        >
                            <img :src="url" alt="Preview" class="h-full w-full object-cover" />
                            <span
                                v-if="idx === 0"
                                class="absolute left-2 top-2 rounded-md bg-primary px-2 py-0.5 text-[10px] font-black text-primary-foreground shadow-soft"
                            >
                                Portada
                            </span>
                            <button
                                type="button"
                                class="absolute right-2 top-2 grid h-7 w-7 place-items-center rounded-full bg-black/70 text-white opacity-0 group-hover:opacity-100 transition hover:bg-destructive"
                                @click="removeImage(idx)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ════════════ PASO 4: PLANES Y PUBLICACIÓN ════════════ -->
            <div v-if="step === 4" class="rounded-3xl border border-border bg-card p-6 sm:p-8 shadow-soft space-y-6">
                <div>
                    <h2 class="text-xl font-black text-secondary">Paso 4: Elige tu plan de publicación</h2>
                    <p class="text-xs text-muted-foreground mt-0.5">Selecciona el nivel de visibilidad para tu anuncio.</p>
                </div>

                <div class="grid gap-5 md:grid-cols-3">
                    <div
                        v-for="plan in plans.filter(p => !p.key_name.startsWith('search_') && !p.key_name.startsWith('membresia_'))"
                        :key="plan.id"
                        class="rounded-3xl border p-6 cursor-pointer transition-all duration-300 relative flex flex-col justify-between"
                        :class="form.plan_key === plan.key_name
                            ? 'border-primary ring-2 ring-primary/40 bg-accent/40 shadow-elegant -translate-y-1'
                            : 'border-border bg-card hover:border-primary/40'"
                        @click="form.plan_key = plan.key_name"
                    >
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black uppercase tracking-wider text-primary">{{ plan.name }}</span>
                                <span
                                    class="grid h-5 w-5 place-items-center rounded-full border"
                                    :class="form.plan_key === plan.key_name ? 'border-primary bg-primary text-primary-foreground' : 'border-border'"
                                >
                                    <Check v-if="form.plan_key === plan.key_name" class="h-3 w-3" />
                                </span>
                            </div>
                            <p class="mt-3 text-3xl font-black text-secondary">
                                {{ plan.price === 0 ? 'Gratis' : formatPrice(plan.price) }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ plan.duration || '30 días' }} de exposición activa</p>
                        </div>

                        <div class="mt-6 border-t border-border/60 pt-4 text-xs space-y-2 text-secondary/80">
                            <p class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-emerald-500 shrink-0" /> Hasta {{ plan.max_cars }} auto publicado
                            </p>
                            <p class="flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4 text-emerald-500 shrink-0" /> Fotos y ficha técnica completa
                            </p>
                            <p v-if="plan.highlight" class="flex items-center gap-2 font-bold text-primary">
                                <Sparkles class="h-4 w-4 text-primary shrink-0" /> Posición destacada en catálogo
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Cupones de descuento para planes de pago -->
                <div v-if="selectedPlan && selectedPlan.price > 0" class="rounded-2xl border border-border bg-muted/30 p-4">
                    <Label class="text-xs font-bold text-secondary">¿Tienes un cupón de descuento?</Label>
                    <div class="mt-2 flex gap-2 max-w-sm">
                        <Input
                            v-model="couponCode"
                            placeholder="Ej. WECAR10"
                            class="h-10 text-xs uppercase"
                            :disabled="appliedCoupon !== null"
                        />
                        <Button
                            v-if="!appliedCoupon"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="applyCoupon"
                        >
                            Aplicar
                        </Button>
                        <Button
                            v-else
                            type="button"
                            variant="destructive"
                            size="sm"
                            @click="removeCoupon"
                        >
                            Quitar
                        </Button>
                    </div>
                    <p v-if="appliedCoupon" class="mt-2 text-xs font-bold text-emerald-600 flex items-center gap-1.5">
                        <CheckCircle2 class="h-3.5 w-3.5" /> Cupón {{ appliedCoupon.code }} aplicado ({{ appliedCoupon.discountPct }}% de descuento).
                    </p>
                    <p v-if="couponError" class="mt-2 text-xs font-bold text-destructive">
                        {{ couponError }}
                    </p>
                </div>

                <!-- Resumen final de cobro -->
                <div class="rounded-2xl bg-secondary p-5 text-white flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div>
                        <p class="text-xs text-white/70">Total a pagar al publicar:</p>
                        <p class="text-2xl font-black text-primary">
                            {{ finalPlanPrice === 0 ? 'Gratis' : formatPrice(finalPlanPrice) }}
                        </p>
                        <p v-if="finalPlanPrice > 0" class="text-[11px] text-white/60">
                            Se abrirá la pasarela segura (Stripe / PayPal / SPEI) tras confirmar.
                        </p>
                    </div>

                    <Button
                        type="submit"
                        variant="hero"
                        size="lg"
                        class="w-full sm:w-auto shadow-elegant font-bold gap-2"
                        :disabled="form.processing"
                    >
                        <LoaderCircle v-if="form.processing" class="h-5 w-5 animate-spin" />
                        <span v-else>Confirmar y publicar auto</span>
                    </Button>
                </div>
            </div>

            <!-- Navegación entre pasos -->
            <div class="mt-8 flex justify-between items-center">
                <Button
                    v-if="step > 1"
                    type="button"
                    variant="outline"
                    class="gap-1.5"
                    @click="step--"
                >
                    <ChevronLeft class="h-4 w-4" /> Anterior
                </Button>
                <div v-else />

                <Button
                    v-if="step < 4"
                    type="button"
                    variant="hero"
                    class="gap-1.5 shadow-elegant"
                    :disabled="!canContinue"
                    @click="step++"
                >
                    Siguiente <ChevronRight class="h-4 w-4" />
                </Button>
            </div>
        </form>
    </section>
</template>
