<?php

namespace SequelONE\Geonames\Events;

use Illuminate\Queue\SerializesModels;
use SequelONE\Geonames\Models\GeonamesDelete;

/**
 * Class GeonameDeleted
 * @package SequelONE\Geonames\Events
 */
class GeonameDeleted {
    use SerializesModels;

    public $geonameDelete;

    /**
     * Create a new Event instance.
     * GeonameDeleted constructor.
     * @param GeonamesDelete $geonameDelete
     */
    public function __construct( GeonamesDelete $geonameDelete ) {
        $this->geonameDelete = $geonameDelete;
    }
}