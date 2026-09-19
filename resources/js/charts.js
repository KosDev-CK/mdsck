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

function buildTheme(isDark) {
    const c = semanticColors();
    const textColor = isDark ? '#e5e7eb' : '#374151';
    const axisLineColor = isDark ? '#374151' : '#e5e7eb';
    const splitLineColor = isDark ? '#1f2937' : '#f3f4f6';

    return {
        color: [c.primary, c.success, c.warning, c.info, c.danger],
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
