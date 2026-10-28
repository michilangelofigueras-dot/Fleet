<?php
declare(strict_types=1);

/**
 * The file/data management class. Holds the records in memory and moves them to and from
 * data/records.json:
 *
 *   load()  read the file          save()  write the file
 *   getSection() / setSection()    read / replace one collection in memory
 *
 * Auto-save is ON for the web API (every change is written straight away) and OFF for the
 * console app, where menu options 7 (Save) and 8 (Load) control when the file changes.
 */
final class FleetDataManager
{
    use JsonFileStorage;

    private $document = [];
    private $loaded   = false;
    private $dirty    = false;
    private $autoSave;
    private $lockHandle = null;

    public function __construct(bool $autoSave = true)
    {
        $this->autoSave = $autoSave;
    }

    /** Runs when the object is destroyed (end of the script): release the lock. */
    public function __destruct()
    {
        $this->unlock();
        if (PHP_SAPI === 'cli') {
            echo "[FleetDataManager] Data file released. Goodbye!\n";
        }
    }

    /* ---- file operations ---- */

    /** Read data/records.json into memory, discarding any unsaved changes. */
    public function load(): void
    {
        $this->document = $this->readDocumentFile();
        $this->loaded   = true;
        $this->dirty    = false;
    }

    /** Write the records in memory to data/records.json. */
    public function save(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
        $this->writeDocumentFile($this->document);
        $this->dirty = false;
    }

    /** Delete data/records.json; it is recreated from the demo data on the next load. */
    public function deleteFile(): void
    {
        $path = Config::dataFile();
        if (is_file($path) && !@unlink($path)) {
            throw new StorageException('Could not delete data/records.json.');
        }
        $this->document = [];
        $this->loaded   = false;
        $this->dirty    = false;
    }

    public function fileExists(): bool
    {
        return is_file(Config::dataFile());
    }

    public function isDirty(): bool
    {
        return $this->dirty;
    }

    /* ---- records in memory ---- */

    public function getSection(string $name): array
    {
        $this->ensureLoaded();
        $rows = $this->document[$name] ?? [];
        return is_array($rows) ? $rows : [];
    }

    public function setSection(string $name, array $rows): void
    {
        $this->ensureLoaded();
        $this->document[$name] = array_values($rows);
        $this->dirty = true;

        if ($this->autoSave) {
            $this->save();
        }
    }

    /* ---- locking (the web API takes turns; every save is read-modify-write) ---- */

    public function lock(): void
    {
        $this->ensureStorage();
        $this->lockHandle = @fopen(Config::dataDir() . '/.lock', 'c');
        if ($this->lockHandle === false || !flock($this->lockHandle, LOCK_EX)) {
            $this->lockHandle = null;
            throw new StorageException('Could not lock the data folder. Check that the web server can write to it.');
        }
    }

    private function unlock(): void
    {
        if (is_resource($this->lockHandle)) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
        }
        $this->lockHandle = null;
    }

    private function ensureLoaded(): void
    {
        if (!$this->loaded) {
            $this->load();
        }
    }
}
