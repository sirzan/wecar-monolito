<script setup lang="ts">
import { useForm, router } from '@inertiajs/vue3';
import { Mail, Trash2, UserPlus, Users } from '@lucide/vue';
import PanelLayout from '@/layouts/PanelLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';

defineOptions({ layout: PanelLayout });

interface Member {
    id: string;
    email: string;
    name: string | null;
    role: string;
    status: string;
    accepted_at: string | null;
}

const props = defineProps<{
    agency: { id: string; slug: string; name: string };
    members: Member[];
    canManage: boolean;
}>();

const base = `/panel/agencia/${props.agency.slug}`;

const form = useForm({
    email: '',
    role: 'seller',
});

const invite = () => {
    form.post(`${base}/equipo/invitar`, {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
};

const updateMember = (member: Member, payload: { role?: string; status?: string }) => {
    router.patch(`${base}/equipo/${member.id}`, { role: member.role, status: member.status, ...payload }, { preserveScroll: true });
};

const removeMember = (member: Member) => {
    if (!window.confirm(`¿Eliminar a ${member.email} del equipo?`)) return;
    router.delete(`${base}/equipo/${member.id}`, { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <p class="text-xs font-bold uppercase tracking-wide text-primary">Agencia</p>
        <h1 class="text-2xl font-black text-secondary sm:text-3xl">Equipo</h1>
        <p class="mt-1 text-sm text-muted-foreground">Gestiona vendedores y administradores de {{ agency.name }}.</p>

        <form v-if="canManage" class="mt-6 rounded-3xl border border-border bg-card p-6 shadow-soft" @submit.prevent="invite">
            <div class="flex items-center gap-2">
                <UserPlus class="h-5 w-5 text-primary" />
                <h2 class="font-black text-secondary">Invitar miembro</h2>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_180px_auto] sm:items-end">
                <div>
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" v-model="form.email" type="email" class="mt-1.5" placeholder="vendedor@agencia.com" />
                    <p v-if="form.errors.email" class="mt-1 text-xs text-destructive">{{ form.errors.email }}</p>
                </div>
                <div>
                    <Label for="role">Rol</Label>
                    <select id="role" v-model="form.role" class="mt-1.5 h-11 w-full rounded-xl border border-input bg-background px-3 text-sm">
                        <option value="seller">Vendedor</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <Button type="submit" :disabled="form.processing">Enviar invitación</Button>
            </div>
        </form>

        <div class="mt-6 rounded-3xl border border-border bg-card shadow-soft">
            <div class="flex items-center gap-2 border-b border-border p-5">
                <Users class="h-5 w-5 text-primary" />
                <h2 class="font-black text-secondary">Miembros ({{ members.length }})</h2>
            </div>

            <div v-if="members.length" class="divide-y divide-border">
                <div v-for="member in members" :key="member.id" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-bold text-secondary">{{ member.name ?? member.email }}</p>
                        <p class="text-xs text-muted-foreground">{{ member.email }}</p>
                        <p v-if="member.accepted_at" class="text-[11px] text-muted-foreground">Aceptó la invitación</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <select
                            v-if="canManage"
                            class="h-8 rounded-lg border border-input bg-background px-2 text-xs"
                            :value="member.role"
                            @change="updateMember(member, { role: ($event.target as HTMLSelectElement).value })"
                        >
                            <option value="seller">Vendedor</option>
                            <option value="admin">Administrador</option>
                        </select>
                        <span v-else class="rounded-full bg-accent px-2 py-0.5 text-[10px] font-bold uppercase text-accent-foreground">{{ member.role }}</span>

                        <select
                            v-if="canManage"
                            class="h-8 rounded-lg border border-input bg-background px-2 text-xs"
                            :value="member.status"
                            @change="updateMember(member, { status: ($event.target as HTMLSelectElement).value })"
                        >
                            <option value="invited">Invitado</option>
                            <option value="active">Activo</option>
                            <option value="disabled">Deshabilitado</option>
                        </select>
                        <span v-else class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-bold uppercase text-muted-foreground">{{ member.status }}</span>

                        <Button v-if="canManage" variant="ghost" size="sm" class="text-destructive" @click="removeMember(member)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
            <p v-else class="p-5 text-sm text-muted-foreground">Aún no hay miembros en el equipo.</p>
        </div>

        <div v-if="!canManage" class="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
            <Mail class="h-4 w-4" /> Solo el dueño de la agencia puede invitar o modificar miembros.
        </div>
    </div>
</template>
