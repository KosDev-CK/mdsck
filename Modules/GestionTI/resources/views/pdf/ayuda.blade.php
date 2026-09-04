<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ayuda — {{ $contenido['titulo'] }}</title>
    <style>
        {{-- Márgenes de página = alto exacto del encabezado/pie (imágenes ya
             armadas por el usuario, 2550x355px y 2550x95px a 300dpi = 3cm y
             8mm de alto a todo el ancho de carta) — SIN margen izquierdo ni
             derecho a nivel de página, para que ambas imágenes lleguen al
             borde real sin ningún truco de margen negativo horizontal. El
             padding lateral del texto se maneja aparte, en .content. --}}
        @page { margin: 3cm 0 8mm 0; }

        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; margin: 0; padding: 0; }

        header { position: fixed; top: -3cm; left: 0; right: 0; height: 3cm; }
        header img { width: 100%; height: 100%; display: block; }

        footer { position: fixed; bottom: -8mm; left: 0; right: 0; height: 8mm; }
        footer img { width: 100%; height: 100%; display: block; }

        .content { padding: 16px 28px 0 28px; }

        h1 { font-size: 16px; margin: 0 0 12px; color: #1f2937; }
        h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #d1d5db; padding-bottom: 4px; color: #E97132; }
        p { margin: 0 0 8px; line-height: 1.5; }
        ol { margin: 0 0 8px 18px; padding: 0; }
        li { margin-bottom: 4px; }
        table.campos { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.campos td { padding: 6px 4px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
        td.campo-nombre { font-weight: bold; width: 180px; }
    </style>
</head>
<body>
    <header><img src="{{ $cabecera }}" alt=""></header>
    <footer><img src="{{ $pie }}" alt=""></footer>

    <div class="content">
        <h1>Ayuda — {{ $contenido['titulo'] }}</h1>

        <h2>¿Qué es esta pantalla?</h2>
        <p>{{ $contenido['concepto'] }}</p>

        <h2>¿Qué resuelve?</h2>
        <p>{{ $contenido['resuelve'] }}</p>

        @if (! empty($contenido['proceso']))
            <h2>Proceso de llenado</h2>
            <ol>
                @foreach ($contenido['proceso'] as $paso)
                    <li>{{ $paso }}</li>
                @endforeach
            </ol>
        @endif

        @if (! empty($contenido['campos']))
            <h2>Explicación de cada campo</h2>
            <table class="campos">
                @foreach ($contenido['campos'] as $campo)
                    <tr>
                        <td class="campo-nombre">{{ $campo['nombre'] }}</td>
                        <td>{{ $campo['explicacion'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>
</body>
</html>
