<?php

namespace App\Livewire\Account;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Account menu in the top-right of every page: who is signed in, change password,
 * change picture and sign out. Everything here acts on the signed-in user only.
 */
class Menu extends Component
{
    use WithFileUploads;

    /** null | password | picture */
    public ?string $modal = null;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $photo;

    public function open(string $modal): void
    {
        $this->closeModal();
        $this->modal = in_array($modal, ['password', 'picture'], true) ? $modal : null;
    }

    public function closeModal(): void
    {
        $this->reset(['modal', 'current_password', 'password', 'password_confirmation', 'photo']);
        $this->resetValidation();
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)],
        ], ['password.different' => 'Choose a password you are not already using.'], ['password' => 'new password']);

        $user = Auth::user();
        $user->forceFill(['password' => $this->password])->save();

        // Sign out this account everywhere else, keep this session.
        Auth::logoutOtherDevices($this->password);
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        activity('password_changed', $user->name.' changed their password', $user);
        $this->closeModal();
        $this->dispatch('toast', message: 'Password changed.');
    }

    public function savePicture()
    {
        $this->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
        ], [], ['photo' => 'picture']);

        $user = Auth::user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->update(['avatar_path' => $this->photo->store('avatars', 'local')]);

        session()->flash('status', 'Profile picture updated.');

        // Reload so the new picture shows everywhere on the page.
        return $this->redirect(request()->header('Referer') ?: route('dashboard'));
    }

    public function removePicture()
    {
        $user = Auth::user();
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        session()->flash('status', 'Profile picture removed.');

        return $this->redirect(request()->header('Referer') ?: route('dashboard'));
    }

    public function render()
    {
        return view('livewire.account.menu', ['user' => Auth::user()]);
    }
}
