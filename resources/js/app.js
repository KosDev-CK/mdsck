import './bootstrap';
import { camaraDisponible, escanearConCamara } from './barcode-scanner';

// Lectura de códigos de barras con la cámara (solo celulares) — ver
// `barcode-scanner.js`. Los lectores USB no necesitan esto: escriben en el
// campo enfocado como un teclado.
window.camaraDisponible = camaraDisponible;
window.escanearConCamara = escanearConCamara;

// Recepción de Proveedor (eventos que manda el servidor tras escanear un código):
// llevar la vista a la línea activa y devolver el foco al campo de escaneo.
window.addEventListener('recepcion-scroll', (evento) => {
    setTimeout(() => document.getElementById(evento.detail.id)?.scrollIntoView({ behavior: 'smooth', block: 'center' }), 100);
});
window.addEventListener('recepcion-enfocar-escaner', () => {
    setTimeout(() => document.getElementById('recepcion-escaner')?.focus(), 250);
});

// Enter en un campo de número de serie (lo que manda un lector al terminar de
// escanear) pasa al siguiente número de serie del formulario en vez de
// enviarlo. Los campos se marcan con `data-serie`.
window.enfocarSiguienteSerie = (el) => {
    const campos = [...el.closest('form').querySelectorAll('[data-serie]')];
    const siguiente = campos[campos.indexOf(el) + 1];

    if (siguiente) {
        siguiente.focus();
        siguiente.select?.();
    }
};

// Import dinámico (no estático) a propósito: si `charts.js` (que carga
// ECharts) se importara aquí arriba, Vite lo metería al bundle principal
// que se descarga en CADA página del sitio, aunque solo los 3 dashboards
// nuevos vayan a usarlo — con `import()` Vite lo separa en su propio chunk,
// que el navegador solo pide la primera vez que una pantalla llama a
// `window.initChart(...)`. Por eso `initChart` es async aquí: quien lo
// llame debe hacer `await window.initChart(el, option)` (o `.then(...)`).
window.initChart = async (el, option) => {
    const { initChart } = await import('./charts');

    return initChart(el, option);
};

// Indicador global de "trabajando": cualquier request de Livewire (clic en un
// botón, guardar, enviar correo, paginar...) prende `data-lw-busy` en <html>
// de inmediato (bloquea re-clics sobre acciones, ver app.css) y, si tarda más
// de 250 ms, `data-lw-slow` (barra de progreso arriba + cursor de espera +
// acciones atenuadas). Los requests rápidos no muestran nada, sin parpadeo.
const registrarIndicadorDeCarga = () => {
    if (window.__lwBusyRegistrado) {
        return;
    }
    window.__lwBusyRegistrado = true;

    const html = document.documentElement;
    const barra = document.createElement('div');
    barra.id = 'lw-busy-bar';
    barra.setAttribute('role', 'progressbar');
    barra.setAttribute('aria-label', 'Procesando');
    barra.setAttribute('aria-hidden', 'true');
    document.body.appendChild(barra);

    let pendientes = 0;
    let temporizador = null;

    const actualizar = () => {
        if (pendientes > 0) {
            html.setAttribute('data-lw-busy', '');
            html.setAttribute('aria-busy', 'true');
            if (temporizador === null) {
                temporizador = setTimeout(() => {
                    html.setAttribute('data-lw-slow', '');
                    barra.setAttribute('aria-hidden', 'false');
                }, 250);
            }

            return;
        }

        clearTimeout(temporizador);
        temporizador = null;
        html.removeAttribute('data-lw-busy');
        html.removeAttribute('data-lw-slow');
        html.removeAttribute('aria-busy');
        barra.setAttribute('aria-hidden', 'true');
    };

    // Requests de fondo (wire:poll, eventos de Echo/dispatch) no son acciones
    // del usuario: no deben mostrar la barra ni bloquear clics.
    const esDeFondo = (commit) => {
        const llamadas = commit.calls ?? [];
        const sinCambios = Object.keys(commit.updates ?? {}).length === 0;

        return sinCambios && llamadas.length > 0 && llamadas.every((l) => ['$refresh', '__dispatch'].includes(l.method));
    };

    window.Livewire.hook('commit', ({ commit, succeed, fail }) => {
        if (esDeFondo(commit)) {
            return;
        }

        pendientes++;
        actualizar();

        const terminar = () => {
            pendientes = Math.max(0, pendientes - 1);
            actualizar();
        };

        succeed(terminar);
        fail(terminar);
    });
};

if (window.Livewire) {
    document.body ? registrarIndicadorDeCarga() : document.addEventListener('DOMContentLoaded', registrarIndicadorDeCarga);
} else {
    document.addEventListener('livewire:init', registrarIndicadorDeCarga);
}
