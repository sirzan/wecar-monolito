<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { TicketPercent, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: AdminLayout });

defineProps<{
    recipients: { email: string; coupon_code: string; created_at: string }[];
    couponCode: string;
}>();

const email = ref('');

const add = () => {
    if (!email.value.trim()) return;
    router.post('/admin/cupones', { email: email.value.trim() }, {
        preserveScroll: true,
        onSuccess: () => { email.value = ''; },
    });
};

const remove = (value: string) => {
    if (!window.confirm(`¿Quitar el cupón a ${value}?`)) return;
    router.delete(`/admin/cupones/${encodeURIComponent(value)}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <h1 class="text-3xl font-semibold tracking-tight">Cupones</h1>
        <p class="mt-1 text-sm text-muted-foreground">Correos autorizados para el cupón de descuento en tokens.</p>

        <div class="mt-6 rounded-2xl border border-border bg-card/50 p-6">
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-primary"><TicketPercent class="h-5 w-5" /></span>
                <div>
                    <h2 class="font-semibold">Cupón {{ couponCode }}</h2>
                    <p class="text-sm text-muted-foreground">Solo estos correos verán el 50% de descuento en paquetes de tokens.</p>
                </div>
            </div>

            <form class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="add">
                <div class="flex-1">
                    <Label>Correo electrónico</Label>
                    <Input v-model="email" type="email" placeholder="correo@ejemplo.com" class="mt-1.5" />
                </div>
                <Button type="submit">Agregar</Button>
            </form>

            <div class="mt-5 overflow-hidden rounded-xl border border-border">
                <div v-if="!recipients.length" class="p-4 text-sm text-muted-foreground">Nadie tiene el cupón todavía.</div>
                <div v-for="recipient in recipients" :key="recipient.email" class="flex items-center justify-between border-b border-border p-4 text-sm last:border-b-0">
                    <div>
                        <p class="font-semibold">{{ recipient.email }}</p>
                        <p class="text-xs text-muted-foreground">Agregado el {{ new Date(recipient.created_at).toLocaleDateString('es-MX') }}</p>
                    </div>
                    <Button variant="ghost" size="icon" class="text-destructive" @click="remove(recipient.email)">
                        <Trash2 class="h-4 w-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
