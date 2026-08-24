<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TestSpacesConnection extends Command
{
    protected $signature = 'spaces:test';
    protected $description = 'Test the connection, credentials, and write permissions for DigitalOcean Spaces';

    public function handle(): int
    {
        $this->info('Testing DigitalOcean Spaces connection...');

        $key = config('filesystems.disks.spaces.key');
        $secret = config('filesystems.disks.spaces.secret');
        $region = config('filesystems.disks.spaces.region');
        $bucket = config('filesystems.disks.spaces.bucket');
        $endpoint = config('filesystems.disks.spaces.endpoint');

        $this->table(
            ['Configuration Key', 'Value'],
            [
                ['DO_SPACES_KEY', $key ? substr((string)$key, 0, 6) . '...' : 'Not configured'],
                ['DO_SPACES_SECRET', $secret ? '***' . substr((string)$secret, -4) : 'Not configured'],
                ['DO_SPACES_REGION', $region ?? 'Not configured'],
                ['DO_SPACES_BUCKET', $bucket ?? 'Not configured'],
                ['DO_SPACES_ENDPOINT', $endpoint ?? 'Not configured'],
            ]
        );

        if (blank($key) || blank($secret) || blank($region) || blank($bucket) || blank($endpoint)) {
            $this->error('Some configuration variables are missing. Please verify your .env file.');
            return 1;
        }

        try {
            $this->info('Attempting to write test file to spaces: "test_spaces_connection.txt"...');
            $filename = 'test_spaces_connection.txt';
            $content = 'Testing connection at ' . now()->toDateTimeString();

            // Try storing the file
            $writeResult = Storage::disk('spaces')->put($filename, $content, 'private');
            
            if ($writeResult === false) {
                $this->error('Failed to write file (put returned false).');
                return 1;
            }
            $this->info('Successfully wrote file to DigitalOcean Spaces.');

            // Try getting temporary URL
            $this->info('Attempting to generate temporary URL...');
            $url = Storage::disk('spaces')->temporaryUrl($filename, now()->addMinutes(10));
            $this->line('Temporary URL: ' . $url);

            // Try reading the file back
            $this->info('Attempting to read file back...');
            $readContent = Storage::disk('spaces')->get($filename);
            
            if ($readContent !== $content) {
                $this->error('Read content does not match write content.');
                return 1;
            }
            $this->info('Successfully read file back. Content matches.');

            // Try deleting the file
            $this->info('Attempting to delete the test file...');
            Storage::disk('spaces')->delete($filename);
            $this->info('Successfully deleted the test file.');

            $this->info('Spaces connection test completed successfully! All operations verified.');
            return 0;

        } catch (Throwable $e) {
            $this->error('An error occurred during testing:');
            $this->error('Exception: ' . get_class($e));
            $this->error('Message: ' . $e->getMessage());
            $this->error('Code: ' . $e->getCode());
            $this->line('File: ' . $e->getFile() . ':' . $e->getLine());
            $this->line('Trace snippet:');
            $this->line(substr($e->getTraceAsString(), 0, 1000));
            
            if ($e->getPrevious()) {
                $this->line('--- Previous Exception ---');
                $this->error('Exception: ' . get_class($e->getPrevious()));
                $this->error('Message: ' . $e->getPrevious()->getMessage());
            }

            return 1;
        }
    }
}
