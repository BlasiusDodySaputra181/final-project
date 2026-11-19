<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ArtificialIntelligence extends Model
{
    /**
     * Groq shut llama-3.3-70b-versatile down on 2026-08-16.
     * This is the supported replacement used for cover reading.
     */
    public const DEFAULT_MODEL = 'openai/gpt-oss-120b';

    /**
     * Model ids Groq no longer serves on the developer tier, mapped to a live replacement.
     *
     * @var array<string, string>
     */
    private const RETIRED_MODELS = [
        'llama-3.3-70b-versatile' => self::DEFAULT_MODEL,
        'llama-3.3-70b-specdec' => self::DEFAULT_MODEL,
        'llama-3.1-70b-versatile' => self::DEFAULT_MODEL,
        'llama-3.1-70b-specdec' => self::DEFAULT_MODEL,
        'llama3-70b-8192' => self::DEFAULT_MODEL,
        'deepseek-r1-distill-llama-70b' => self::DEFAULT_MODEL,
        'llama-3.1-8b-instant' => 'openai/gpt-oss-20b',
        'llama3-8b-8192' => 'openai/gpt-oss-20b',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'model',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'key',
    ];

    public static function apiKey(): string
    {
        $stored = self::normalizeKey(self::currentValue('key'));

        if ($stored !== null) {
            return $stored;
        }

        return self::normalizeKey(config('services.groq.key')) ?? '';
    }

    public static function modelName(): string
    {
        $stored = self::currentValue('model');

        if (is_string($stored) && self::validModel(trim($stored))) {
            return self::activeModel(trim($stored));
        }

        $fallback = trim((string) config('services.groq.model', self::DEFAULT_MODEL));

        return self::activeModel($fallback);
    }

    /**
     * Replace a retired Groq model id with one that can still read a cover.
     */
    public static function activeModel(string $model): string
    {
        $model = trim($model);

        if ($model === '') {
            return self::DEFAULT_MODEL;
        }

        return self::RETIRED_MODELS[$model] ?? $model;
    }

    public static function hasKey(): bool
    {
        return self::normalizeKey(self::currentValue('key')) !== null;
    }

    /**
     * True only after Pengaturan has saved a usable key and model.
     * A fresh install, with an empty table and no saved change, is not ready.
     */
    public static function isStored(): bool
    {
        try {
            if (! Schema::hasTable('artificial_intelligences')) {
                return false;
            }

            $row = static::query()->orderBy('id')->first(['key', 'model']);
        } catch (Throwable) {
            return false;
        }

        if ($row === null) {
            return false;
        }

        $model = is_string($row->getAttribute('model')) ? trim($row->getAttribute('model')) : '';

        return self::normalizeKey($row->getAttribute('key')) !== null
            && self::validModel($model);
    }

    /**
     * Shown when import starts before any key and model exist in the database.
     */
    public static function missingStorageMessage(): string
    {
        return 'Kunci dan model kecerdasan buatan belum ada sama sekali di basis data, atau pemasangan masih awal dan belum ada perubahan. Isi kunci dan model AI terlebih dahulu di Pengaturan, atau pilih basis data terkait proyek dari aplikasi final-project ini di Peladen Lokal.';
    }

    /**
     * Drop a Bearer prefix and spaces so the value stored from Pengaturan can be sent as a token.
     */
    public static function prepareKey(string $value): string
    {
        $token = trim($value);
        $token = preg_replace('/\Abearer\s+/i', '', $token) ?? $token;

        return preg_replace('/\s+/', '', $token) ?? '';
    }

    /**
     * The key saved from Pengaturan, ready to send, or null when it is missing or unusable.
     */
    private static function normalizeKey(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $token = self::prepareKey($value);

        return self::validKey($token) ? $token : null;
    }

    public static function validKey(string $token): bool
    {
        $token = trim($token);
        $length = strlen($token);

        return $length >= 20
            && $length <= 4096
            && preg_match('/\A[A-Za-z0-9_\-]+\z/', $token) === 1;
    }

    public static function validModel(string $model): bool
    {
        $model = trim($model);

        return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]{1,119}\z/', $model) === 1;
    }

    private static function currentValue(string $column): mixed
    {
        try {
            if (! Schema::hasTable('artificial_intelligences')) {
                return null;
            }

            return static::query()->orderBy('id')->value($column);
        } catch (Throwable) {
            return null;
        }
    }
};
