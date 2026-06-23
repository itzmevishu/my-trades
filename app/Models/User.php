<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        \Log::info('[canAccessPanel] Method called', [
            'user_id' => $this->id,
            'user_email' => $this->email,
            'panel_id' => $panel->getId(),
            'auth_check' => \Auth::check(),
            'auth_user_id' => \Auth::id(),
            'session_id' => session()->getId(),
        ]);
        
        $canAccess = true;
        
        \Log::info('[canAccessPanel] Returning', [
            'user_id' => $this->id,
            'can_access' => $canAccess,
        ]);
        
        return $canAccess;
    }
}
