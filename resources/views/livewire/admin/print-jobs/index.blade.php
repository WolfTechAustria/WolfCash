<div>

    <h1>Druckjobs</h1>

    <table border="1" cellpadding="10" width="100%">
        <thead>
        <tr>
            <th>ID</th>
            <th>Zeit</th>
            <th>Tisch</th>
            <th>Drucker</th>
            <th>Typ</th>
            <th>Status</th>
            <th>Payload</th>
            <th>Aktion</th>
        </tr>
        </thead>

        <tbody>
        @foreach($jobs as $job)

            <tr>
                <td>{{ $job->id }}</td>

                <td>{{ $job->created_at->format('H:i:s') }}</td>

                <td>{{ $job->order?->table?->number }}</td>

                <td>{{ $job->printer?->name }}</td>

                <td>{{ $job->type }}</td>

                <td>
                    {{ $job->status }}

                    @if($job->error_message)
                        <br>
                        <small style="color:red;">
                            {{ $job->error_message }}
                        </small>
                    @endif
                </td>

                <td>
                    <pre style="white-space:pre-wrap;font-size:12px;">
{{ json_encode($job->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}
                    </pre>
                </td>

                <td>
                    <button wire:click="markPrinted({{ $job->id }})">
                        Gedruckt
                    </button>

                    <button wire:click="markFailed({{ $job->id }})">
                        Fehler
                    </button>

                    <button wire:click="retry({{ $job->id }})">
                        Erneut
                    </button>
                </td>
            </tr>

        @endforeach
        </tbody>
    </table>

</div>
