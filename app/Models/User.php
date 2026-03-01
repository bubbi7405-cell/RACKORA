<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\GameLog;
use App\Models\Research;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company_name',
        'company_logo',
        'email',
        'password',
        'is_admin',
        'specialization',
        'specialization_updated_at',
        'tutorial_step',
        'tutorial_completed',
        'avatar',
        'banner',
        'slogan',
        'accent_color',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_admin' => 'boolean',
            'is_banned' => 'boolean',
            'tutorial_completed' => 'boolean',
            'specialization_updated_at' => 'datetime',
            'last_summary_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    /**
     * Get the player's economy data.
     */
    public function network(): HasOne
    {
        return $this->hasOne(PlayerNetwork::class);
    }

    public function privateNetworks(): HasMany
    {
        return $this->hasMany(PrivateNetwork::class);
    }

    public function economy(): HasOne
    {
        return $this->hasOne(PlayerEconomy::class);
    }

    /**
     * Get all rooms owned by the player.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(GameRoom::class);
    }

    /**
     * Get all customers of the player.
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Get all customer orders for the player.
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(CustomerOrder::class, Customer::class);
    }

    /**
     * Get all game events for the player.
     */
    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    /**
     * Get all servers owned by the player (via Room -> Rack -> Server)
     */
    public function servers()
    {
        return Server::whereHas('rack.room', function ($q) {
            $q->where('user_id', $this->id);
        });
    }

    /**
     * Get all achievements for the player.
     */
    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }

    /**
     * Get all certifications earned by the user.
     */
    public function certifications(): BelongsToMany
    {
        return $this->belongsToMany(Certificate::class, 'user_certificates')
            ->withPivot('issued_at', 'expires_at')
            ->withTimestamps();
    }

    /**
     * Get all compliance audits for the user.
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ComplianceAudit::class);
    }

    /**
     * Get all employees of the player.
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function serverTemplates(): HasMany
    {
        return $this->hasMany(ServerTemplate::class);
    }

    /**
     * Helper to unlock an achievement for this user.
     */
    public function unlockAchievement(string $key): bool
    {
        $achievement = Achievement::where('key', $key)->first();
        if (!$achievement) return false;

        if (!$this->achievements()->where('achievement_id', $achievement->id)->exists()) {
            $this->achievements()->attach($achievement->id, ['unlocked_at' => now()]);
            
            // Broadcast the event for real-time UI notification
            event(new \App\Events\AchievementUnlocked($this, $achievement));
            
            GameLog::log($this, "🏆 Achievement Unlocked: {$achievement->name}", 'info', 'milestone');
            return true;
        }
        
        return false;
    }
    public function isResearched(string $techId): bool
    {
        return Research::where('user_id', $this->id)
            ->where('tech_id', $techId)
            ->where('status', 'completed')
            ->exists();
    }

    public function hq(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CorporateHq::class);
    }
}
