<div class="p-6">

    <h1 class="text-xl font-bold mb-4">Geräteverwaltung</h1>

    <table border="1" cellpadding="10" width="100%">
        <thead>
        <tr>
            <th>Name</th>
            <th>Platform</th>
            <th>Status</th>
            <th>Aktionen</th>
        </tr>
        </thead>

        <tbody>
        @foreach($devices as $device)
            <tr>
                <td>{{ $device->name }}</td>
                <td>{{ $device->platform }}</td>
                <td>
                    <strong>{{ $device->status }}</strong>
                </td>
                <td>

                    @if($device->status !== 'approved')
                        <button wire:click="approve({{ $device->id }})">
                            Approve
                        </button>
                    @endif

                    @if($device->status !== 'blocked')
                        <button wire:click="block({{ $device->id }})">
                            Block
                        </button>
                    @endif

                </td>
            </tr>
        @endforeach
        </tbody>

    </table>

</div>
