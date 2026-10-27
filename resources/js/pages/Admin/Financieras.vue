<script setup lang="ts">
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: AdminLayout });

interface FinancieraItem {
    id: string;
    slug: string;
    nombre: string;
    tipo: string;
    vehicle_condition: string;
    brand_scope: string[] | null;
    max_vehicle_age: number | null;
    requires_membership: boolean;
    aplica_para: string | null;
    tasa_min: number | null;
    tasa_max: number | null;
    cat_min: number | null;
    cat_max: number | null;
    enganche_pct: number | null;
    plazo_max: number | null;
    score_min: number | null;
    destacado: boolean;
    contacto: string | null;
    notas: string | null;
    orden: number;
    activo: boolean;
}

const props = defineProps<{ financieras: FinancieraItem[] }>();

const editingId = ref<string | null>(null);
const form = reactive({
    nombre: '',
    slug: '',
    tipo: 'banco',
    vehicle_condition: 'any',
    brand_scope: '',
    max_vehicle_age: '' as number | string,
    requires_membership: false,
    aplica_para: '',
    tasa_min: '' as number | string,
    tasa_max: '' as number | string,
    cat_min: '' as number | string,
    cat_max: '' as number | string,
    enganche_pct: '' as number | string,
    plazo_max: '' as number | string,
    score_min: '' as number | string,
    destacado: false,
    contacto: '',
    notas: '',
    orden: 0,
    activo: true,
});

const reset = () => {
    editingId.value = null;
    Object.assign(form, {
        nombre: '', slug: '', tipo: 'banco', vehicle_condition: 'any', brand_scope: '', max_vehicle_age: '', requires_membership: false, aplica_para: '', tasa_min: '', tasa_max: '', cat_min: '', cat_max: '',
        enganche_pct: '', plazo_max: '', score_min: '', destacado: false, contacto: '', notas: '', orden: 0, activo: true,
    });
};

const edit = (item: FinancieraItem) => {
    editingId.value = item.id;
    Object.assign(form, {
        nombre: item.nombre,
        slug: item.slug,
        tipo: item.tipo,
        vehicle_condition: item.vehicle_condition,
        brand_scope: (item.brand_scope ?? []).join(', '),
        max_vehicle_age: item.max_vehicle_age ?? '',
        requires_membership: item.requires_membership,
        aplica_para: item.aplica_para ?? '',
        tasa_min: item.tasa_min ?? '',
        tasa_max: item.tasa_max ?? '',
        cat_min: item.cat_min ?? '',
        cat_max: item.cat_max ?? '',
        enganche_pct: item.enganche_pct ?? '',
        plazo_max: item.plazo_max ?? '',
        score_min: item.score_min ?? '',
        destacado: item.destacado,
        contacto: item.contacto ?? '',
        notas: item.notas ?? '',
        orden: item.orden,
        activo: item.activo,
    });
};

const submit = () => {
    const payload = { ...form };
    if (editingId.value) {
        router.patch(`/admin/financieras/${editingId.value}`, payload, { preserveScroll: true, onSuccess: reset });
    } else {
        router.post('/admin/financieras', payload, { preserveScroll: true, onSuccess: reset });
    }
};

