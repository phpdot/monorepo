<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use MongoDB\BSON\ObjectId;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Config\MongoConfig;
use PHPdot\MongoDB\GridFS\Bucket;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GridFSTest extends TestCase
{
    use RequiresMongo;

    private MongoConnection $connection;
    private Bucket $bucket;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = new MongoConfig(database: 'phpdot_test');
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $this->bucket = new Bucket($this->connection, 'test_fs');
        $this->bucket->drop();
    }

    protected function tearDown(): void
    {
        if (isset($this->bucket)) {
            $this->bucket->drop();
        }

        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_uploads_and_downloads_a_file(): void
    {
        $content = 'Hello, GridFS!';
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, $content);
        rewind($source);

        $id = $this->bucket->uploadFromStream('test.txt', $source);
        fclose($source);

        self::assertInstanceOf(ObjectId::class, $id);

        // Download
        $dest = fopen('php://memory', 'r+');
        self::assertIsResource($dest);
        $this->bucket->downloadToStream($id, $dest);
        rewind($dest);

        $downloaded = stream_get_contents($dest);
        fclose($dest);

        self::assertSame($content, $downloaded);
    }

    #[Test]
    public function it_uploads_with_metadata(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'data');
        rewind($source);

        $id = $this->bucket->uploadFromStream('meta.txt', $source, [
            'metadata' => ['author' => 'Omar', 'type' => 'test'],
        ]);
        fclose($source);

        $files = $this->bucket->find(['_id' => $id])->toArray();
        self::assertCount(1, $files);
    }

    #[Test]
    public function it_opens_download_stream(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'stream content');
        rewind($source);

        $id = $this->bucket->uploadFromStream('stream.txt', $source);
        fclose($source);

        $stream = $this->bucket->openDownloadStream($id);
        $content = stream_get_contents($stream);
        fclose($stream);

        self::assertSame('stream content', $content);
    }

    #[Test]
    public function it_opens_upload_stream(): void
    {
        $stream = $this->bucket->openUploadStream('upload.txt');
        fwrite($stream, 'uploaded via stream');
        fclose($stream);

        $files = $this->bucket->find(['filename' => 'upload.txt'])->toArray();
        self::assertCount(1, $files);
    }

    #[Test]
    public function it_deletes_a_file(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'to delete');
        rewind($source);

        $id = $this->bucket->uploadFromStream('delete_me.txt', $source);
        fclose($source);

        $this->bucket->delete($id);

        $files = $this->bucket->find(['_id' => $id])->toArray();
        self::assertCount(0, $files);
    }

    #[Test]
    public function it_renames_a_file(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'rename me');
        rewind($source);

        $id = $this->bucket->uploadFromStream('old_name.txt', $source);
        fclose($source);

        $this->bucket->rename($id, 'new_name.txt');

        $files = $this->bucket->find(['filename' => 'new_name.txt'])->toArray();
        self::assertCount(1, $files);

        $files = $this->bucket->find(['filename' => 'old_name.txt'])->toArray();
        self::assertCount(0, $files);
    }

    #[Test]
    public function it_finds_files(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $source = fopen('php://memory', 'r+');
            self::assertIsResource($source);
            fwrite($source, "file {$i}");
            rewind($source);
            $this->bucket->uploadFromStream("file_{$i}.txt", $source);
            fclose($source);
        }

        $files = $this->bucket->find()->toArray();
        self::assertCount(3, $files);
    }

    #[Test]
    public function it_finds_files_with_filter(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'target');
        rewind($source);
        $this->bucket->uploadFromStream('target.txt', $source);
        fclose($source);

        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'other');
        rewind($source);
        $this->bucket->uploadFromStream('other.txt', $source);
        fclose($source);

        $files = $this->bucket->find(['filename' => 'target.txt'])->toArray();
        self::assertCount(1, $files);
    }

    #[Test]
    public function it_drops_the_bucket(): void
    {
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'data');
        rewind($source);
        $this->bucket->uploadFromStream('file.txt', $source);
        fclose($source);

        $this->bucket->drop();

        $files = $this->bucket->find()->toArray();
        self::assertCount(0, $files);
    }

    #[Test]
    public function it_uses_custom_bucket_name(): void
    {
        $customBucket = new Bucket($this->connection, 'custom_bucket');
        $customBucket->drop();

        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, 'custom');
        rewind($source);
        $customBucket->uploadFromStream('custom.txt', $source);
        fclose($source);

        $files = $customBucket->find()->toArray();
        self::assertCount(1, $files);

        // Should not be in the default bucket
        $defaultFiles = $this->bucket->find(['filename' => 'custom.txt'])->toArray();
        self::assertCount(0, $defaultFiles);

        $customBucket->drop();
    }

    #[Test]
    public function it_returns_raw_bucket(): void
    {
        self::assertInstanceOf(\MongoDB\GridFS\Bucket::class, $this->bucket->raw());
    }

    #[Test]
    public function it_uploads_large_file(): void
    {
        // 1MB file
        $data = str_repeat('A', 1024 * 1024);
        $source = fopen('php://memory', 'r+');
        self::assertIsResource($source);
        fwrite($source, $data);
        rewind($source);

        $id = $this->bucket->uploadFromStream('large.bin', $source);
        fclose($source);

        $dest = fopen('php://memory', 'r+');
        self::assertIsResource($dest);
        $this->bucket->downloadToStream($id, $dest);
        rewind($dest);
        $downloaded = stream_get_contents($dest);
        fclose($dest);

        self::assertSame(strlen($data), strlen($downloaded));
    }
}
