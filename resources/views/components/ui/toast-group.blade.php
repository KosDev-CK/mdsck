{{--
    top-20 (no top-4): la topbar mide h-16 (4rem) y no es position:fixed, así
    que un toast anclado a top-4 queda por encima de ella, encima del título/
    campanita/avatar. Con top-20 el toast flota dentro del área de trabajo
    (debajo de la topbar, a la derecha, sin tocar el sidebar).
--}}
<div class="pointer-events-none fixed right-4 top-20 z-[100] flex flex-col gap-2">
    {{ $slot }}
</div>
