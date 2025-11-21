<?php
namespace App\Mapper\Contract;
use App\Mapper\Contract\Mapper;

interface MapperFactory{
    
    public function create(string $entity): Mapper;
}