// Helper compartido para gráficas ECharts en dashboards Livewire.
//
// Import modular (echarts/core + solo lo que usemos) en vez de 'echarts' a
// secas, para no meter al bundle los ~30 tipos de gráfica/componentes que
// esta app no necesita — cada dashboard que agregue un tipo de gráfica
// nuevo (heatmap, radar, etc.) debe registrar su módulo aquí.
import * as echarts from 'echarts/core';
import { BarChart, LineChart, PieChart, GaugeChart } from 'echarts/charts';
import {
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
    DatasetComponent,
} from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    GaugeChart,
    TitleComponent,
    TooltipComponent,
    GridComponent,
    LegendComponent,
    DatasetComponent,
    CanvasRenderer,
]);

// Los temas se arman en caliente (no como objeto estático) porque los 5
// colores semánticos son configurables en runtime por pantalla vía
// SiteSetting (ver partials/branding-head.blade.php) — hay que leer el
// valor real ya resuelto en <html>, no asumir el default de app.css.
function semanticColors() {
    const root = getComputedStyle(document.documentElement);
    const read = (token, fallback) => (root.getPropertyValue(token) || fallback).trim();

    return {
        primary: read('--color-primary', '#4F46E5'),
        success: read('--color-success', '#059669'),
        danger: read('--color-danger', '#DC2626'),
        warning: read('--color-warning', '#D97706'),
        info: read('--color-info', '#2563EB'),
    };
}

// Paleta categórica (ver el comentario junto a --color-chart-1 en app.css)
// — a propósito NUNCA incluye success/warning/danger (el semáforo de
// estado rojo/ámbar/verde), para que ninguna gráfica coloree una
// categoría cualquiera con un color que en el resto de la app significa
// "mal"/"bien". Es la paleta POR DEFECTO de cualquier serie de ECharts
// que no fije su propio `itemStyle.color` (ej. la dona de categorías,
// las series de la barra apilada) — las gráficas que sí necesitan color
// semántico (cumplimiento de SLA, KPIs) lo piden explícito vía
// `resolveSemanticTokens()`, nunca heredándolo de aquí.
function categoricalColors() {
    const root = getComputedStyle(document.documentElement);
    const read = (token, fallback) => (root.getPropertyValue(token) || fallback).trim();

    return [
        read('--color-chart-1', '#E8C2A0'),
        read('--color-chart-2', '#D8A0E8'),
        read('--color-chart-3', '#A0DBE8'),
        read('--color-chart-4', '#CDE8A0'),
        read('--color-chart-5', '#938272'),
        read('--color-chart-6', '#E8BE7D'),
        read('--color-chart-7', '#6F91E8'),
        read('--color-chart-8', '#35E8B3'),
        read('--color-chart-9', '#695D5B'),
        read('--color-chart-10', '#5A5E69'),
        read('--color-chart-11', '#E8B266'),
        read('--color-chart-12', '#E83BE6'),
        read('--color-chart-13', '#9AC9E8'),
        read('--color-chart-14', '#92E866'),
        read('--color-chart-15', '#938572'),
    ];
}

function buildTheme(isDark) {
    const textColor = isDark ? '#e5e7eb' : '#374151';
    const axisLineColor = isDark ? '#374151' : '#e5e7eb';
    const splitLineColor = isDark ? '#1f2937' : '#f3f4f6';

    return {
        color: categoricalColors(),
        backgroundColor: 'transparent',
        textStyle: { color: textColor },
        title: { textStyle: { color: textColor } },
        legend: { textStyle: { color: textColor } },
        tooltip: {
            backgroundColor: isDark ? '#1f2937' : '#ffffff',
            borderColor: axisLineColor,
            textStyle: { color: textColor },
        },
        categoryAxis: {
            axisLine: { lineStyle: { color: axisLineColor } },
            axisLabel: { color: textColor },
            splitLine: { lineStyle: { color: splitLineColor } },
        },
        valueAxis: {
            axisLine: { lineStyle: { color: axisLineColor } },
            axisLabel: { color: textColor },
            splitLine: { lineStyle: { color: splitLineColor } },
        },
    };
}

function isDarkMode() {
    return document.documentElement.classList.contains('dark');
}

