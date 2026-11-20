<script setup lang="ts">
import SeoHead from '@/components/site/SeoHead.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

defineProps<{ settings?: { title?: string; effective_date?: string; content?: string }; seo: { title: string; description: string; canonical: string } }>();
</script>

<template>
    <SeoHead :title="seo.title" :description="seo.description" :canonical="seo.canonical" />

    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6">
        <h1 class="text-3xl font-black text-secondary sm:text-4xl">
            {{ settings?.title || 'Términos y Condiciones de Uso — WeCar' }}
        </h1>
        <p class="mt-2 text-sm text-muted-foreground">
            <strong>Versión 2.1</strong> — Efectiva desde: {{ settings?.effective_date || '15/10/2025' }}
        </p>

        <div v-if="settings?.content" class="prose prose-sm mt-10 max-w-none text-secondary/80" v-html="settings.content" />

        <div v-else class="mt-10 space-y-4 text-sm leading-relaxed text-secondary/80">
            <h2 class="mt-8 text-lg font-black text-secondary">1. Aceptación de los Términos</h2>
            <p>
                Al acceder y utilizar la plataforma WeCar, usted acepta estar sujeto a estos Términos y Condiciones de
                Uso, así como a nuestra Política de Privacidad y Política de Sostenibilidad. Si no está de acuerdo con
                alguna parte de estos términos, no podrá acceder ni utilizar nuestros servicios.
            </p>
            <p>
                WeCar se reserva el derecho de actualizar, modificar o reemplazar cualquier parte de estos términos sin
                previo aviso, siempre en cumplimiento con la legislación aplicable. Es responsabilidad del usuario
                revisar periódicamente esta página para estar al tanto de cualquier cambio.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">2. Descripción del Servicio</h2>
            <p>
                WeCar es una plataforma digital integral que conecta a usuarios con servicios automotrices
                especializados, incluyendo compra, venta, mantenimiento, financiamiento de vehículos y servicios de
                movilidad sostenible. Actuamos como intermediario tecnológico entre compradores y vendedores,
                facilitando transacciones seguras y transparentes.
            </p>
            <p>Nuestros servicios incluyen, de manera enunciativa más no limitativa:</p>
            <ul class="list-disc space-y-1.5 pl-5">
                <li>Compra y venta de vehículos seminuevos entre particulares y con agencias certificadas.</li>
                <li>Financiamiento automotriz a través de instituciones financieras asociadas.</li>
                <li>Servicios de verificación de identidad y documentación vehicular.</li>
                <li>Garantía extendida y protección integral para vehículos adquiridos en la plataforma.</li>
                <li>Programas de sostenibilidad y compensación de carbono.</li>
            </ul>

            <h2 class="mt-8 text-lg font-black text-secondary">3. Elegibilidad y Registro</h2>
            <p>
                Para utilizar nuestros servicios, usted debe ser mayor de 18 años y tener capacidad legal para celebrar
                contratos vinculantes. El registro requiere proporcionar información veraz, completa y actualizada.
                Implementamos procesos de verificación biométrica y documental para garantizar la máxima seguridad de la
                plataforma.
            </p>
            <p>
                Usted es el único responsable de mantener la confidencialidad de sus credenciales de acceso. WeCar no
                será responsable por pérdidas o daños derivados del uso no autorizado de su cuenta. En caso de sospecha
                de uso indebido, deberá notificarlo de inmediato a nuestro equipo de soporte.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">4. Programa de Sostenibilidad</h2>
            <p>
                Promovemos activamente la movilidad sostenible a través de incentivos para vehículos eléctricos e
                híbridos, programas de compensación de carbono y alianzas con organizaciones ambientales. Los usuarios
                que participen en estos programas aceptan los términos específicos de cada iniciativa, los cuales serán
                comunicados de manera clara y transparente.
            </p>
            <p>
                WeCar se compromete a reportar anualmente el impacto ambiental generado por la comunidad, incluyendo
                toneladas de CO₂ compensadas y kilómetros equivalentes en movilidad limpia.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">5. Inteligencia Artificial y Personalización</h2>
            <p>
                Utilizamos algoritmos de inteligencia artificial y machine learning para personalizar su experiencia,
                recomendar vehículos y servicios relevantes, optimizar precios y detectar actividades fraudulentas.
                Estos sistemas operan respetando su privacidad y cumpliendo con las regulaciones de protección de datos
                aplicables.
            </p>
            <p>
                Los usuarios tienen derecho a solicitar información sobre el funcionamiento de estos algoritmos, así
                como a oponerse al tratamiento automatizado de sus datos para fines de marketing y personalización.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">6. Servicios Financieros</h2>
            <p>
                Los servicios financieros ofrecidos a través de WeCar son proporcionados por instituciones financieras
                asociadas, independientes de WeCar. Cada producto financiero (crédito, leasing, seguro) está sujeto a
                sus propios términos y condiciones, los cuales serán presentados al usuario antes de su contratación.
            </p>
            <p>
                WeCar actúa únicamente como intermediario tecnológico y no asume responsabilidad por las decisiones
                crediticias, tasas de interés o términos financieros establecidos por las instituciones asociadas.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">7. Garantías y Protección</h2>
            <p>
                Introducimos garantías extendidas y programas de protección integral para vehículos adquiridos a través
                de nuestra plataforma. Los términos específicos de cada garantía serán detallados en el momento de la
                compra y pueden variar según el tipo de vehículo y el plan seleccionado.
            </p>
            <p>
                La garantía cubre defectos de fabricación y funcionamiento del vehículo durante el período estipulado,
                excluyendo daños por mal uso, accidentes, desgaste normal o modificaciones no autorizadas.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">8. Propiedad Intelectual</h2>
            <p>
                Todos los derechos de propiedad intelectual sobre la plataforma WeCar, incluyendo su diseño, código
                fuente, logotipos, marcas y contenido, son propiedad exclusiva de Wecar Technology S.A.S. o sus
                licenciantes. Queda prohibida la reproducción, distribución, modificación o uso no autorizado de
                cualquier elemento de la plataforma.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">9. Limitación de Responsabilidad</h2>
            <p>
                WeCar actúa como intermediario tecnológico y no será responsable por daños directos, indirectos,
                incidentales o consecuentes derivados del uso de la plataforma, incluyendo pero no limitado a pérdida de
                datos, lucro cesante o interrupción del servicio, excepto donde la ley aplicable lo requiera de manera
                imperativa.
            </p>
            <p>
                La responsabilidad máxima de WeCar en ningún caso excederá el monto total pagado por el usuario por los
                servicios específicos que dieron origen a la reclamación.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">10. Privacidad y Protección de Datos</h2>
            <p>
                El tratamiento de sus datos personales se realiza de acuerdo con nuestra Política de Privacidad, la cual
                forma parte integral de estos términos. WeCar implementa medidas de seguridad técnicas, administrativas y
                físicas para proteger su información contra acceso no autorizado, pérdida o alteración.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">11. Terminación</h2>
            <p>
                WeCar se reserva el derecho de suspender o terminar su acceso a la plataforma en caso de incumplimiento
                de estos términos, sin perjuicio de las acciones legales que correspondan. Usted puede solicitar la
                cancelación de su cuenta en cualquier momento contactando a nuestro equipo de soporte.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">12. Modificaciones</h2>
            <p>
                Nos reservamos el derecho de modificar estos términos con un aviso previo de 30 días, el cual será
                comunicado a través de los canales de contacto registrados por el usuario y mediante aviso destacado en
                la plataforma. El uso continuado de la plataforma después de la entrada en vigor de las modificaciones
                constituye la aceptación de las mismas.
            </p>

            <h2 class="mt-8 text-lg font-black text-secondary">13. Ley Aplicable y Jurisdicción</h2>
            <p>
                Estos términos se rigen por las leyes de los Estados Unidos Mexicanos. Cualquier controversia derivada de
                su interpretación o ejecución será sometida a la jurisdicción de los tribunales competentes de Monterrey,
                Nuevo León, renunciando las partes a cualquier otra jurisdicción que pudiera corresponderles.
            </p>
        </div>
    </section>
</template>
