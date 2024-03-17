<?php

namespace SequelONE\Geonames\Repositories;

use Illuminate\Support\Collection;
use SequelONE\Geonames\Models\IsoLanguageCode;

class IsoLanguageCodeRepository {

    /**
     * @return Collection
     */
    public function all() {
        return IsoLanguageCode::all();
    }
}