export interface FaqCategory {
    id: string;
    label: string;
}

export interface Faq {
    id: string;
    category: string;
    question: string;
    answer: string;
}

export const faqCategories: FaqCategory[] = [
    { id: 'garantia', label: 'Garantía' },
    { id: 'inspeccion', label: 'Inspección y certificación' },
    { id: 'compra', label: 'Compra segura' },
    { id: 'busqueda', label: 'Búsqueda y catálogo' },
    { id: 'externos', label: 'Búsqueda externa, tokens y membresía' },
    { id: 'publicar', label: 'Publicar y vender' },
    { id: 'credito', label: 'Crédito y financiamiento' },
    { id: 'pagos', label: 'Pagos y facturación' },
    { id: 'cuenta', label: 'Cuenta y privacidad' },
];

export const faqs: Faq[] = [
    // Garantía
    {
        id: 'garantia-cobertura',
        category: 'garantia',
        question: '¿Qué cubre exactamente la garantía y revisión de WeCar?',
        answer: 'Incluye una inspección de más de 160 puntos mecánicos, eléctricos y estéticos, revisión de antecedentes legales, facturas y multas, así como acompañamiento durante el cierre de la compraventa.',
    },
    {
        id: 'garantia-auto-propio',
        category: 'garantia',
        question: '¿Puedo contratar el servicio para un auto que encontré por mi cuenta?',
        answer: 'Sí. Puedes contratar el servicio de Compra Segura para cualquier auto seminuevo en las ciudades donde operamos. Nosotros contactamos al vendedor, revisamos el auto y te entregamos el reporte.',
    },
    {
        id: 'garantia-costo',
        category: 'garantia',
        question: '¿Cómo se cotiza el costo del servicio de Compra Segura?',
        answer: 'El cargo por servicio es desde el 2.5% sobre el valor acordado del vehículo, e incluye diagnóstico, gestoría y acompañamiento legal.',
    },
    {
        id: 'garantia-no-pasa',
        category: 'garantia',
        question: '¿Qué pasa si el auto no pasa la inspección mecánica o legal?',
        answer: 'Te entregamos un reporte detallado con las fallas o anomalías encontradas para que no compres un problema. Tú decides si renegociar el precio o buscar otra opción en nuestro catálogo.',
    },

    // Inspección y certificación
    {
        id: 'inspeccion-mecanica',
        category: 'inspeccion',
        question: '¿Cómo validan el auto mecánicamente?',
        answer: 'Realizamos una inspección de 160 puntos en el taller Motorevs, revisada por técnicos certificados. Al finalizar recibes un reporte con el estado del vehículo.',
    },
    {
        id: 'inspeccion-legal',
        category: 'inspeccion',
        question: '¿Qué incluye la revisión legal?',
        answer: 'Validamos REPUVE, ICV, factura y que el auto esté libre de gravámenes, para que compres con la tranquilidad de saber exactamente qué estás comprando.',
    },
    {
        id: 'inspeccion-ver-auto',
        category: 'inspeccion',
        question: '¿Puedo ver los autos físicamente?',
        answer: 'La revisión se realiza en el Taller Motorevs. Al agendar tu cita coordinamos la visita para que puedas conocer el auto junto con el informe de la revisión.',
    },
    {
        id: 'inspeccion-contratos',
        category: 'inspeccion',
        question: '¿Quién prepara los contratos?',
        answer: 'WeCar te acompaña durante el proceso de compra y venta. La formalización del contrato se realiza con las partes involucradas y, cuando corresponde, con la agencia o financiera.',
    },
    {
        id: 'inspeccion-acompanamiento',
        category: 'inspeccion',
        question: '¿Hasta dónde llega el acompañamiento?',
        answer: 'Un asesor te guía desde la elección del auto hasta la revisión, la firma del contrato y la entrega de llaves.',
    },

    // Compra segura
    {
        id: 'compra-proteccion',
        category: 'compra',
        question: '¿Cómo protege WeCar mi compra?',
        answer: 'Validamos la documentación del auto, su situación legal y su estado mecánico. Además te acompañamos durante la negociación y el cierre de la operación.',
    },
    {
        id: 'compra-pago',
        category: 'compra',
        question: '¿WeCar interviene en el pago entre comprador y vendedor?',
        answer: 'WeCar no funge como custodio de los pagos. La operación se realiza entre las partes, agencia o financiera; te recomendamos no pagar sin completar la validación y la firma correspondiente.',
    },
    {
        id: 'compra-reporte-robo',
        category: 'compra',
        question: '¿Qué pasa si el auto tiene reporte de robo o adeudos?',
        answer: 'Durante la revisión legal verificamos REPUVE, factura y gravámenes. Si detectamos una anomalía, te entregamos el reporte para que no continúes con la compra.',
    },
    {
        id: 'compra-reportar-anuncio',
        category: 'compra',
        question: '¿Cómo reporto un anuncio sospechoso?',
        answer: 'Escríbenos desde la página de Contacto o por nuestros canales de atención con el enlace del anuncio. Revisaremos el caso y tomaremos las medidas correspondientes.',
    },

    // Búsqueda y catálogo
    {
        id: 'busqueda-ia',
        category: 'busqueda',
        question: '¿Cómo funciona la búsqueda con IA?',
        answer: 'Escribe lo que buscas en lenguaje natural. La IA interpreta marca, modelo, año, precio y otros filtros, busca primero en el catálogo WeCar y, si no hay resultados, consulta plataformas aliadas.',
    },
    {
        id: 'busqueda-sin-resultados',
        category: 'busqueda',
        question: '¿Qué pasa si no encuentran mi auto en el catálogo?',
        answer: 'La búsqueda se amplía automáticamente a plataformas aliadas. Si tampoco hay resultados, puedes ajustar los filtros o ampliar el rango de precio y año.',
    },
    {
        id: 'busqueda-externos-verificados',
        category: 'busqueda',
        question: '¿Los autos de otras plataformas están verificados por WeCar?',
        answer: 'Los anuncios externos provienen de plataformas aliadas y no cuentan con la inspección de WeCar. Te recomendamos validar los detalles directamente con el vendedor antes de cualquier pago.',
    },
    {
        id: 'busqueda-favoritos',
        category: 'busqueda',
        question: '¿Puedo guardar autos en favoritos?',
        answer: 'Sí. Desde la card de cada auto puedes guardarlo en favoritos y consultarlo después desde tu cuenta.',
    },
    {
        id: 'busqueda-comparador',
        category: 'busqueda',
        question: '¿Cómo funciona el comparador de autos?',
        answer: 'Selecciona autos con el botón Comparar y revisa sus datos lado a lado para tomar una mejor decisión.',
    },
    {
        id: 'busqueda-actualizacion',
        category: 'busqueda',
        question: '¿Cada cuánto se actualiza el catálogo?',
        answer: 'El catálogo interno se actualiza conforme se publican y modifican autos. Los resultados de plataformas aliadas se consultan al momento de realizar la búsqueda.',
    },

    // Búsqueda externa, tokens y membresía
    {
        id: 'externos-gratis',
        category: 'externos',
        question: '¿Cuántas búsquedas gratis tengo al día?',
        answer: 'Cada cuenta tiene 3 búsquedas y 15 vistas gratis al día en plataformas externas.',
    },
    {
        id: 'externos-tokens',
        category: 'externos',
        question: '¿Qué son los tokens y caducan?',
        answer: 'Los tokens permiten realizar búsquedas adicionales cuando se agotan las gratuitas. No caducan y se pueden comprar desde el panel de membresía.',
    },
    {
        id: 'externos-membresia',
        category: 'externos',
        question: '¿Cuánto cuesta la membresía y qué incluye?',
        answer: 'La membresía tiene un costo de $499 MXN al mes e incluye búsquedas y vistas ilimitadas en plataformas externas.',
    },
    {
        id: 'externos-agotado',
        category: 'externos',
        question: '¿Qué pasa cuando se acaban mis búsquedas gratuitas?',
        answer: 'Puedes comprar tokens desde $49 MXN o activar la membresía para tener acceso ilimitado.',
    },
    {
        id: 'externos-cupon',
        category: 'externos',
        question: '¿Cómo funciona el cupón TKNS50?',
        answer: 'Cuando el cupón está activo en tu cuenta, obtienes 50% de descuento en la compra de tokens.',
    },
    {
        id: 'externos-cancelar',
        category: 'externos',
        question: '¿Puedo cancelar o renovar mi membresía?',
        answer: 'Puedes renovar 30 días desde el panel de membresía. Para cancelar, contacta a nuestro equipo de soporte.',
    },
    {
        id: 'externos-ver-anuncio',
        category: 'externos',
        question: '¿Abrir un anuncio externo consume una búsqueda?',
        answer: 'No. Abrir el detalle o el anuncio original de un auto externo no consume búsquedas adicionales.',
    },

    // Publicar y vender
    {
        id: 'publicar-costo',
        category: 'publicar',
        question: '¿Cuánto cuesta publicar mi auto?',
        answer: 'Puedes publicar gratis con el plan Básico y cambiar de plan cuando quieras.',
    },
    {
        id: 'publicar-autos',
        category: 'publicar',
        question: '¿Cuántos autos puedo publicar según mi plan?',
        answer: 'Cada plan indica cuántos autos permite publicar. Puedes consultar los límites en la sección de paquetes al publicar.',
    },
    {
        id: 'publicar-tiempo',
        category: 'publicar',
        question: '¿Cuánto tarda publicar un auto?',
        answer: 'El proceso de publicación está diseñado para completarse en aproximadamente 2 minutos.',
    },
    {
        id: 'publicar-editar',
        category: 'publicar',
        question: '¿Puedo editar mi publicación después de publicarla?',
        answer: 'Sí. Desde Mis publicaciones puedes editar datos, fotos, precio y estado del anuncio.',
    },
    {
        id: 'publicar-prospectos',
        category: 'publicar',
        question: '¿Cómo recibo prospectos o contactos?',
        answer: 'Los interesados pueden contactarte desde la ficha del auto. También puedes consultar el seguimiento desde tu panel.',
    },
    {
        id: 'publicar-documentos',
        category: 'publicar',
        question: '¿Qué documentos necesito para publicar?',
        answer: 'Necesitas los datos del auto y sus fotos. Para completar la ficha te pediremos la documentación que respalde la publicación.',
    },
    {
        id: 'publicar-destacar',
        category: 'publicar',
        question: '¿Puedo destacar mi anuncio?',
        answer: 'Sí. Algunos planes incluyen posición destacada en el catálogo.',
    },
    {
        id: 'publicar-vendido',
        category: 'publicar',
        question: '¿Qué pasa si vendo mi auto?',
        answer: 'Puedes actualizar el estado de tu publicación desde el panel para dejar de recibir contactos.',
    },

    // Crédito y financiamiento
    {
        id: 'credito-simular',
        category: 'credito',
        question: '¿Cómo simulo mi crédito?',
        answer: 'Desde /credito o desde la ficha de un auto con el botón Solicitar financiamiento. Ajusta precio, enganche y plazo para ver una mensualidad estimada.',
    },
    {
        id: 'credito-datos',
        category: 'credito',
        question: '¿Qué datos necesito para precalificar?',
        answer: 'Ingreso mensual, antigüedad laboral, historial crediticio, edad y los datos del auto a financiar.',
    },
    {
        id: 'credito-quien',
        category: 'credito',
        question: '¿Quién otorga el financiamiento?',
        answer: 'El crédito lo otorgan instituciones financieras asociadas. WeCar actúa como intermediario tecnológico.',
    },
    {
        id: 'credito-tiempo',
        category: 'credito',
        question: '¿Cuánto tarda la aprobación?',
        answer: 'El proceso de aprobación suele tomar entre 24 y 48 horas, dependiendo de la financiera y de tu documentación.',
    },
    {
        id: 'credito-cargo',
        category: 'credito',
        question: '¿Qué es el cargo por servicio del 2.5%?',
        answer: 'Es el cargo por servicio de WeCar sobre el valor del auto. Incluye la validación de 160 puntos y el acompañamiento legal.',
    },
    {
        id: 'credito-auto-externo',
        category: 'credito',
        question: '¿Puedo financiar un auto que encontré en otra plataforma?',
        answer: 'Sí. Desde la ficha del auto externo puedes iniciar la solicitud con el botón Solicitar financiamiento.',
    },
    {
        id: 'credito-documentos',
        category: 'credito',
        question: '¿Qué documentos necesito para solicitar el crédito?',
        answer: 'Identificación oficial, comprobante de domicilio y estado de cuenta o comprobante de ingresos.',
    },
    {
        id: 'credito-sin-registro',
        category: 'credito',
        question: '¿Puedo solicitar crédito sin estar registrado?',
        answer: 'Puedes simular el crédito, pero para enviar tu solicitud necesitas crear una cuenta e iniciar sesión.',
    },

    // Pagos y facturación
    {
        id: 'pagos-metodos',
        category: 'pagos',
        question: '¿Qué métodos de pago aceptan?',
        answer: 'Puedes pagar con tarjeta o transferencia SPEI.',
    },
    {
        id: 'pagos-tarjeta',
        category: 'pagos',
        question: '¿Guardan los datos de mi tarjeta?',
        answer: 'No almacenamos los datos completos de tu tarjeta. Los pagos se procesan mediante una pasarela externa.',
    },
    {
        id: 'pagos-factura',
        category: 'pagos',
        question: '¿Cómo obtengo mi factura?',
        answer: 'Desde la sección de facturación de tu panel puedes consultar tus pagos y generar tu factura.',
    },
    {
        id: 'pagos-movimientos',
        category: 'pagos',
        question: '¿Dónde consulto mis pagos y movimientos?',
        answer: 'En tu panel encontrarás el historial de pagos y los movimientos de tokens.',
    },

    // Cuenta y privacidad
    {
        id: 'cuenta-crear',
        category: 'cuenta',
        question: '¿Cómo creo mi cuenta?',
        answer: 'Regístrate con tu correo electrónico desde la página de registro y completa tu perfil.',
    },
    {
        id: 'cuenta-actualizar',
        category: 'cuenta',
        question: '¿Cómo actualizo mis datos personales?',
        answer: 'Desde tu panel de perfil puedes actualizar tus datos de contacto y tu perfil crediticio.',
    },
    {
        id: 'cuenta-eliminar',
        category: 'cuenta',
        question: '¿Cómo elimino mi cuenta?',
        answer: 'Contacta a nuestro equipo de soporte para solicitar la cancelación de tu cuenta.',
    },
    {
        id: 'cuenta-arco',
        category: 'cuenta',
        question: '¿Cómo ejercen mis derechos ARCO?',
        answer: 'Puedes solicitarlo escribiendo a privacidad@wecar.mx. Responderemos en un plazo máximo de 20 días hábiles.',
    },
    {
        id: 'cuenta-documentos',
        category: 'cuenta',
        question: '¿Qué hacen con mis documentos?',
        answer: 'Se usan únicamente para validar tu identidad y tu solicitud. Se guardan de forma privada y se tratan conforme al Aviso de Privacidad.',
    },
];
