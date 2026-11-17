<?php

namespace App\Livewire\Admin\Users;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * Verwaltung der Admin-Benutzer. Jeder Benutzer hat vollen Zugriff
 * auf den Admin-Bereich, es gibt (noch) keine Rollen.
 */
class Index extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->editingId),
            ],
            /*
             * Beim Bearbeiten optional: leer lassen behält das
             * bisherige Passwort.
             */
            'password' => [
                $this->editingId ? 'nullable' : 'required',
                'confirmed',
                Password::min(8),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'password.confirmed' => 'Die Passwörter stimmen nicht überein.',
        ];
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->resetForm();

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(ActivityLogger $logger): void
    {
        $this->email = mb_strtolower(trim($this->email));
        $this->name = trim($this->name);

        $this->validate();

        $data = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $before = $user->only(['name', 'email']);

            $user->update($data);

            $changed = array_keys(array_diff_assoc($user->only(['name', 'email']), $before));

            if ($this->password !== '') {
                $changed[] = 'password';
            }

            $logger->log(
                ActivityLog::USER_UPDATED,
                'Benutzer geändert: '.$user->name
                    .($changed !== [] ? ' ('.implode(', ', $changed).')' : ''),
                $user,
                ['changed' => $changed],
            );

            $message = 'Benutzer '.$user->name.' wurde gespeichert.';
        } else {
            $user = User::create($data);

            $logger->log(
                ActivityLog::USER_CREATED,
                'Benutzer angelegt: '.$user->name.' ('.$user->email.')',
                $user,
            );

            $message = 'Benutzer '.$user->name.' wurde angelegt.';
        }

        $this->resetForm();

        session()->flash('userSaved', $message);
    }

    public function delete(int $id, ActivityLogger $logger): void
    {
        $this->resetErrorBag('delete');

        if ($id === auth()->id()) {
            $this->addError('delete', 'Du kannst dich nicht selbst löschen.');

            return;
        }

        $deleted = DB::transaction(function () use ($id): ?User {
            /*
             * Unter Sperre zählen, damit zwei gleichzeitige Löschungen
             * nicht gemeinsam den letzten Benutzer entfernen.
             */
            $users = User::query()->lockForUpdate()->get(['id', 'name', 'email']);

            $user = $users->firstWhere('id', $id);

            if (! $user || $users->count() <= 1) {
                return null;
            }

            /*
             * Offene Sitzungen beenden, sonst bliebe ein gelöschter
             * Benutzer bis zum Ablauf der Session angemeldet.
             */
            DB::table('sessions')->where('user_id', $user->id)->delete();

            User::query()->whereKey($user->id)->delete();

            return $user;
        });

        if (! $deleted) {
            $this->addError('delete', 'Der letzte Benutzer kann nicht gelöscht werden.');

            return;
        }

        if ($this->editingId === $deleted->id) {
            $this->resetForm();
        }

        $logger->log(
            ActivityLog::USER_DELETED,
            'Benutzer gelöscht: '.$deleted->name.' ('.$deleted->email.')',
            properties: ['user_id' => $deleted->id, 'email' => $deleted->email],
        );

        session()->flash('userSaved', 'Benutzer '.$deleted->name.' wurde gelöscht.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'showForm',
            'name',
            'email',
            'password',
            'password_confirmation',
        ]);

        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.admin.users.index', [
            'users' => User::query()
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
