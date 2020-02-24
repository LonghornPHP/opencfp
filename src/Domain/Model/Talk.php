<?php

declare(strict_types=1);

/**
 * Copyright (c) 2013-2020 OpenCFP
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 *
 * @see https://github.com/opencfp/opencfp
 */

namespace OpenCFP\Domain\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static Builder distinct(string $column)
 * @method static Builder recent(int $limit=30)
 * @method static Builder selected()
 * @method static Builder category()
 * @method static Builder type()
 * @method static Builder notViewedBy(int $userId)
 * @method static Builder notRatedBy(int $userId)
 * @method static Builder topRated()
 * @method static Builder ratedPlusOneBy(int $userId)
 * @method static Builder viewedBy(int $userId)
 * @method static Builder favoritedBy(int $userId)
 * @method static self create(array $attributes)
 * @method static self|null find($id, $columns = ['*'])
 *
 * @property int $id
 */
class Talk extends Eloquent
{
    public function speaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class, 'talk_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TalkComment::class);
    }

    public function meta(): HasMany
    {
        return $this->hasMany(TalkMeta::class, 'talk_id');
    }

    /**
     * Returns the most recent talks
     *
     * @param Builder $query
     * @param int     $limit maximum ammount of entries to return
     *
     * @return Builder
     */
    public function scopeRecent(Builder $query, int $limit = 30): Builder
    {
        return $query
            ->orderBy('created_at', 'desc')
            ->with(['favorites', 'meta'])
            ->take($limit);
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeSelected(Builder $query): Builder
    {
        return $query
            ->where('selected', '=', 1);
    }

    /**
     * @param Builder $query
     * @param string  $category
     *
     * @return Builder
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query
            ->where('category', '=', $category);
    }

    /**
     * @param Builder $query
     * @param string  $type
     *
     * @return Builder
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query
            ->where('type', '=', $type);
    }

    /**
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeViewedBy(Builder $query, int $userId): Builder
    {
        return $query
            ->whereHas('meta', function (Builder $query) use ($userId) {
                $query
                    ->where('admin_user_id', '=', $userId)
                    ->where('viewed', '=', 1);
            });
    }

    /**
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeFavoritedBy(Builder $query, int $userId): Builder
    {
        return $query
            ->whereHas('favorites', function (Builder $query) use ($userId) {
                $query->where('admin_user_id', '=', $userId);
            });
    }

    /**
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeRatedPlusOneBy(Builder $query, int $userId): Builder
    {
        return $query
            ->whereHas('meta', function (Builder $query) use ($userId) {
                $query
                   ->where('admin_user_id', '=', $userId)
                   ->where('rating', '=', 1);
            });
    }

    /**
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeNotRatedBy(Builder $query, int $userId): Builder
    {
        return $query
            ->whereDoesntHave('meta', function (Builder $query) use ($userId) {
                $query
                    ->where('admin_user_id', '=', $userId)
                    ->where('rating', '!=', 0);
            });
    }

    /**
     * @param Builder $query
     * @param int     $userId
     *
     * @return Builder
     */
    public function scopeNotViewedBy(Builder $query, int $userId): Builder
    {
        return $query
            ->whereDoesntHave('meta', function (Builder $query) use ($userId) {
                $query
                    ->where('admin_user_id', '=', $userId)
                    ->where('viewed', '!=', 0);
            });
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeTopRated(Builder $query): Builder
    {
        return $query->selectRaw('talks.*, sum(m.rating) as total')
            ->join('talk_meta as m', 'talks.id', '=', 'm.talk_id')
            ->groupBy('m.talk_id')
            ->havingRaw('total > 0')
            ->orderBy('total', 'desc');
    }

    /**
     * @throws \Exception
     *
     * @return null|bool
     */
    public function delete(): ?bool
    {
        $this->deleteComments();
        $this->deleteFavorites();
        $this->deleteMeta();

        return parent::delete();
    }

    /**
     * Deletes all comments of the talk
     *
     * @throws \Exception
     */
    public function deleteComments(): void
    {
        $this->comments()
            ->get()
            ->each(function (TalkComment $comment) {
                if (!$comment->delete()) {
                    throw new \RuntimeException('Unable to delete all comments');
                }
            });
    }

    /**
     * Deletes all favorites of the talk
     *
     * @throws \Exception
     */
    public function deleteFavorites(): void
    {
        $this->favorites()
            ->get()
            ->each(static function (Favorite $favorite) {
                if (!$favorite->delete()) {
                    throw new \RuntimeException('Unable to delete all favorites');
                }
            });
    }

    /**
     * Deletes all meta info of the talk
     *
     * @throws \Exception
     */
    public function deleteMeta(): void
    {
        $this->meta()
            ->get()
            ->each(static function (TalkMeta $meta) {
                if (!$meta->delete()) {
                    throw new \RuntimeException('Unable to delete all meta info');
                }
            });
    }

    /**
     * Gets the meta object of the current talk, with a specific Admin.
     *
     * @param int  $userId
     * @param bool $willCreate on true it will create a new model if it doesn't exists, on false
     *                         it will throw an error
     *
     * @throws ModelNotFoundException
     *
     * @return Model|static
     */
    public function getMetaFor(int $userId, bool $willCreate = false)
    {
        return $willCreate ? $this->getOrCreateMeta($userId) : $this->getOrFailMeta($userId);
    }

    /**
     * @param int $userId
     *
     * @return Model
     */
    private function getOrCreateMeta(int $userId): Model
    {
        return $this->meta()->firstOrCreate([
            'admin_user_id' => $userId,
            'talk_id'       => $this->id,
        ]);
    }

    /**
     * @param int $userId
     *
     * @return HasMany|Model
     */
    private function getOrFailMeta(int $userId)
    {
        return $this->meta()
            ->where('admin_user_id', $userId)
            ->where('talk_id', $this->id)
            ->firstOrFail();
    }
}
