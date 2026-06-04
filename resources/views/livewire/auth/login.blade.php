<div>
    <form wire:submit.prevent="login">

        <div>
            <input type="email" wire:model="email" placeholder="Email">
            @error('email') <div>{{ $message }}</div> @enderror
        </div>

        <div>
            <input type="password" wire:model="password" placeholder="Password">
            @error('password') <div>{{ $message }}</div> @enderror
        </div>

        <button type="submit">Login</button>

    </form>
</div>
