<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { AlertTriangle, Settings, Users } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';

defineOptions({ layout: PanelLayout });

const props = defineProps<{
    agency: { id: string; slug: string; name: string; role?: string | null };
}>();

const base = `/panel/agencia/${props.agency.slug}`;
</script>

<template>
    <div class="mx-auto max-w-3xl">
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Configuración</h1>
        <p class="mt-1 text-sm text-muted-foreground">Administra la configuración de {{ agency.name }}.</p>

        <div class="mt-6 space-y-4">
            <Link :href="`${base}/perfil`" class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:border-primary/40">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-accent-foreground"><Settings class="h-5 w-5" /></span>
                <div>
                    <p class="font-bold text-secondary">Perfil público</p>
                    <p class="text-sm text-muted-foreground">Nombre, descripción, contacto y dirección.</p>
                </div>
            </Link>

            <Link :href="`${base}/equipo`" class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft transition hover:border-primary/40">
                <span class="grid h-10 w-10 place-items-center rounded-xl bg-accent text-accent-foreground"><Users class="h-5 w-5" /></span>
                <div>
                    <p class="font-bold text-secondary">Equipo</p>
                    <p class="text-sm text-muted-foreground">Invita y administra vendedores.</p>
                </div>
            </Link>

            <div class="rounded-2xl border border-destructive/30 bg-destructive/5 p-5">
                <div class="flex items-start gap-3">
                    <AlertTriangle class="mt-0.5 h-5 w-5 text-destructive" />
                    <div>
                        <p class="font-bold text-secondary">Zona de riesgo</p>
                        <p class="text-sm text-muted-foreground">La eliminación de la agencia estará disponible en una fase posterior.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <Button as="a" :href="`${base}/resumen`" variant="outline">Volver al resumen</Button>
        </div>
    </div>
</template>
