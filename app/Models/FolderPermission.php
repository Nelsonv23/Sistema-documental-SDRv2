<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolderPermission extends Model
{
    use HasFactory;

    public const SUBJECT_ROLE = 'role';

    public const SUBJECT_USER = 'user';

    public const ACCESS_ALLOW = 'allow';

    public const ACCESS_DENY = 'deny';

    protected $fillable = [
        'folder_id',
        'subject_type',
        'subject_id',
        'access',
        'created_by',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'folder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  array<int, int|string>  $roleIds
     */
    public function scopeForRoles(Builder $query, array $roleIds): Builder
    {
        return $query->where('subject_type', self::SUBJECT_ROLE)
            ->whereIn('subject_id', $roleIds);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('subject_type', self::SUBJECT_USER)
            ->where('subject_id', $userId);
    }
}
