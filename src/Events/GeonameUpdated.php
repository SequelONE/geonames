<?php

namespace SequelONE\Geonames\Events;

use Illuminate\Queue\SerializesModels;
use SequelONE\Geonames\Models\Geoname;
/**
 * Class GeonameUpdated
 * @package SequelONE\Geonames\Events
 */
class GeonameUpdated {
    use SerializesModels;

    public $geoname;

    /**
     * Create a new Event instance.
     * GeonameUpdated constructor.
     * @param Geoname $geoname
     */
    public function __construct ( Geoname $geoname ) {
        $this->geoname = $geoname;
    }
}