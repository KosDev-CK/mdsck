<div class="space-y-6">
    @push('page-title')
        Destinatarios de reporte
    @endpush

    @push('page-actions')
        <x-ui.help-button />
    @endpush

    <x-ui.toast-group>
        @if (session('status'))
            <x-ui.toast variant="success">{{ session('status') }}</x-ui.toast>
        @endif

        @if (session('error'))
            <x-ui.toast variant="error">{{ session('error') }}</x-ui.toast>
        @endif
    </x-ui.toast-group>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Encuesta de satisfacción</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Formulario (de "Formularios") que se envía automáticamente al solicitante cuando su ticket pasa a
            estado completado. Mientras quede en "Ninguno", no se envía ningún correo automático.
        </p>

        <x-ui.select label="Formulario de encuesta" name="surveyFormId" wire:model.live="surveyFormId" class="max-w-md">
            <option value="">Ninguno (desactivada)</option>
            @foreach ($surveyForms as $form)
                <option value="{{ $form->id }}">{{ $form->name }}</option>
            @endforeach
        </x-ui.select>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">
            Supervisores (rol "Supervisor Mesa de Servicio")
        </h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Solo lectura — se gestiona asignando/quitando este rol a usuarios desde
            <a href="{{ route('user-roles.index') }}" class="text-primary hover:underline">Perfiles por usuario</a>.
        </p>

        <x-ui.table
            :headers="['Nombre', 'Correo']"
            :empty="$supervisors->isEmpty()"
            empty-title="Sin supervisores asignados"
            empty-description="Asigna el rol 'Supervisor Mesa de Servicio' a un usuario activo desde Perfiles por usuario."
        >
            @foreach ($supervisors as $supervisor)
                <tr wire:key="supervisor-{{ $supervisor->id }}" class="border-b border-gray-50 dark:border-gray-800">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $supervisor->name }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $supervisor->email }}</td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.card padding="p-5">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-1">Correos sueltos</h2>
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
            Destinatarios adicionales sin cuenta en el sistema, para avisos de cierre diario/mensual.
        </p>

        <form wire:submit="addEmail" class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 items-end mb-4">
            <x-ui.input label="Correo" name="newEmail" type="email" wire:model="newEmail" />
            <x-ui.input label="Nombre (opcional)" name="newNombre" wire:model="newNombre" />
            <x-ui.button type="submit">Agregar</x-ui.button>
        </form>

        <x-ui.table
            :headers="['Correo', 'Nombre', '']"
            :empty="$recipients->isEmpty()"
            empty-title="Sin correos agregados"
            empty-description="Agrega el primero con el formulario de arriba."
        >
            @foreach ($recipients as $recipient)
                <tr wire:key="recipient-{{ $recipient->id }}" class="border-b border-gray-50 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50 transition-colors">
                    <td class="py-2 font-medium text-gray-900 dark:text-gray-100">{{ $recipient->email }}</td>
                    <td class="py-2 text-gray-500 dark:text-gray-400">{{ $recipient->nombre }}</td>
                    <td class="py-2 text-right">
                        <x-ui.icon-button
                            wire:click="removeEmail({{ $recipient->id }})"
                            wire:confirm="¿Quitar este correo de la lista de destinatarios?"
                            icon="heroicon-o-trash"
                            title="Quitar"
                            variant="danger"
                        />
                    </td>
                </tr>
            @endforeach
        </x-ui.table>
    </x-ui.card>

    <x-ui.help-modal titulo="Destinatarios de reporte" :pdf-url="route('mesaservicio.ayuda.pdf', 'destinatarios')">
        @include('mesaservicio::ayuda.contenido', ['contenido' => \Modules\MesaServicio\Support\Ayuda\AyudaCatalog::contenido('destinatarios')])
    </x-ui.help-modal>
</div>
