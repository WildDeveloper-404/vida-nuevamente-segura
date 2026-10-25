<?php
namespace App\Models\Concerns;
trait CompositePivot
{
    protected function setKeysForSaveQuery($query)
    {
        foreach($this->compositeKeys as $key) $query->where($key,$this->getOriginal($key)??$this->getAttribute($key));
        return $query;
    }
    protected function setKeysForSelectQuery($query) { return $this->setKeysForSaveQuery($query); }
}
