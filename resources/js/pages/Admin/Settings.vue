<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { CreditCard, FileText, Info, Mail, PanelBottom, ShieldCheck } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import { cn } from '@/lib/utils';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    settings: Record<string, Record<string, unknown>>;
}>();

const tabs = [
    { key: 'payments', label: 'Pagos', icon: CreditCard },
    { key: 'contact_info', label: 'Contacto', icon: Mail },
    { key: 'about_page', label: 'Nosotros', icon: Info },
    { key: 'footer_config', label: 'Footer', icon: PanelBottom },
    { key: 'warranty_config', label: 'Garantía', icon: ShieldCheck },
    { key: 'legal', label: 'Legal', icon: FileText },
];

const active = ref('payments');

const payments = reactive({
    stripe_enabled: Boolean(props.settings.payments?.stripe_enabled),
    paypal_enabled: Boolean(props.settings.payments?.paypal_enabled),
    spei_enabled: Boolean(props.settings.payments?.spei_enabled),
    tokens_enabled: Boolean(props.settings.payments?.tokens_enabled),
    membership_enabled: Boolean(props.settings.payments?.membership_enabled),
});

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

const save = (key: string, value: Record<string, string | boolean | number | null>) => {
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
                <p class="mt-1 text-sm text-muted-foreground">Activa o desactiva los métodos visibles en el sitio.</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label v-for="(label, key) in { stripe_enabled: 'Stripe', paypal_enabled: 'PayPal', spei_enabled: 'SPEI', tokens_enabled: 'Paquetes de tokens', membership_enabled: 'Membresía de búsqueda' }" :key="key" class="flex items-center justify-between rounded-xl border border-border p-4 text-sm">
                        <span>{{ label }}</span>
                        <input v-model="payments[key]" type="checkbox" class="h-5 w-5" />
                    </label>
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
