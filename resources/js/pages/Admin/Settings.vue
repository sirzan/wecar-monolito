<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CalendarDays, CreditCard, FileText, Info, Mail, PanelBottom, ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { cn } from '@/lib/utils';

defineOptions({ layout: AdminLayout });

interface PaymentGatewayStatus {
    configured: boolean;
    enabled: boolean;
    active: boolean;
    hint: string;
}

const props = defineProps<{
    settings: Record<string, Record<string, unknown>>;
    paymentStatus?: Record<string, PaymentGatewayStatus>;
}>();

const tabs = [
    { key: 'payments', label: 'Pagos', icon: CreditCard },
    { key: 'contact_info', label: 'Contacto', icon: Mail },
    { key: 'about_page', label: 'Nosotros', icon: Info },
    { key: 'footer_config', label: 'Footer', icon: PanelBottom },
    { key: 'warranty_config', label: 'Garantía', icon: ShieldCheck },
    { key: 'certification_config', label: 'Certificaciones', icon: CalendarDays },
    { key: 'legal', label: 'Legal', icon: FileText },
];

const active = ref('payments');

const payments = reactive({
    mercadopago_enabled: Boolean(props.settings.payments?.mercadopago_enabled),
    stripe_enabled: Boolean(props.settings.payments?.stripe_enabled),
    paypal_enabled: Boolean(props.settings.payments?.paypal_enabled),
    spei_enabled: Boolean(props.settings.payments?.spei_enabled),
    tokens_enabled: Boolean(props.settings.payments?.tokens_enabled),
    membership_enabled: Boolean(props.settings.payments?.membership_enabled),
    spei_bank_name: String(props.settings.payments?.spei_bank_name ?? ''),
    spei_clabe: String(props.settings.payments?.spei_clabe ?? ''),
    spei_beneficiary: String(props.settings.payments?.spei_beneficiary ?? 'WeCar tech de México'),
});

const paymentGateways = [
    { key: 'mercadopago', settingKey: 'mercadopago_enabled' as const, label: 'Mercado Pago' },
    { key: 'stripe', settingKey: 'stripe_enabled' as const, label: 'Stripe (Tarjeta)' },
    { key: 'paypal', settingKey: 'paypal_enabled' as const, label: 'PayPal' },
    { key: 'spei', settingKey: 'spei_enabled' as const, label: 'Transferencia SPEI' },
];

const productToggles = [
    { settingKey: 'tokens_enabled' as const, label: 'Paquetes de tokens' },
    { settingKey: 'membership_enabled' as const, label: 'Membresía de búsqueda' },
];

const contact = reactive({
    email: String(props.settings.contact_info?.email ?? ''),
    phone: String(props.settings.contact_info?.phone ?? ''),
    whatsapp: String(props.settings.contact_info?.whatsapp ?? ''),
    address: String(props.settings.contact_info?.address ?? ''),
    hours: String(props.settings.contact_info?.hours ?? ''),
});

const about = reactive({
    title: String(props.settings.about_page?.title ?? ''),
    subtitle: String(props.settings.about_page?.subtitle ?? ''),
    mission: String(props.settings.about_page?.mission ?? ''),
    vision: String(props.settings.about_page?.vision ?? ''),
    values: String(props.settings.about_page?.values ?? ''),
});

const footer = reactive({
    copyright: String(props.settings.footer_config?.copyright ?? ''),
    legal_links_label: String(props.settings.footer_config?.legal_links_label ?? ''),
    show_newsletter: Boolean(props.settings.footer_config?.show_newsletter),
});

const warranty = reactive({
    enabled: Boolean(props.settings.warranty_config?.enabled),
    title: String(props.settings.warranty_config?.title ?? ''),
    description: String(props.settings.warranty_config?.description ?? ''),
    contact_email: String(props.settings.warranty_config?.contact_email ?? ''),
});

type BusinessHour = { enabled: boolean; start: string; end: string };

const weekDays = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const certificationSource = (props.settings.certification_config ?? {}) as Record<string, unknown>;
const storedHours = (certificationSource.business_hours ?? {}) as Record<string, BusinessHour>;

const certification = reactive({
    business_hours: Object.fromEntries(
        weekDays.map((_, index) => {
            const day = storedHours[index];

            return [
                index,
                {
                    enabled: Boolean(day?.enabled),
                    start: String(day?.start ?? '09:00'),
                    end: String(day?.end ?? '18:00'),
                },
            ];
        }),
    ) as Record<number, BusinessHour>,
    duration_minutes: Number(certificationSource.duration_minutes ?? 60),
    slot_interval_minutes: Number(certificationSource.slot_interval_minutes ?? 60),
    capacity_per_slot: Number(certificationSource.capacity_per_slot ?? 1),
    min_notice_hours: Number(certificationSource.min_notice_hours ?? 24),
    closed_dates_text: Array.isArray(certificationSource.closed_dates)
        ? certificationSource.closed_dates
              .map((entry) => (typeof entry === 'string' ? entry : (entry as { date?: string })?.date))
              .filter(Boolean)
              .join('\n')
        : '',
});

