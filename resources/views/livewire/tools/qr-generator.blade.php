<div>
    @push('page-title')
        Generador de QR
    @endpush

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/qr-generator/qr-app.css') }}">

    <div class="qr-app">

        <header class="app-header">
            <div class="brand">
                <div class="brand-mark"><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span></div>
                <div>
                    <div class="brand-name">QR Studio</div>
                    <div class="brand-tag">Generador de códigos QR</div>
                </div>
            </div>
            <div class="header-copy">
                <h1>Crea, diseña y descarga tu código QR</h1>
                <p>Elige un tipo de contenido, personaliza la forma, el marco y el logo, y exporta en PNG, SVG o PDF. Todo se genera en tu navegador.</p>
            </div>
            <div class="project-io">
                <button type="button" class="btn" id="btn-export-json" title="Guarda todo tu contenido y diseño actual en un archivo .json">
                    <svg viewBox="0 0 24 24" width="15" height="15"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 18h14" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Exportar proyecto
                </button>
                <button type="button" class="btn" id="btn-import-json" title="Carga un proyecto guardado previamente (.json)">
                    <svg viewBox="0 0 24 24" width="15" height="15"><path d="M12 16V6m0 0L8 10m4-4l4 4M5 18h14" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Importar proyecto
                </button>
                <input type="file" id="import-json-input" accept="application/json,.json" style="display:none">
            </div>
        </header>

        <div class="err-msg" id="project-io-err" style="margin-bottom:14px"></div>

        <div class="app-grid">
            <!-- ---------- Columna izquierda ---------- -->
            <div class="panel">
                <div class="type-tabs" id="type-tabs"></div>

                <div class="step">
                    <div class="step-head">
                        <div class="step-glyph n1"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                        <div>
                            <div class="step-title">Completa el contenido</div>
                            <div class="step-sub">Lo que verá el código al escanearse</div>
                        </div>
                    </div>
                    <div id="content-fields"></div>
                </div>

                <div class="step">
                    <div class="step-head">
                        <div class="step-glyph n2"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                        <div>
                            <div class="step-title">Diseña tu código QR</div>
                            <div class="step-sub">Marco, forma, color y logo</div>
                        </div>
                        <button type="button" class="btn-reset btn-reset-all" id="reset-all">
                            <svg viewBox="0 0 24 24" width="13" height="13"><path d="M4 4v5h5M20 20v-5h-5" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 15a7.5 7.5 0 0013.4 2.6M18.5 9a7.5 7.5 0 00-13.4-2.6" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round"/></svg>
                            Restablecer todo
                        </button>
                    </div>

                    <div class="design-tabs">
                        <button type="button" class="design-tab active" data-pane="frame">Marco</button>
                        <button type="button" class="design-tab" data-pane="shape">Forma</button>
                        <button type="button" class="design-tab" data-pane="level">Nivel</button>
                        <button type="button" class="design-tab" data-pane="logo">Logo</button>
                    </div>

                    <div class="design-pane active" id="design-pane-frame"></div>
                    <div class="design-pane" id="design-pane-shape"></div>
                    <div class="design-pane" id="design-pane-level"></div>
                    <div class="design-pane" id="design-pane-logo"></div>
                </div>
            </div>

            <!-- ---------- Columna derecha: vista previa ---------- -->
            <div class="panel preview-panel">
                <div class="step-head">
                    <div class="step-glyph n3"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
                    <div>
                        <div class="step-title">Descarga tu código QR</div>
                        <div class="step-sub">Listo para imprimir o compartir</div>
                    </div>
                </div>

                <div class="preview-canvas-wrap"><div id="preview-canvas"></div></div>
                <div class="preview-meta"><span>Tamaño de salida</span><b id="preview-dims">—</b></div>

                <div class="range-row">
                    <div class="range-row-head"><span>Tamaño de salida PNG</span><span id="png-size-label">1024 × 1024 px</span></div>
                    <div class="size-input-row">
                        <input type="range" id="png-size-slider" min="256" max="4096" step="16" value="1024">
                        <input type="number" id="png-size-input" min="256" max="4096" step="1" value="1024">
                    </div>
                </div>

                <button type="button" class="btn btn-primary" id="btn-png">
                    <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 18h14" stroke="currentColor" stroke-width="1.7" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Descargar PNG
                </button>
                <div class="export-row" style="grid-template-columns:1fr 1fr;margin-top:8px">
                    <button type="button" class="btn" id="btn-svg">
                        <svg viewBox="0 0 24 24" width="17" height="17"><path d="M6 3h9l4 4v14a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M15 3v4h4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                        SVG
                    </button>
                    <button type="button" class="btn" id="btn-pdf">
                        <svg viewBox="0 0 24 24" width="17" height="17"><path d="M6 3h9l4 4v14a1 1 0 01-1 1H6a1 1 0 01-1-1V4a1 1 0 011-1z" stroke="currentColor" stroke-width="1.5" fill="none"/><path d="M15 3v4h4" stroke="currentColor" stroke-width="1.5" fill="none"/></svg>
                        PDF
                    </button>
                </div>
                <div class="err-msg" id="err-msg"></div>
            </div>
        </div>

        <footer class="app-footer">Generado localmente en tu navegador — ningún dato se envía a un servidor.</footer>
    </div>

    <script src="{{ asset('assets/qr-generator/qrcode.js') }}"></script>
    <script src="{{ asset('assets/qr-generator/qrcode_utf8.js') }}"></script>
    <script src="{{ asset('assets/qr-generator/jspdf.umd.min.js') }}"></script>
    <script src="{{ asset('assets/qr-generator/qr-app.js') }}"></script>
</div>
