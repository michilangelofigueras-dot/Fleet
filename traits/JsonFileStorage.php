<?php
declare(strict_types=1);

/**
 * Low-level reading and writing of data/records.json, a single document shaped like
 * {"vehicles": [...], "customers": [...], "rentals": [...]}.
 *
 * - Writes go to a temp file that is then renamed, so a crash never leaves half a file.
 * - Unreadable JSON is reported, never treated as "empty" (that would wipe real records).
 * - A missing file is created from the old per-collection files, or from the demo data.
 */
trait JsonFileStorage
{
    protected function ensureStorage(): void
    {
        $dir = Config::dataDir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new StorageException('Could not create the data folder. Give the web server write access to this directory.');
        }
    }

    /** Read the whole document from disk (creating the file on first run). */
    protected function readDocumentFile(): array
    {
        $this->ensureStorage();
        $path = Config::dataFile();

        if (!is_file($path)) {
            $this->writeDocumentFile($this->initialDocument());
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new StorageException('Could not read data/records.json. Check the folder permissions.');
        }
        if (trim($raw) === '') {
            return [];
        }

        $document = json_decode($raw, true);
        if (!is_array($document)) {
            throw new StorageException('data/records.json is not valid JSON. Fix or delete it (deleting restores the demo data).');
        }
        return $document;
    }

    protected function writeDocumentFile(array $document): void
    {
        $this->ensureStorage();
        $path = Config::dataFile();
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new StorageException('Could not encode the records for saving.');
        }

        $temp = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temp, $json) === false) {
            throw new StorageException('Could not write to the data folder. Give the web server write access to it.');
        }
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new StorageException('Could not save data/records.json.');
        }
    }

    /** Import the old vehicles/customers/rentals.json files if they exist, else use demo data. */
    private function initialDocument(): array
    {
        $document = [];
        foreach (Config::COLLECTIONS as $name) {
            $legacy = Config::dataDir() . '/' . $name . '.json';
            $rows   = is_file($legacy) ? json_decode((string) @file_get_contents($legacy), true) : null;
            $document[$name] = is_array($rows) ? array_values($rows) : SeedData::forCollection($name);
        }
        return $document;
    }
}