const saveCertification = () => {
    save('certification_config', {
        business_hours: certification.business_hours,
        duration_minutes: certification.duration_minutes,
        slot_interval_minutes: certification.slot_interval_minutes,
        capacity_per_slot: certification.capacity_per_slot,
        min_notice_hours: certification.min_notice_hours,
        closed_dates: certification.closed_dates_text
            .split(/\r?\n/)
            .map((date) => date.trim())
            .filter((date) => /^\d{4}-\d{2}-\d{2}$/.test(date)),
    });
};

const privacy = reactive({
    title: String(props.settings.privacy_policy?.title ?? ''),
    effective_date: String(props.settings.privacy_policy?.effective_date ?? ''),
    content: String(props.settings.privacy_policy?.content ?? ''),
});

const terms = reactive({
    title: String(props.settings.terms_conditions?.title ?? ''),
    effective_date: String(props.settings.terms_conditions?.effective_date ?? ''),
    content: String(props.settings.terms_conditions?.content ?? ''),
});

const save = (key: string, value: Record<string, any>) => {
    router.patch('/admin/configuracion', { key, value }, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1200px]">
        <h1 class="text-3xl font-semibold tracking-tight">Configuración</h1>
        <p class="mt-1 text-sm text-muted-foreground">Ajustes del sitio, pagos y contenido legal.</p>

        <div class="mt-5 flex flex-wrap gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                :class="cn('inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold', active === tab.key ? 'bg-primary text-primary-foreground' : 'bg-card text-muted-foreground hover:text-foreground')"
                @click="active = tab.key"
            >
                <component :is="tab.icon" class="h-4 w-4" /> {{ tab.label }}
            </button>
        </div>

        <div class="mt-6 rounded-2xl border border-border bg-card/50 p-6">
            <template v-if="active === 'payments'">
                <h2 class="font-semibold">Métodos de pago</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Activa o desactiva pasarelas. Solo se mostrarán a los clientes los métodos habilitados que tengan credenciales o datos completos configurados en el servidor.
                </p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="gateway in paymentGateways"
                        :key="gateway.key"
                        class="flex items-start justify-between gap-3 rounded-xl border border-border p-4 text-sm"
                    >
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ gateway.label }}</span>
                                <span
                                    v-if="paymentStatus?.[gateway.key]"
                                    :class="cn(
                                        'rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        paymentStatus[gateway.key].configured
                                            ? 'bg-emerald-500/10 text-emerald-600'
                                            : 'bg-amber-500/15 text-amber-700',
                                    )"
                                >
                                    {{ paymentStatus[gateway.key].configured ? 'Configurado' : 'Sin credenciales' }}
                                </span>
                            </div>
                            <p
                                v-if="paymentStatus?.[gateway.key] && !paymentStatus[gateway.key].configured"
                                class="text-xs text-muted-foreground"
                            >
                                {{ paymentStatus[gateway.key].hint }}
                            </p>
                        </div>
                        <input v-model="payments[gateway.settingKey]" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0" />
                    </label>
                </div>

                <h3 class="mt-6 text-sm font-semibold">Productos habilitados</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label
                        v-for="toggle in productToggles"
                        :key="toggle.settingKey"
                        class="flex items-center justify-between rounded-xl border border-border p-4 text-sm"
                    >
                        <span>{{ toggle.label }}</span>
                        <input v-model="payments[toggle.settingKey]" type="checkbox" class="h-5 w-5" />
                    </label>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <div><Label>Banco SPEI</Label><Input v-model="payments.spei_bank_name" class="mt-1.5" placeholder="Nombre del banco" /></div>
                    <div><Label>CLABE SPEI</Label><Input v-model="payments.spei_clabe" inputmode="numeric" maxlength="18" class="mt-1.5" placeholder="18 dígitos" /></div>
                    <div class="sm:col-span-2"><Label>Beneficiario SPEI</Label><Input v-model="payments.spei_beneficiary" class="mt-1.5" /></div>
                </div>
                <Button class="mt-5" @click="save('payments', { ...payments })">Guardar pagos</Button>
            </template>

            <template v-else-if="active === 'contact_info'">
                <h2 class="font-semibold">Información de contacto</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div><Label>Email</Label><Input v-model="contact.email" class="mt-1.5" /></div>
                    <div><Label>Teléfono</Label><Input v-model="contact.phone" class="mt-1.5" /></div>
                    <div><Label>WhatsApp</Label><Input v-model="contact.whatsapp" class="mt-1.5" /></div>
                    <div><Label>Horario</Label><Input v-model="contact.hours" class="mt-1.5" /></div>
                    <div class="sm:col-span-2"><Label>Dirección</Label><Input v-model="contact.address" class="mt-1.5" /></div>
                </div>
                <Button class="mt-5" @click="save('contact_info', { ...contact })">Guardar contacto</Button>
            </template>

            <template v-else-if="active === 'about_page'">
                <h2 class="font-semibold">Página Nosotros</h2>
                <div class="mt-4 space-y-4">
                    <div><Label>Título</Label><Input v-model="about.title" class="mt-1.5" /></div>
                    <div><Label>Subtítulo</Label><Input v-model="about.subtitle" class="mt-1.5" /></div>
                    <div><Label>Misión</Label><textarea v-model="about.mission" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                    <div><Label>Visión</Label><textarea v-model="about.vision" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                    <div><Label>Valores</Label><textarea v-model="about.values" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                </div>
                <Button class="mt-5" @click="save('about_page', { ...about })">Guardar Nosotros</Button>
            </template>

            <template v-else-if="active === 'footer_config'">
                <h2 class="font-semibold">Footer</h2>
                <div class="mt-4 space-y-4">
                    <div><Label>Copyright</Label><Input v-model="footer.copyright" class="mt-1.5" /></div>
                    <div><Label>Etiqueta de links legales</Label><Input v-model="footer.legal_links_label" class="mt-1.5" /></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="footer.show_newsletter" type="checkbox" /> Mostrar newsletter</label>
                </div>
                <Button class="mt-5" @click="save('footer_config', { ...footer })">Guardar footer</Button>
            </template>

            <template v-else-if="active === 'warranty_config'">
                <h2 class="font-semibold">Garantía</h2>
                <div class="mt-4 space-y-4">
                    <label class="flex items-center gap-2 text-sm"><input v-model="warranty.enabled" type="checkbox" /> Garantía habilitada</label>
                    <div><Label>Título</Label><Input v-model="warranty.title" class="mt-1.5" /></div>
                    <div><Label>Descripción</Label><textarea v-model="warranty.description" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                    <div><Label>Email de contacto</Label><Input v-model="warranty.contact_email" class="mt-1.5" /></div>
                </div>
                <Button class="mt-5" @click="save('warranty_config', { ...warranty })">Guardar garantía</Button>
            </template>

            <template v-else-if="active === 'certification_config'">
                <h2 class="font-semibold">Certificaciones</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Define los horarios de atención del taller, duración del servicio y capacidad por franja. La página
                    pública mostrará disponibilidad únicamente con los horarios activos aquí.
                </p>

                <div class="mt-5 overflow-hidden rounded-xl border border-border">
                    <div
                        v-for="(label, index) in weekDays"
                        :key="index"
                        class="grid items-center gap-3 border-b border-border p-3 last:border-b-0 sm:grid-cols-[140px_auto_1fr_1fr]"
                    >
                        <label class="flex items-center gap-2 text-sm font-semibold">
                            <input v-model="certification.business_hours[index].enabled" type="checkbox" class="h-4 w-4" />
                            {{ label }}
                        </label>
                        <span class="text-xs text-muted-foreground">{{ certification.business_hours[index].enabled ? 'Abierto' : 'Cerrado' }}</span>
                        <Input v-model="certification.business_hours[index].start" type="time" :disabled="!certification.business_hours[index].enabled" />
                        <Input v-model="certification.business_hours[index].end" type="time" :disabled="!certification.business_hours[index].enabled" />
                    </div>
                </div>

                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <Label>Duración del servicio (min)</Label>
                        <Input v-model.number="certification.duration_minutes" type="number" min="15" step="15" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Intervalo entre citas (min)</Label>
                        <Input v-model.number="certification.slot_interval_minutes" type="number" min="15" step="15" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Capacidad por franja</Label>
                        <Input v-model.number="certification.capacity_per_slot" type="number" min="1" class="mt-1.5" />
                    </div>
                    <div>
                        <Label>Anticipación mínima (horas)</Label>
                        <Input v-model.number="certification.min_notice_hours" type="number" min="0" class="mt-1.5" />
                    </div>
                </div>

                <div class="mt-4">
                    <Label>Fechas cerradas</Label>
                    <textarea
                        v-model="certification.closed_dates_text"
                        rows="4"
                        placeholder="Una fecha por línea, formato AAAA-MM-DD"
                        class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm"
                    />
                    <p class="mt-1 text-xs text-muted-foreground">Se usarán para bloquear días festivos o cierres del taller.</p>
                </div>

                <Button class="mt-5" @click="saveCertification">Guardar certificaciones</Button>
            </template>

            <template v-else>
                <h2 class="font-semibold">Documentos legales</h2>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <div class="space-y-3">
                        <p class="text-sm font-bold text-primary">Privacidad</p>
                        <Input v-model="privacy.title" placeholder="Título" />
                        <Input v-model="privacy.effective_date" placeholder="Fecha de vigencia" />
                        <textarea v-model="privacy.content" rows="8" placeholder="Contenido" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                        <Button @click="save('privacy_policy', { ...privacy })">Guardar privacidad</Button>
                    </div>
                    <div class="space-y-3">
                        <p class="text-sm font-bold text-primary">Términos</p>
                        <Input v-model="terms.title" placeholder="Título" />
                        <Input v-model="terms.effective_date" placeholder="Fecha de vigencia" />
                        <textarea v-model="terms.content" rows="8" placeholder="Contenido" class="w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                        <Button @click="save('terms_conditions', { ...terms })">Guardar términos</Button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
