<?php

namespace SequelONE\Geonames\Models;

use SequelONE\Geonames\Console\AlternateName as AlternateNameConsole;

class AlternateNamesWorking extends AlternateName {

    protected $table = AlternateNameConsole::TABLE_WORKING;

}