const destroy = (item: FinancieraItem) => {
    if (!window.confirm(`¿Eliminar ${item.nombre}?`)) return;
    router.delete(`/admin/financieras/${item.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-[1400px]">
        <h1 class="text-3xl font-semibold tracking-tight">Financieras</h1>
        <p class="mt-1 text-sm text-muted-foreground">Bancos y financieras mostradas en el comparador de crédito.</p>

        <div class="mt-6 grid gap-6 lg:grid-cols-[380px_1fr]">
            <form class="rounded-2xl border border-border bg-card/50 p-5" @submit.prevent="submit">
                <h2 class="font-semibold">{{ editingId ? 'Editar financiera' : 'Nueva financiera' }}</h2>
                <div class="mt-4 space-y-3">
                    <div><Label>Nombre</Label><Input v-model="form.nombre" class="mt-1.5" /></div>
                    <div><Label>Slug</Label><Input v-model="form.slug" class="mt-1.5" placeholder="Se genera automáticamente" /></div>
                    <div>
                        <Label>Tipo</Label>
                        <select v-model="form.tipo" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option value="banco">Banco</option>
                            <option value="financiera_marca">Financiera de marca</option>
                            <option value="autofinanciamiento">Autofinanciamiento</option>
                            <option value="prendaria">Prendaria</option>
                            <option value="fintech">Fintech</option>
                        </select>
                    </div>
                    <div>
                        <Label>Condición del auto</Label>
                        <select v-model="form.vehicle_condition" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                            <option value="any">Nuevos y seminuevos</option>
                            <option value="new">Solo nuevos</option>
                            <option value="used">Solo seminuevos</option>
                        </select>
                    </div>
                    <div><Label>Marcas permitidas (slugs separados por coma)</Label><Input v-model="form.brand_scope" class="mt-1.5" placeholder="volkswagen, audi, seat" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>Edad máx. del auto</Label><Input v-model.number="form.max_vehicle_age" type="number" min="0" class="mt-1.5" /></div>
                        <label class="flex items-center gap-2 pt-6 text-sm"><input v-model="form.requires_membership" type="checkbox" /> Requiere membresía</label>
                    </div>
                    <div><Label>Aplica para</Label><Input v-model="form.aplica_para" class="mt-1.5" placeholder="Autos nuevos y seminuevos" /></div>
                    <div class="grid grid-cols-2 gap-3">
                        <div><Label>Tasa mín.</Label><Input v-model.number="form.tasa_min" type="number" step="0.01" class="mt-1.5" /></div>
                        <div><Label>Tasa máx.</Label><Input v-model.number="form.tasa_max" type="number" step="0.01" class="mt-1.5" /></div>
                        <div><Label>CAT mín.</Label><Input v-model.number="form.cat_min" type="number" step="0.01" class="mt-1.5" /></div>
                        <div><Label>CAT máx.</Label><Input v-model.number="form.cat_max" type="number" step="0.01" class="mt-1.5" /></div>
                        <div><Label>Enganche %</Label><Input v-model.number="form.enganche_pct" type="number" min="0" class="mt-1.5" /></div>
                        <div><Label>Plazo máx.</Label><Input v-model.number="form.plazo_max" type="number" min="1" class="mt-1.5" /></div>
                        <div><Label>Score mín.</Label><Input v-model.number="form.score_min" type="number" min="0" class="mt-1.5" /></div>
                        <div><Label>Orden</Label><Input v-model.number="form.orden" type="number" min="0" class="mt-1.5" /></div>
                    </div>
                    <div><Label>Contacto</Label><Input v-model="form.contacto" class="mt-1.5" /></div>
                    <div><Label>Notas</Label><textarea v-model="form.notas" rows="2" class="mt-1.5 w-full rounded-xl border border-input bg-background px-3 py-2 text-sm" /></div>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.destacado" type="checkbox" /> Destacada</label>
                    <label class="flex items-center gap-2 text-sm"><input v-model="form.activo" type="checkbox" /> Activa</label>
                </div>
                <div class="mt-5 flex gap-2">
                    <Button type="submit">{{ editingId ? 'Guardar' : 'Crear' }}</Button>
                    <Button v-if="editingId" type="button" variant="ghost" @click="reset">Cancelar</Button>
                </div>
            </form>

            <div class="overflow-x-auto rounded-2xl border border-border bg-card/50">
                <table class="w-full min-w-[700px] text-sm">
                    <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <tr><th class="p-4">Financiera</th><th class="p-4">Tipo</th><th class="p-4">Tasa</th><th class="p-4">Plazo</th><th class="p-4">Activa</th><th class="p-4 text-right">Acciones</th></tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in financieras" :key="item.id">
                            <td class="p-4">
                                <p class="font-medium">{{ item.nombre }} <span v-if="item.destacado" class="text-xs text-primary">★</span></p>
                                <p class="text-xs text-muted-foreground">{{ item.slug }}</p>
                            </td>
                            <td class="p-4 text-muted-foreground">{{ item.tipo }}</td>
                            <td class="p-4 text-muted-foreground">{{ item.tasa_min ?? '—' }}% – {{ item.tasa_max ?? '—' }}%</td>
                            <td class="p-4 text-muted-foreground">{{ item.plazo_max ?? '—' }} meses</td>
                            <td class="p-4"><span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase" :class="item.activo ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">{{ item.activo ? 'Activa' : 'Inactiva' }}</span></td>
                            <td class="p-4">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="sm" @click="edit(item)"><Pencil class="h-4 w-4" /></Button>
                                    <Button variant="ghost" size="sm" class="text-destructive" @click="destroy(item)"><Trash2 class="h-4 w-4" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
