<?php

declare(strict_types=1);

namespace Agus\Database;

/**
 * A simple Active Record base class. One subclass per table:
 *
 *   class Listing extends Model {
 *       protected static string $table = 'listings';
 *       protected static array $fillable = ['name', 'price', 'shop_id'];
 *   }
 *
 *   $listing = Listing::find(5);
 *   $listing->price = 25;
 *   $listing->save();
 *   Listing::create(['name' => 'Pandesal', 'price' => 25, 'shop_id' => 1]);
 *   Listing::query()->where('price', '<', 50)->get();   // returns Listing objects
 */
abstract class Model implements \JsonSerializable
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    /** Columns that create()/fill() may set from user input (mass-assignment protection). */
    protected static array $fillable = [];

    /** Columns left out of toArray()/JSON, e.g. password hashes. */
    protected static array $hidden = [];

    /** Set created_at / updated_at automatically. */
    protected static bool $timestamps = true;

    protected array $attributes = [];
    protected bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    public static function table(): string
    {
        if (static::$table === '') {
            // Listing -> listings, Category -> categorys (set $table when the plural is irregular)
            $short = (new \ReflectionClass(static::class))->getShortName();
            return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $short)) . 's';
        }
        return static::$table;
    }

    public static function query(): QueryBuilder
    {
        return (new QueryBuilder(static::table()))->asModel(static::class);
    }

    public static function fromRow(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->exists = true;
        return $model;
    }

    /** @return static[] */
    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find(int|string $id): ?static
    {
        return static::query()->where(static::$primaryKey, $id)->first();
    }

    public static function findOrFail(int|string $id): static
    {
        return static::find($id) ?? throw new \Agus\Exceptions\HttpException(404);
    }

    public static function where(string $column, mixed $operator, mixed $value = null): QueryBuilder
    {
        return func_num_args() === 2
            ? static::query()->where($column, $operator)
            : static::query()->where($column, $operator, $value);
    }

    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /** Only keys listed in $fillable are accepted. */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, static::$fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    public function save(): bool
    {
        $now = date('Y-m-d H:i:s');
        $data = $this->attributes;
        unset($data[static::$primaryKey]);

        if ($this->exists) {
            if (static::$timestamps) {
                $data['updated_at'] = $this->attributes['updated_at'] = $now;
            }
            (new QueryBuilder(static::table()))->where(static::$primaryKey, $this->getKey())->update($data);
            return true;
        }

        if (static::$timestamps) {
            $data['created_at'] = $this->attributes['created_at'] = $this->attributes['created_at'] ?? $now;
            $data['updated_at'] = $this->attributes['updated_at'] = $now;
        }
        $id = (new QueryBuilder(static::table()))->insert($data);
        $this->attributes[static::$primaryKey] = $id;
        $this->exists = true;
        return true;
    }

    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        (new QueryBuilder(static::table()))->where(static::$primaryKey, $this->getKey())->delete();
        $this->exists = false;
        return true;
    }

    /** Re-reads the row from the database. */
    public function refresh(): static
    {
        $fresh = static::find($this->getKey());
        if ($fresh) {
            $this->attributes = $fresh->attributes;
        }
        return $this;
    }

    public function getKey(): mixed
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    // ---- simple relationships ---------------------------------------------

    /** $listing->belongsTo(Shop::class, 'shop_id') */
    protected function belongsTo(string $related, string $foreignKey): ?Model
    {
        $id = $this->attributes[$foreignKey] ?? null;
        return $id === null ? null : $related::find($id);
    }

    /** $shop->hasMany(Listing::class, 'shop_id') */
    protected function hasMany(string $related, string $foreignKey): array
    {
        return $related::query()->where($foreignKey, $this->getKey())->get();
    }

    // ---- attribute access ---------------------------------------------------

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /** Direct property writes bypass $fillable on purpose; use them for trusted values. */
    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function toArray(): array
    {
        return array_diff_key($this->attributes, array_flip(static::$hidden));
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
