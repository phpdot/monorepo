<?php

declare(strict_types=1);

/**
 * Filesystem-based storage for queued messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Storage;

use PHPdot\Mail\Gateway\DataType\DTO\MessageEnvelope;
use PHPdot\Mail\Gateway\DataType\Enum\QueueState;
use PHPdot\Mail\Gateway\Exception\StorageException;

/**
 * Stores .eml files and JSON metadata on the local filesystem.
 *
 * Directory structure:
 *   {basePath}/pending/{id}.eml
 *   {basePath}/pending/{id}.json
 *   {basePath}/sending/{id}.eml
 *   {basePath}/sending/{id}.json
 *   {basePath}/sent/{id}.eml
 *   {basePath}/sent/{id}.json
 *   {basePath}/failed/{id}.eml
 *   {basePath}/failed/{id}.json
 */
final class FilesystemStorage implements StorageInterface
{
    private readonly string $basePath;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
        $this->ensureDirectories();
    }

    public function store(MessageEnvelope $envelope, string $eml): void
    {
        $dir = $this->stateDir($envelope->state);
        $emlPath = $dir . '/' . $envelope->id . '.eml';
        $metaPath = $dir . '/' . $envelope->id . '.json';

        if (file_put_contents($emlPath, $eml) === false) {
            throw new StorageException('Failed to write .eml: ' . $emlPath);
        }

        $json = json_encode($envelope->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (file_put_contents($metaPath, $json) === false) {
            @unlink($emlPath);
            throw new StorageException('Failed to write metadata: ' . $metaPath);
        }
    }

    public function retrieve(string $id): string
    {
        $path = $this->findFile($id, '.eml');
        if ($path === null) {
            throw new StorageException('Message not found: ' . $id);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new StorageException('Failed to read .eml: ' . $path);
        }

        return $content;
    }

    public function getEnvelope(string $id): MessageEnvelope
    {
        $path = $this->findFile($id, '.json');
        if ($path === null) {
            throw new StorageException('Envelope not found: ' . $id);
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new StorageException('Failed to read metadata: ' . $path);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return MessageEnvelope::fromArray($data);
    }

    public function updateEnvelope(MessageEnvelope $envelope): void
    {

        $currentPath = $this->findFile($envelope->id, '.json');
        $currentEml = $this->findFile($envelope->id, '.eml');

        $newDir = $this->stateDir($envelope->state);
        $newMetaPath = $newDir . '/' . $envelope->id . '.json';
        $newEmlPath = $newDir . '/' . $envelope->id . '.eml';

        $json = json_encode($envelope->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        if (file_put_contents($newMetaPath, $json) === false) {
            throw new StorageException('Failed to update metadata');
        }

        if ($currentEml !== null && $currentEml !== $newEmlPath) {
            if (!rename($currentEml, $newEmlPath)) {
                throw new StorageException('Failed to move .eml to new state directory');
            }
        }

        if ($currentPath !== null && $currentPath !== $newMetaPath) {
            @unlink($currentPath);
        }
    }

    public function listByState(QueueState $state, int $limit = 100): array
    {
        $dir = $this->stateDir($state);
        $ids = [];

        $files = @scandir($dir);
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            if (str_ends_with($file, '.json')) {
                $ids[] = substr($file, 0, -5);
                if (count($ids) >= $limit) {
                    break;
                }
            }
        }

        return $ids;
    }

    public function listRetryable(int $limit = 100): array
    {
        $now = new \DateTimeImmutable();
        $ids = [];

        foreach ($this->listByState(QueueState::Pending, $limit * 2) as $id) {
            try {
                $envelope = $this->getEnvelope($id);
                if ($envelope->nextRetryAt !== null && $envelope->nextRetryAt <= $now) {
                    $ids[] = $id;
                } elseif ($envelope->nextRetryAt === null && $envelope->attempts === 0) {

                    $ids[] = $id;
                }

                if (count($ids) >= $limit) {
                    break;
                }
            } catch (StorageException) {
                continue;
            }
        }

        return $ids;
    }

    public function delete(string $id): void
    {
        $emlPath = $this->findFile($id, '.eml');
        $metaPath = $this->findFile($id, '.json');

        if ($emlPath !== null) {
            @unlink($emlPath);
        }
        if ($metaPath !== null) {
            @unlink($metaPath);
        }
    }

    public function stats(): array
    {
        $stats = [];
        foreach (QueueState::cases() as $state) {
            $dir = $this->stateDir($state);
            $files = @scandir($dir);
            $count = 0;
            if ($files !== false) {
                foreach ($files as $file) {
                    if (str_ends_with($file, '.json')) {
                        $count++;
                    }
                }
            }
            $stats[$state->value] = $count;
        }

        return $stats;
    }

    private function stateDir(QueueState $state): string
    {
        return $this->basePath . '/' . $state->value;
    }

    /**
     * Find a file across all state directories.
     */
    private function findFile(string $id, string $extension): string|null
    {
        foreach (QueueState::cases() as $state) {
            $path = $this->stateDir($state) . '/' . $id . $extension;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function ensureDirectories(): void
    {
        foreach (QueueState::cases() as $state) {
            $dir = $this->stateDir($state);
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new StorageException('Failed to create directory: ' . $dir);
                }
            }
        }
    }
}
