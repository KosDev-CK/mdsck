import './bootstrap';

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
