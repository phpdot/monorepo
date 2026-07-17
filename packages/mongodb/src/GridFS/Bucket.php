<?php

declare(strict_types=1);

/**
 * GridFS wrapper for storing and retrieving large files.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\GridFS;

use MongoDB\BSON\ObjectId;
use MongoDB\GridFS\Bucket as GridFSBucket;
use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\MongoConnection;

final class Bucket
{
    private readonly GridFSBucket $bucket;

    /**
     * Open a GridFS bucket on the connection for streaming file storage.
     *
     * @param MongoConnection $connection The MongoDB connection
     * @param string $bucketName Bucket name (default: 'fs')
     * @param array<string, mixed> $options Additional bucket options
     */
    public function __construct(
        MongoConnection $connection,
        string $bucketName = 'fs',
        array $options = [],
    ) {
        $this->bucket = $connection->getDatabase()->selectGridFSBucket([
            'bucketName' => $bucketName,
            ...$options,
        ]);
    }

    /**
     * Upload a file from a stream.
     *
     * @param string $filename Filename to store
     * @param resource $source Source stream
     * @param array<string, mixed> $options Upload options (metadata, chunkSizeBytes, etc.)
     *
     * @return ObjectId
     */
    public function uploadFromStream(string $filename, mixed $source, array $options = []): ObjectId
    {
        /**
         * @var ObjectId
         */
        return $this->bucket->uploadFromStream($filename, $source, $options);
    }

    /**
     * Download a file to a stream.
     *
     * @param ObjectId $id File ID
     * @param resource $destination Destination stream
     *
     * @return void
     */
    public function downloadToStream(ObjectId $id, mixed $destination): void
    {
        $this->bucket->downloadToStream($id, $destination);
    }

    /**
     * Open a readable stream for a file.
     *
     * @param ObjectId $id
     *
     * @return resource
     */
    public function openDownloadStream(ObjectId $id): mixed
    {
        return $this->bucket->openDownloadStream($id);
    }

    /**
     * Open a writable stream for uploading.
     *
     * @param array<string, mixed> $options
     * @param string $filename
     *
     * @return resource
     */
    public function openUploadStream(string $filename, array $options = []): mixed
    {
        return $this->bucket->openUploadStream($filename, $options);
    }

    /**
     * Delete a file by ID.
     *
     * @param ObjectId $id
     *
     * @return void
     */
    public function delete(ObjectId $id): void
    {
        $this->bucket->delete($id);
    }

    /**
     * Find files matching a filter.
     *
     * @param array<string, mixed> $filter
     * @param array<string, mixed> $options
     *
     * @return Cursor
     */
    public function find(array $filter = [], array $options = []): Cursor
    {
        return new Cursor($this->bucket->find($filter, $options));
    }

    /**
     * Rename a file.
     *
     * @param string $newFilename
     * @param ObjectId $id
     *
     * @return void
     */
    public function rename(ObjectId $id, string $newFilename): void
    {
        $this->bucket->rename($id, $newFilename);
    }

    /**
     * Drop the entire bucket (files and chunks collections).
     *
     * @return void
     */
    public function drop(): void
    {
        $this->bucket->drop();
    }

    /**
     * Get the underlying GridFS\Bucket. Escape hatch.
     *
     * @return GridFSBucket
     */
    public function raw(): GridFSBucket
    {
        return $this->bucket;
    }
}
