<?php
declare(strict_types=1);

// src/Import/Csv/Importer.php
namespace App\Import\Csv;

use App\Import\Contract\Importer as ImporterContract;
use App\Mapper\Contract\Mapper as MapperContract;
use Psr\Container\ContainerInterface;
use InvalidArgumentException;



class Importer implements ImporterContract
{
    private string $delimiter = ',';
    private string $enclosure = '"';
    private string $escape    = '\\';
    private ContainerInterface $mapperLocator;
    
    public function __construct(ContainerInterface $mapperLocator ) {
        $this->mapperLocator=$mapperLocator;
    }


    public function import(string $filePath, string $entity): array
    {
        $mapper = $this->resolveMapper($entity);

        $file    = $this->openFile($filePath);
        $headers = $this->readHeaders($file);

        $out = [];

        while (!$file->eof()) {
            $row = $this->readRow($file);
            if (!is_null($row)&&!$this->isEmptyRow($row)) {
                $row   = $this->alignRowToHeaders($headers, $row);
                $assoc = $this->combineRow($headers, $row);
                //dd($assoc);
                if (!is_null($assoc)) {
                    $assoc        = $this->trimAssoc($assoc);
                    $mappedEntity = $this->mapRow($assoc, $mapper);
                    if ($mappedEntity !== null) {
                        $out[] = $mappedEntity;
                    }
                }   
            }
        }
        return $out;
    }

 

    private function openFile(string $path,string $mode="r"): \SplFileObject
    {
        $f =new \SplFileObject($path,$mode);
        $f->setCsvControl($this->delimiter, $this->enclosure, $this->escape);
        $f->rewind();
        return $f;
    }

    private function readHeaders(\SplFileObject $f): array
    {
        $raw =$f->fgetcsv($this->delimiter, $this->enclosure, $this->escape) ?: [];
        if ($raw && isset($raw[0])) {
            $raw[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $raw[0]) ?? $raw[0];
        }

        $headers = array_values(array_filter(
            array_map(static fn($h) => strtolower(trim((string) $h)), $raw),
            static fn($h) => $h !== ''
        ));

        if ($headers==[])  {
            throw new \RuntimeException('Entêtes CSV invalides.');
        }
        return $headers;
    }


    private function readRow(\SplFileObject $f): ?array
    {
        $row = $f->fgetcsv($this->delimiter, $this->enclosure, $this->escape);
        if ($row===false || $row===[null]) {
            return null;
        }
        return $row;
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $v) {
            if ( !is_null($v) && trim((string) $v) !== '') {
                return false;
            }
        }
        return true;
    }

    private function alignRowToHeaders(array $headers, array $row): array
    {
        $cH = count($headers);
        $cR = count($row);
        if ($cR < $cH) {
            return array_pad($row, $cH, null);
        }else{
            if ($cR > $cH) {
                return array_slice($row, 0, $cH);
            }
            return $row;
        }
        
        
    }

    private function combineRow(array $headers, array $row): ?array
    {
        $assoc = @array_combine($headers, $row);
        if($assoc){
            return $assoc;
        }    
        return null;
    }


    private function trimAssoc(array $assoc): array
    {
        return array_map(static fn($v) => is_string($v) ? trim($v) : $v, $assoc);
    }

    private function mapRow(array $assoc, MapperContract $mapper): ?object
    {
        return $mapper->fromRow($assoc);
    }

    private function resolveMapper(string $entity): MapperContract
    {
        $key = strtolower(trim($entity)); 
        if (!$this->mapperLocator->has($key)) {
            throw new InvalidArgumentException("Mapper introuvable pour l’entité « {$entity} »");
        }
        $mapper = $this->mapperLocator->get($key);
        \assert($mapper instanceof MapperContract);
        return $mapper;
    }
}
