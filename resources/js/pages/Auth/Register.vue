<script setup lang="ts">
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff, LoaderCircle } from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SeoHead from '@/components/site/SeoHead.vue';
import Logo from '@/components/site/Logo.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Label from '@/components/ui/Label.vue';
import PhoneInput from '@/components/ui/PhoneInput.vue';
import FormErrorSummary from '@/components/ui/FormErrorSummary.vue';
import FormFieldError from '@/components/ui/FormFieldError.vue';
import { useRequiredValidation } from '@/composables/useRequiredValidation';

defineOptions({ layout: PublicLayout });

type AccountType = 'personal' | 'agency';

const form = useForm({
    account_type: 'personal' as AccountType,
    name: '',
    agency_name: '',
    agency_phone: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const showPassword = ref(false);
const acceptedTerms = ref(false);
const validation = useRequiredValidation(form, () => ({
    name: 'nombre',
    ...(form.account_type === 'agency' ? { agency_name: 'nombre de la agencia', agency_phone: 'teléfono de la agencia' } : {}),
    email: 'correo electrónico',
    password: 'contraseña',
    password_confirmation: 'confirmación de contraseña',
    terms: 'aceptación de términos',
}));

const setAccountType = (type: AccountType) => {
    form.account_type = type;
    if (type === 'personal') {
        form.agency_name = '';
        form.agency_phone = '';
    }
};

const submit = () => {
    form.terms = acceptedTerms.value;

    if (!validation.validate()) return;

    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <SeoHead title="Crear cuenta — Wecar" description="Crea tu cuenta personal o de agencia en Wecar." />

    <section class="mx-auto max-w-md px-4 py-10 sm:px-6 sm:py-16">
        <div class="rounded-3xl border border-border bg-card p-6 shadow-elegant sm:p-8">
            <div class="flex justify-center">
                <Logo />
            </div>

            <h1 class="mt-5 text-center text-xl font-semibold tracking-tight text-secondary">Crear cuenta</h1>
            <p class="mt-2 text-center text-sm text-muted-foreground">Únete y encuentra tu auto ideal</p>

            <a
                href="/auth/google/redirect"
                class="mt-6 flex h-12 w-full items-center justify-center gap-3 rounded-full border border-input bg-background text-sm font-semibold transition-colors hover:bg-accent"
            >
                <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3c-1.6 4.7-6.1 8-11.3 8-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.3-.4-3.5z" />
                    <path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z" />
                    <path fill="#4CAF50" d="M24 44c5.2 0 10-2 13.6-5.2l-6.3-5.3C29.2 35 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z" />
                    <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.3-4.3 5.7l6.3 5.3C41.4 35.4 44 30.1 44 24c0-1.3-.1-2.3-.4-3.5z" />
                </svg>
                Continuar con Google
            </a>

            <div class="my-6 flex items-center gap-3 text-xs text-muted-foreground">
                <span class="h-px flex-1 bg-border" /> o con email
                <span class="h-px flex-1 bg-border" />
            </div>

            <form no-validate class="grid gap-4" @submit.prevent="submit">
                <FormErrorSummary :fields="validation.summary.value" />
                <div class="grid grid-cols-2 gap-2 rounded-2xl bg-accent/50 p-1">
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors"
                        :class="form.account_type === 'personal' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="setAccountType('personal')"
                    >
                        Personal
                    </button>
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold transition-colors"
                        :class="form.account_type === 'agency' ? 'bg-background text-primary shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        @click="setAccountType('agency')"
                    >
                        Soy agencia
                    </button>
                </div>

                <div>
                    <Label for="name">{{ form.account_type === 'agency' ? 'Nombre del responsable' : 'Nombre completo' }}</Label>
                    <Input id="name" v-model="form.name" :invalid="validation.hasError('name')" class="mt-1.5" autocomplete="name" placeholder="Tu nombre" @input="validation.clearField('name')" />
                    <FormFieldError :message="validation.fieldError('name')" />
                </div>

                <template v-if="form.account_type === 'agency'">
                    <div>
                        <Label for="agency_name">Nombre de la agencia</Label>
                        <Input id="agency_name" v-model="form.agency_name" :invalid="validation.hasError('agency_name')" class="mt-1.5" placeholder="Mi agencia" @input="validation.clearField('agency_name')" />
                        <FormFieldError :message="validation.fieldError('agency_name')" />
                    </div>
                    <div>
                        <Label for="agency_phone">Teléfono de la agencia</Label>
                        <PhoneInput id="agency_phone" v-model="form.agency_phone" :invalid="validation.hasError('agency_phone')" class="mt-1.5" autocomplete="tel" placeholder="81 1234 5678" />
                        <FormFieldError :message="validation.fieldError('agency_phone')" />
                    </div>
                </template>

                <div>
                    <Label for="email">Correo electrónico</Label>
                    <Input id="email" v-model="form.email" type="email" :invalid="validation.hasError('email')" class="mt-1.5" autocomplete="email" placeholder="tu@correo.com" @input="validation.clearField('email')" />
                    <FormFieldError :message="validation.fieldError('email')" />
                </div>

                <div>
                    <Label for="password">Contraseña</Label>
                    <div class="relative mt-1.5">
                        <Input
                            id="password"
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            :invalid="validation.hasError('password')"
                            class="pr-11"
                            autocomplete="new-password"
                            @input="validation.clearField('password')"
                        />
                        <button
                            type="button"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            class="absolute inset-y-0 right-3 grid place-items-center text-muted-foreground hover:text-foreground"
                            @click="showPassword = !showPassword"
                        >
                            <EyeOff v-if="showPassword" class="h-4 w-4" />
                            <Eye v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <FormFieldError :message="validation.fieldError('password')" />
                    <p v-if="!validation.hasError('password')" class="mt-1 text-xs text-muted-foreground">Mínimo 8 caracteres, con letras y números.</p>
                </div>

                <div>
                    <Label for="password_confirmation">Confirmar contraseña</Label>
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        :type="showPassword ? 'text' : 'password'"
                        :invalid="validation.hasError('password_confirmation')"
                        class="mt-1.5"
                        autocomplete="new-password"
                        @input="validation.clearField('password_confirmation')"
                    />
                    <FormFieldError :message="validation.fieldError('password_confirmation')" />
                </div>

                <label class="flex cursor-pointer items-start gap-2 text-xs text-muted-foreground">
                    <input v-model="acceptedTerms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-input" />
                    <span>
                        Acepto los
                        <Link href="/terminos" target="_blank" class="font-semibold text-primary hover:underline">términos y condiciones</Link>
                        y el
                        <Link href="/privacidad" target="_blank" class="font-semibold text-primary hover:underline">aviso de privacidad</Link>.
                    </span>
                </label>
                <p v-if="validation.hasError('terms')" class="flex items-start gap-1 text-xs font-medium text-destructive">
                    Debes aceptar los términos y el aviso de privacidad.
                </p>

                <Button type="submit" variant="hero" size="xl" class="w-full" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ form.processing ? 'Creando cuenta…' : 'Crear cuenta' }}
                </Button>
            </form>

            <p class="mt-6 text-center text-sm text-muted-foreground">
                ¿Ya tienes cuenta?
                <Link href="/login" class="font-bold text-primary hover:underline">Inicia sesión</Link>
            </p>
        </div>
    </section>
</template>