function hexToRgba(hex, alpha) {
    const normalizado = hex.replace('#', '');
    const valor = normalizado.length === 3
        ? normalizado.split('').map((c) => c + c).join('')
        : normalizado;
    const r = parseInt(valor.substring(0, 2), 16);
    const g = parseInt(valor.substring(2, 4), 16);
    const b = parseInt(valor.substring(4, 6), 16);

    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

/**
 * Convierte el `areaStyle` plano de una serie de línea (`{opacity: 0.15}`,
 * como lo arman los dashboards en PHP) en un degradado vertical real —
 * opaco junto a la línea, transparente hacia abajo — usando el color ya
 * resuelto en runtime (ver `categoricalColors()`), nunca uno fijo en el
 * PHP que arma la opción. Solo toca series `type: 'line'` que ya declaran
 * `areaStyle`; una serie de referencia sin relleno (ej. la línea de
 * "Meta" en `slaPorMesOption`, que usa `symbol: 'none'` y no trae
 * `areaStyle`) no se modifica. La paleta de respaldo es la categórica
 * (no la semántica) porque así es como ECharts ya coloreó la línea si la
 * serie no fijó su propio `lineStyle`/`itemStyle` — ver `buildTheme()`.
 */
function conDegradadoDeArea(option) {
    const paleta = categoricalColors();

    (option.series || []).forEach((serie, indice) => {
        if (serie.type !== 'line' || !serie.areaStyle) {
            return;
        }

        const colorBase = serie.lineStyle?.color || serie.itemStyle?.color || paleta[indice % paleta.length];

        serie.areaStyle = {
            color: {
                type: 'linear',
                x: 0,
                y: 0,
                x2: 0,
                y2: 1,
                colorStops: [
                    { offset: 0, color: hexToRgba(colorBase, 0.35) },
                    { offset: 1, color: hexToRgba(colorBase, 0) },
                ],
            },
        };
    });

    return option;
}

/**
 * Resuelve tokens de color semántico (`'--color-primary'`, etc. — el
 * nombre de la custom property de `app.css`, SIN `var()`, opcionalmente con
 * un modificador de opacidad al estilo Tailwind: `'--color-primary/35'` =
 * ese color al 35% de opacidad) a su valor real ya calculado en runtime, en
 * cualquier parte de un `option` de ECharts (`itemStyle.color`,
 * `colorStops[].color`, un array de colores de gradiente...). Existe porque
 * el PHP que arma cada `option` no puede saber el hex real de un color de
 * marca (`SiteSetting` lo sobrescribe por sitio, ver
 * branding-head.blade.php) — así que en vez de mandar un hex fijo desde
 * PHP, manda el NOMBRE del token y esta función lo resuelve aquí, igual de
 * dinámico que `semanticColors()` / `conDegradadoDeArea()` arriba. El
 * modificador de opacidad es lo que arma, por ejemplo, una barra que va del
 * color fuerte a una versión más clara DEL MISMO color (ver
 * `departamentoOption()` en Ejecutivo.php) — mezclando con el fondo de la
 * gráfica en vez de con un segundo color de marca distinto. Recorre el
 * árbol completo del `option` porque un color puede estar en cualquier
 * profundidad (un `data[].itemStyle.color.colorStops[].color`, por
 * ejemplo).
 */
function resolveSemanticTokens(option) {
    const colors = semanticColors();
    const categoricos = categoricalColors();
    const tokenMap = {
        primary: colors.primary,
        success: colors.success,
        danger: colors.danger,
        warning: colors.warning,
        info: colors.info,
    };
    categoricos.forEach((hex, indice) => {
        tokenMap[`chart-${indice + 1}`] = hex;
    });
    const patron = /^--color-(primary|success|danger|warning|info|chart-(?:[1-9]|1[0-5]))(?:\/(\d{1,3}))?$/;

    const resolverToken = (valor) => {
        const coincidencia = patron.exec(valor);

        if (!coincidencia) {
            return null;
        }

        const [, nombre, opacidad] = coincidencia;
        const hex = tokenMap[nombre];

        return opacidad ? hexToRgba(hex, Number(opacidad) / 100) : hex;
    };

    const recorrer = (nodo) => {
        if (Array.isArray(nodo)) {
            nodo.forEach(recorrer);
            return;
        }

        if (!nodo || typeof nodo !== 'object') {
            return;
        }

        Object.keys(nodo).forEach((clave) => {
            const valor = nodo[clave];
            const resuelto = typeof valor === 'string' ? resolverToken(valor) : null;

            if (resuelto) {
                nodo[clave] = resuelto;
            } else {
                recorrer(valor);
            }
        });
    };

    recorrer(option);

    return option;
}

/**
 * Inicializa una gráfica ECharts en `el` con el tema claro/oscuro vigente, y
 * la mantiene sincronizada con: cambios de tema (toggle de theme-toggle.blade.php,
 * detectado por MutationObserver sobre la clase de <html>), resize del
 * contenedor (ResizeObserver, cubre sidebar colapsable/expandible), y
 * actualizaciones de Livewire que reemplacen el nodo (morph) — por eso el
 * caller es responsable de llamar a `dispose()` en un `x-init`/`destroy`
 * de Alpine antes de que Livewire quite el elemento, para no dejar
 * observers huérfanos.
 *
 * @param {HTMLElement} el
 * @param {import('echarts/core').EChartsOption} option
 * @returns {{ chart: import('echarts/core').ECharts, dispose: () => void, setOption: (option: object) => void }}
 */
export function initChart(el, option) {
    option = resolveSemanticTokens(conDegradadoDeArea(option));

    let chart = echarts.init(el, isDarkMode() ? buildTheme(true) : buildTheme(false));
    chart.setOption(option);

    // Listeners registrados vía el helper `onClick` de abajo (ej. cross-filtering
    // de dashboards) — se guardan aquí para volver a engancharlos cada vez que
    // el theme toggle fuerza un dispose()+init() de una instancia nueva de
    // ECharts (ver themeObserver abajo); si no, el clic deja de funcionar en
    // cuanto el usuario cambia de tema una vez.
    const clickHandlers = [];

    const themeObserver = new MutationObserver(() => {
        const dark = isDarkMode();
        chart.dispose();
        chart = echarts.init(el, buildTheme(dark));
        chart.setOption(option);
        clickHandlers.forEach((handler) => chart.on('click', handler));
    });
    themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

    const resizeObserver = new ResizeObserver(() => chart.resize());
    resizeObserver.observe(el);

    return {
        get chart() {
            return chart;
        },
        setOption(next) {
            option = next;
            chart.setOption(next);
        },
        onClick(handler) {
            clickHandlers.push(handler);
            chart.on('click', handler);
        },
        dispose() {
            themeObserver.disconnect();
            resizeObserver.disconnect();
            chart.dispose();
        },
    };
}
