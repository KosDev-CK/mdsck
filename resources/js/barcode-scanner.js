// Lectura de códigos de barras con la cámara del celular.
//
// Solo para dispositivos móviles (táctiles con cámara y contexto seguro/HTTPS):
// en una laptop o PC no se ofrece, ahí se captura a mano o con un lector USB
// (que escribe el código como si fuera un teclado, sin necesitar nada de aquí).
//
// Usa el `BarcodeDetector` nativo cuando existe (Chrome/Android) y, si no,
// ZXing (iOS Safari, etc.), cargado bajo demanda para no engordar el bundle
// principal. Una etiqueta de caja trae VARIOS códigos (número de serie, EAN,
// número de caja...): por eso el escáner junta todo lo que ve y muestra la
// lista para que la persona toque el correcto, en vez de aceptar el primero
// que encuentre. Solo con `autoAceptar` (p. ej. el folio de una solicitud, un
// único código) se acepta solo cuando hay un valor estable.

const FORMATOS = ['code_128', 'code_39', 'code_93', 'ean_13', 'ean_8', 'upc_a', 'upc_e', 'itf', 'codabar', 'qr_code', 'data_matrix', 'pdf417'];
const FORMATOS_RETAIL = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];

export const camaraDisponible = () => {
    try {
        return Boolean(
            window.isSecureContext
            && navigator.mediaDevices
            && navigator.mediaDevices.getUserMedia
            && navigator.maxTouchPoints > 0
            && window.matchMedia('(pointer: coarse)').matches,
        );
    } catch (e) {
        return false;
    }
};

const crear = (etiqueta, clases, texto) => {
    const el = document.createElement(etiqueta);
    if (clases) {
        el.className = clases;
    }
    if (texto) {
        el.textContent = texto;
    }

    return el;
};

/**
 * Abre el visor de cámara. `alAceptar(valor)` recibe el código elegido.
 */
export const escanearConCamara = ({ titulo = 'Escanear código', autoAceptar = false, alAceptar }) => {
    const capa = crear('div', 'fixed inset-0 z-[100] flex flex-col bg-black');
    capa.setAttribute('role', 'dialog');
    capa.setAttribute('aria-label', titulo);

    const cabecera = crear('div', 'flex items-center justify-between px-4 py-3 text-white');
    cabecera.appendChild(crear('span', 'text-sm font-semibold', titulo));
    const cerrar = crear('button', 'rounded-md border border-white/40 px-3 py-1 text-sm', 'Cancelar');
    cerrar.type = 'button';
    cabecera.appendChild(cerrar);

    const visor = crear('div', 'relative flex-1 overflow-hidden');
    const video = crear('video', 'h-full w-full object-cover');
    video.setAttribute('playsinline', '');
    video.muted = true;
    visor.appendChild(video);
    visor.appendChild(crear('div', 'pointer-events-none absolute inset-x-6 top-1/3 h-1/4 rounded-lg border-2 border-white/70'));

    const estado = crear('p', 'px-4 pt-3 text-center text-sm text-white/80', 'Apunta al código de barras…');
    const lista = crear('div', 'flex max-h-52 flex-col gap-2 overflow-y-auto px-4 pb-6 pt-2');

    capa.append(cabecera, visor, estado, lista);
    document.body.appendChild(capa);

    const vistos = new Map(); // valor -> formato
    let detener = () => {};
    let cerrada = false;
    let candidato = null;
    let candidatoDesde = 0;

    const cerrarTodo = () => {
        if (cerrada) {
            return;
        }
        cerrada = true;
        detener();
        capa.remove();
    };

    const aceptar = (valor) => {
        cerrarTodo();
        alAceptar(valor);
    };

    const pintarLista = () => {
        lista.replaceChildren();
        const ordenados = [...vistos.entries()].sort(([, a], [, b]) => FORMATOS_RETAIL.includes(a) - FORMATOS_RETAIL.includes(b));

        ordenados.forEach(([valor, formato]) => {
            const boton = crear('button', 'rounded-lg bg-white px-4 py-3 text-left text-gray-900');
            boton.type = 'button';
            boton.appendChild(crear('span', 'block break-all text-base font-semibold', valor));
            boton.appendChild(crear('span', 'block text-xs text-gray-500', FORMATOS_RETAIL.includes(formato) ? `${formato} · código de producto, no de serie` : formato));
            boton.addEventListener('click', () => aceptar(valor));
            lista.appendChild(boton);
        });

        estado.textContent = vistos.size > 1
            ? 'Se detectaron varios códigos: toca el correcto.'
            : vistos.size === 1 ? 'Toca el código para usarlo.' : 'Apunta al código de barras…';
    };

    const registrar = (valor, formato) => {
        if (!valor || cerrada) {
            return;
        }
        if (!vistos.has(valor)) {
            vistos.set(valor, formato || '');
            pintarLista();
        }

        if (autoAceptar && vistos.size === 1) {
            if (candidato !== valor) {
                candidato = valor;
                candidatoDesde = Date.now();
            } else if (Date.now() - candidatoDesde > 500) {
                aceptar(valor);
            }
        } else {
            candidato = null;
        }
    };

    cerrar.addEventListener('click', cerrarTodo);

    const iniciar = async () => {
        try {
            if ('BarcodeDetector' in window) {
                const detector = new window.BarcodeDetector({ formats: FORMATOS });
                const flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
                video.srcObject = flujo;
                await video.play();

                const intervalo = setInterval(async () => {
                    try {
                        const hallazgos = await detector.detect(video);
                        hallazgos.forEach((h) => registrar(h.rawValue, h.format));
                    } catch (e) { /* frame no listo: se reintenta */ }
                }, 250);

                detener = () => {
                    clearInterval(intervalo);
                    flujo.getTracks().forEach((t) => t.stop());
                };

                return;
            }

            const { BrowserMultiFormatReader } = await import('@zxing/browser');
            const { BarcodeFormat } = await import('@zxing/library');
            const lector = new BrowserMultiFormatReader();
            const controles = await lector.decodeFromConstraints(
                { video: { facingMode: { ideal: 'environment' } }, audio: false },
                video,
                (resultado) => {
                    if (resultado) {
                        registrar(resultado.getText(), String(BarcodeFormat[resultado.getBarcodeFormat()] ?? '').toLowerCase());
                    }
                },
            );
            detener = () => controles.stop();
        } catch (e) {
            estado.textContent = 'No se pudo abrir la cámara. Revisa el permiso del navegador o captura el código a mano.';
        }
    };

    iniciar();
};
