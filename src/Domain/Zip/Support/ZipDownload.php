<?php
namespace Domain\Zip\Support;

use ZipArchive;
use Illuminate\Contracts\Support\Responsable;
use Symfony\Component\HttpFoundation\Response;

class ZipDownload implements Responsable
{
    private ZipArchive $archive;

    private bool $closed = false;

    public function __construct(
        private readonly string $name,
        private readonly string $path,
    ) {
        $this->archive = new ZipArchive();
        $this->archive->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    }

    public static function create(string $name): self
    {
        $directory = storage_path('framework/cache');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return new self($name, tempnam($directory, 'zip_'));
    }

    public function add(string $source, string $zipPath): self
    {
        $this->archive->addFile($source, $zipPath);

        return $this;
    }

    public function addRaw(string $content, string $zipPath): self
    {
        $this->archive->addFromString($zipPath, $content);

        return $this;
    }

    public function predictZipSize(): int
    {
        $this->close();

        return filesize($this->path) ?: 0;
    }

    public function toResponse($request): Response
    {
        $this->close();

        return response()
            ->download($this->path, $this->name, ['Content-Type' => 'application/x-zip'])
            ->deleteFileAfterSend();
    }

    private function close(): void
    {
        if ($this->closed) {
            return;
        }

        $this->archive->close();
        $this->closed = true;
    }
}
