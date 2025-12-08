<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class UpdatePost extends Model
{
    public const TYPE_BLOG = 'blog';
    public const TYPE_WHATS_NEW = 'whats_new';

    protected $fillable = [
        'type',
        'title',
        'slug',
        'excerpt',
        'body',
        'version',
        'image_path',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('is_published', true)
            ->where(function (Builder $inner) {
                $inner
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', Carbon::now());
            });
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->image_path) {
            return null;
        }

        $path = ltrim(str_replace('public/', '', $this->image_path), '/');
        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $request = request();
        if ($request) {
            $base = rtrim($request->getSchemeAndHttpHost().$request->getBasePath(), '/');
            return $base.'/storage/'.$path;
        }

        return $disk->url($path);
    }

    public function getIsLiveAttribute(): bool
    {
        if (!$this->is_published) {
            return false;
        }

        return !$this->published_at || $this->published_at->isPast();
    }
}
