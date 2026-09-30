<?php

namespace App\Livewire\Admin\ActivityLog;

use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $period = 'today';

    #[Url]
    public string $event = '';

    #[Url]
    public ?int $userId = null;

    #[Url]
    public ?int $deviceId = null;

    #[Url]
    public string $search = '';

    public ?int $expandedId = null;

    public function updated(string $property): void
    {
        if (in_array($property, ['period', 'event', 'userId', 'deviceId', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['period', 'event', 'userId', 'deviceId', 'search']);

        $this->resetPage();
    }

    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    private function logQuery(): Builder
    {
        return ActivityLog::query()
            ->when(
                $this->period === 'today',
                fn (Builder $query) => $query->whereDate('created_at', today())
            )
            ->when(
                $this->period === 'yesterday',
                fn (Builder $query) => $query->whereDate('created_at', today()->subDay())
            )
            ->when(
                $this->period === 'week',
                fn (Builder $query) => $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek(),
                ])
            )
            ->when(
                $this->period === 'month',
                fn (Builder $query) => $query->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
            )
            ->when(
                $this->event !== '',
                fn (Builder $query) => $query->where('event', $this->event)
            )
            ->when(
                $this->userId,
                fn (Builder $query) => $query->where('user_id', $this->userId)
            )
            ->when(
                $this->deviceId,
                fn (Builder $query) => $query->where('device_id', $this->deviceId)
            )
            ->when(
                trim($this->search) !== '',
                function (Builder $query): void {
                    /*
                     * lower() statt ilike, damit die Suche auch unter
                     * SQLite (Tests) funktioniert.
                     */
                    $search = '%'.mb_strtolower(trim($this->search)).'%';

                    $query->where(function (Builder $searchQuery) use ($search): void {
                        $searchQuery
                            ->whereRaw('lower(description) like ?', [$search])
                            ->orWhereRaw('lower(user_name) like ?', [$search])
                            ->orWhereRaw('lower(device_name) like ?', [$search]);
                    });
                }
            );
    }

    public function render()
    {
        return view('livewire.admin.activity-log.index', [
            'entries' => $this->logQuery()
                ->latest('created_at')
                ->latest('id')
                ->paginate(50),

            'events' => ActivityLog::eventLabels(),

            'users' => User::query()->orderBy('name')->get(),

            'devices' => Device::query()->orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
