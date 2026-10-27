<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Pencil, Star, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: AdminLayout });

interface PlanItem {
    id: string;
    key_name: string;
    type: 'publication' | 'membership' | 'tokens';
    audience: 'user' | 'agency' | 'both';
    name: string;
    price: number;
    duration: string;
    duration_weeks: number;
    max_cars: number;
    token_amount: number | null;
    highlight: boolean;
    features: string[] | null;
    sort_order: number;
    is_active: boolean;
}

const props = defineProps<{ plans: PlanItem[] }>();

const editingId = ref<string | null>(null);
const form = reactive({
    key_name: '',
    type: 'publication' as 'publication' | 'membership' | 'tokens',
    audience: 'user' as 'user' | 'agency' | 'both',
    name: '',
    price: 0,
    duration: '30 días',
    duration_weeks: 4,
    max_cars: 1,
    token_amount: 0,
    highlight: false,
    features: '',
    sort_order: 0,
    is_active: true,
});

const reset = () => {
    editingId.value = null;
    Object.assign(form, {
        key_name: '', type: 'publication', audience: 'user', name: '', price: 0, duration: '30 días', duration_weeks: 4,
        max_cars: 1, token_amount: 0, highlight: false, features: '', sort_order: 0, is_active: true,
    });
};

const edit = (plan: PlanItem) => {
    editingId.value = plan.id;
    Object.assign(form, {
        key_name: plan.key_name,
        type: plan.type,
        audience: plan.audience ?? 'user',
        name: plan.name,
        price: plan.price,
        duration: plan.duration,
        duration_weeks: plan.duration_weeks,
        max_cars: plan.max_cars,
        token_amount: plan.token_amount,
        highlight: plan.highlight,
        features: (plan.features ?? []).join('\n'),
        sort_order: plan.sort_order,
        is_active: plan.is_active,
    });
};

const submit = () => {
    const payload = {
        ...form,
        features: form.features.split('\n').map((item) => item.trim()).filter(Boolean),
    };

    if (editingId.value) {
        router.patch(`/admin/planes/${editingId.value}`, payload, { preserveScroll: true, onSuccess: reset });
    } else {
        router.post('/admin/planes', payload, { preserveScroll: true, onSuccess: reset });
    }
};

const destroy = (plan: PlanItem) => {
    if (!window.confirm(`¿Eliminar el plan ${plan.name}?`)) return;
    router.delete(`/admin/planes/${plan.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Planes</h1>
        <p class="mt-1 text-sm text-muted-foreground">Publicación, membresías y paquetes de tokens.</p>

        <div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
            <form class="rounded-2xl border border-border bg-card/50 p-5" @submit.prevent="submit">
                <h2 class="font-semibold">{{ editingId ? 'Editar plan' : 'Nuevo plan' }}</h2>
                <div class="mt-4 space-y-3">
                    <div><Label>Key</Label><Input v-model="form.key_name" class="mt-1.5" placeholder="plan_basico" /></div>
                    <div>
                        <Label>Tipo</Label>
                        <select v-model="form.type" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="publication">Publicación</option>
                            <option value="membership">Membresía</option>
                            <option value="tokens">Tokens</option>
                        </select>
                    </div>
                    <div v-if="form.type === 'publication'">
                        <Label>Audiencia</Label>
                        <select v-model="form.audience" class="mt-1.5 h-10 w-full rounded-md border border-input bg-background px-3 text-sm">
                            <option value="user">Usuarios</option>
                            <option value="agency">Agencias</option>
                            <option value="both">Usuarios y agencias</option>
                        </select>
                    </div>
                    <div><Label>Nombre</Label><Input v-model="form.name" class="mt-1.5" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>Precio</Label><Input v-model.number="form.price" type="number" min="0" class="mt-1.5" /></div>
                        <div><Label>Máx. autos</Label><Input v-model.number="form.max_cars" type="number" min="0" class="mt-1.5" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>Duración</Label><Input v-model="form.duration" class="mt-1.5" /></div>
                        <div><Label>Semanas</Label><Input v-model.number="form.duration_weeks" type="number" min="0" class="mt-1.5" /></div>
                    </div>
                    <div v-if="form.type === 'tokens'"><Label>Cantidad de tokens</Label><Input v-model.number="form.token_amount" type="number" min="1" class="mt-1.5" /></div>
                    <div><Label>Orden</Label><Input v-model.number="form.sort_order" type="number" min="0" class="mt-1.5" /></div>
                    <div>
                        <Label>Beneficios (uno por línea)</Label>
                        <textarea v-model="form.features" rows="3" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" />
                    </div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.highlight" type="checkbox" /> Destacado</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" /> Activo</label>
                </div>
                <div class="mt-5 flex gap-2">
                    <Button type="submit">{{ editingId ? 'Guardar' : 'Crear' }}</Button>
                    <Button v-if="editingId" type="button" variant="ghost" @click="reset">Cancelar</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-2xl border border-border bg-card/50">
                <table class="w-full min-w-[700px] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr><th class="p-4">Plan</th><th class="p-4">Tipo</th><th class="p-4">Audiencia</th><th class="p-4">Precio</th><th class="p-4">Duración</th><th class="p-4">Autos / Tokens</th><th class="p-4">Estado</th><th class="p-4 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="plan in plans" :key="plan.id">
                            <td class="p-4">
                                <p class="font-medium">{{ plan.name }} <Star v-if="plan.highlight" class="inline h-3.5 w-3.5 text-primary" fill="currentColor" /></p>
                                <p class="text-xs text-muted-foreground">{{ plan.key_name }}</p>
                            </td>
                            <td class="p-4"><span class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold uppercase">{{ plan.type }}</span></td>
                            <td class="p-4 text-xs uppercase text-muted-foreground">{{ plan.audience ?? 'user' }}</td>
                            <td class="p-4 tabular-nums">${{ plan.price.toLocaleString('es-MX') }}</td>
                            <td class="p-4 text-muted-foreground">{{ plan.duration }}</td>
                            <td class="p-4 text-muted-foreground">{{ plan.type === 'tokens' ? `${plan.token_amount ?? 0} tokens` : plan.max_cars }}</td>
                            <td class="p-4">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="plan.is_active ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">{{ plan.is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td class="p-4">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="edit(plan)"><Pencil class="h-4 w-4" /></Button>
                                    <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(plan)"><Trash2 class="h-4 w-4" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
