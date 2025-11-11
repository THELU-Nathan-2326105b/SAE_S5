<?php
declare(strict_types=1);

// src/Import/Csv/Importer.php
namespace App\Import\Csv;

use App\Import\Contract\Importer as ImporterContract;
use App\Mapper\Contract\Mapper as MapperContract;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;

final class Importer implements ImporterContract
{
    public function __construct(
        private ContainerInterface $mapperLocator,   
        private string $delimiter = ',',
        private string $enclosure = '"',
        private string $escape    = '\\'
    ) {}

    /** @return array<object> Entités mappées */
    public function import(string $filePath, string $entity): array
    {
        $mapper = $this->resolveMapper($entity);

        $file    = $this->openFile($filePath);
        $headers = $this->readHeaders($file);

        $out = [];
        while (!$file->eof()) {
            $row = $this->readRow($file);
            if ($row === null || $this->isEmptyRow($row)) {
                continue;
            }

            $row   = $this->alignRowToHeaders($headers, $row);
            $assoc = $this->combineRow($headers, $row);
            if ($assoc === null) {
                continue;
            }
            $assoc        = $this->trimAssoc($assoc);
            $mappedEntity = $this->mapRow($assoc, $mapper);
            if ($mappedEntity !== null) {
                $out[] = $mappedEntity;
            }
        }

        return $out;
    }

    // -----------------------
    // Helpers privés (clairs)
    // -----------------------

    private function openFile(string $path): \SplFileObject
    {
        $f = new \SplFileObject($path, 'r');
        $f->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $f->rewind();
        return $f;
    }

    /** Lit et normalise l’en-tête (trim, lower, BOM) et positionne sur la 1ʳᵉ ligne de données */
    private function readHeaders(\SplFileObject $f): array
    {
        $raw = $f->fgetcsv($this->delimiter, $this->enclosure, $this->escape) ?: [];
        if ($raw && isset($raw[0])) {
            $raw[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw[0]) ?? $raw[0];
        }

        $headers = array_values(array_filter(
            array_map(static fn($h) => strtolower(trim((string) $h)), $raw),
            static fn($h) => $h !== ''
        ));

        if ($headers === []) {
            throw new \RuntimeException('Entêtes CSV vides ou invalides.');
        }
        return $headers;
    }

    /** Lit une ligne de données ; null si invalide/EOF */
    private function readRow(\SplFileObject $f): ?array
    {
        $row = $f->fgetcsv($this->delimiter, $this->enclosure, $this->escape);
        if ($row === false || $row === [null]) {
            return null;
        }
        return $row;
    }

    /** Détecte une ligne entièrement vide (null/vides) */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if ($v !== null && trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }

    /** Ajuste la ligne (pad/slice) pour matcher le nombre d’entêtes */
    private function alignRowToHeaders(array $headers, array $row): array
    {
        $cH = count($headers);
        $cR = count($row);
        if ($cR < $cH) {
            return array_pad($row, $cH, null);
        }
        if ($cR > $cH) {
            return array_slice($row, 0, $cH);
        }
        return $row;
    }

    /** Combine entêtes + valeurs ; null si échec */
    private function combineRow(array $headers, array $row): ?array
    {
        $assoc = @array_combine($headers, $row);
        return $assoc === false ? null : $assoc;
    }

    /** Trim de chaque valeur string */
    private function trimAssoc(array $assoc): array
    {
        return array_map(static fn($v) => is_string($v) ? trim($v) : $v, $assoc);
    }

    /** Mapping via le Mapper ; renvoie l’entité (ou null si tu veux filtrer) */
    private function mapRow(array $assoc, MapperContract $mapper): ?object
    {
        return $mapper->fromRow($assoc);
    }

    /** Résout le mapper selon 'users' | 'company' via le locator */
    private function resolveMapper(string $entity): MapperContract
    {
        $key = strtolower(trim($entity)); // ex: 'users' ou 'company'
        if (!$this->mapperLocator->has($key)) {
            throw new InvalidArgumentException("Mapper introuvable pour l’entité « {$entity} »");
        }
        $mapper = $this->mapperLocator->get($key);
        \assert($mapper instanceof MapperContract);
        return $mapper;
    }
}
