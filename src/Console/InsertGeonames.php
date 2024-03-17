<?php

namespace SequelONE\Geonames\Console;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use SequelONE\Geonames\Models\GeoSetting;
use SequelONE\Geonames\Models\Log;
use SequelONE\LocalFile\LocalFile;


class InsertGeonames extends AbstractCommand {

    use GeonamesConsoleTrait;


    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geonames:insert-geonames
        {--connection= : If you want to specify the name of the database connection you want used.}
        {--test : If you want to test the command on a small countries data set.}';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert fresh data from geonames.org';


    /**
     * @var string The name of the txt file that contains data from all of the countries.
     */
    protected $allCountriesZipFileName = 'allCountries.zip';

    /**
     * @var string The name of the txt file that contains data from all of the countries.
     */
    protected $allCountriesTxtFileName = 'allCountries.txt';


    /**
     * @var string This command makes this file. It contains all the records that get inserted into the database.
     */
    protected $masterTxtFileName = 'master.txt';


    /**
     * @var int A counter that tracks the number of lines written to the master txt file.
     */
    protected $numLinesInMasterFile;


    /**
     *
     */
    const TABLE = 'geonames';


    /**
     *
     */
    const TABLE_WORKING = 'geonames_working';


    /**
     * There are limitations on different database engines as to how many
     * rows they can insert at once. We're going to be inserting a lot, so
     * I chunk the inserts by the size defined in this constant.
     */
    const ROWS_TO_INSERT_AT_ONCE = 500;


    /**
     * Initialize constructor.
     */
    public function __construct() {
        parent::__construct();
    }


    /**
     * Execute the console command.
     *
     * @throws Exception
     */
    public function handle() {
        ini_set( 'memory_limit', -1 );
        $this->startTimer();
        $this->comment( "Running geonames:insert-geonames now." );

        try {
            $this->setDatabaseConnectionName();
            $this->info( "The database connection name was set to: " . $this->connectionName );
            $this->comment( "Testing database connection..." );
            $this->checkDatabase();
            $this->info( "Confirmed database connection set up correctly." );
        } catch ( \Exception $exception ) {
            $this->error( $exception->getMessage() );
            $this->stopTimer();
            return FALSE;
        }

        if ( $this->option( 'test' ) ):
            $this->comment( "Running in test mode. Will insert records for YU." );
            GeoSetting::install( [ 'BS', 'YU', 'UZ' ], [ 'en' ], GeoSetting::DEFAULT_STORAGE_SUBDIR, $this->connectionName );
        endif;

        $zipFileNames = $this->getLocalCountryZipFileNames();

        $absolutePathsToZipFiles = GeoSetting::getAbsoluteLocalStoragePathToFiles( $zipFileNames,
                                                                                   $this->connectionName );

        try {
            $this->unzipFiles( $absolutePathsToZipFiles, $this->connectionName );
        } catch ( \Exception $e ) {
            $this->error( "Unable to unzip at least one of the country zip files." );
            Log::error( '', "We were unable to unzip at least one of the country zip files.", 'local',
                        $this->connectionName );
            throw $e;
        }

        $absolutePathToMasterTxtFile = $this->combineTxtFiles();

        $this->info( "Num Lines: " . LocalFile::lineCount( $absolutePathToMasterTxtFile ) );

        try {
            $this->insert( $absolutePathToMasterTxtFile );
        } catch ( \Exception $e ) {
            $this->error( $e->getMessage() );
            Log::error( '', $e->getMessage(), 'database', $this->connectionName );
        }

        $this->stopTimer();
        $this->line( "\nFinished " . $this->signature );
    }


    /**
     * Get all of the file names in our geonames local storage directory.
     *
     * @return array The file names we saved from geonames.org
     * @throws \Exception
     */
    private function getLocalFiles(): array {
        $storagePath = GeoSetting::getAbsoluteLocalStoragePath( $this->connectionName );
        $fileNames   = scandir( $storagePath );
        array_shift( $fileNames ); // Remove .
        array_shift( $fileNames ); // Remove ..
        return $fileNames;
    }


    /**
     * Every country has a zip file with all of their current geonames records. Also, there is
     * an allCountries.zip file that contains all of the records.
     *
     * @return array    All of the zip file names we downloaded from geonames.org that contain
     *                  records for our geonames table.
     * @throws \Exception
     */
    private function getLocalCountryZipFileNames(): array {
        $fileNames    = $this->getLocalFiles();
        $zipFileNames = [];
        foreach ( $fileNames as $fileName ) {
            if ( $this->isCountryZipFile( $fileName ) ) {
                $zipFileNames[] = $fileName;
            }
        }

        return $zipFileNames;
    }


    /**
     * After all of the country zip files have been downloaded and unzipped, we need to
     * gather up all of the resulting txt files.
     *
     * @return array    An array of all the unzipped country text files.
     * @throws \Exception
     */
    protected function getLocalCountryTxtFileNames(): array {
        $fileNames    = $this->getLocalFiles();
        $txtFileNames = [];
        foreach ( $fileNames as $fileName ) {
            if ( $this->isCountryTxtFile( $fileName ) ) {
                $txtFileNames[] = $fileName;
            }
        }

        return $txtFileNames;
    }


    /**
     * The geonames.org download page has a zip file for every country's geonames records that gets updated daily.
     * This little function accepts a filename, and returns true if the name represents one of these zip files.
     *
     * @param string $fileName The name of a file on our local file system.
     *
     * @return bool True if the filename is one of the zip files that holds geonames records for a country.
     */
    private function isCountryZipFile( string $fileName ): bool {
        // If the file name passed in is the file with every country's geonames data, then true.
        if ( $fileName === $this->allCountriesZipFileName ) {
            return TRUE;
        }

        // A regex here checks for a two character country code and a .zip file extension.
        if ( preg_match( '/^[A-Z]{2}\.zip$/', $fileName ) === 1 ) {
            return TRUE;
        }
        return FALSE;
    }


    /**
     * Given a file name, returns true if it represents an unzipped text file with a country's geonames records.
     *
     * @param string $fileName The name of a file on our local file system.
     *
     * @return bool True if the filename is one of the text files that holds geonames records for a country.
     */
    private function isCountryTxtFile( string $fileName ): bool {
        if ( $fileName === $this->allCountriesTxtFileName ) {
            return TRUE;
        }
        if ( preg_match( '/^[A-Z]{2}\.txt$/', $fileName ) === 1 ) {
            return TRUE;
        }
        return FALSE;
    }


    /**
     * Find all of the unzipped country txt files, and combine them into one master file.
     *
     * @return string
     * @throws Exception
     */
    protected function combineTxtFiles(): string {
        $absolutePathToMasterTxtFile = GeoSetting::getAbsoluteLocalStoragePathToFile( $this->masterTxtFileName,
                                                                                      $this->connectionName );
        $textFileNames               = $this->getLocalCountryTxtFileNames();

        // If the all countries zip file was downloaded, there is nothing to combine. Just rename the file to master.
        if ( $this->allCountriesInLocalTxtFiles( $textFileNames ) ) {

            $absolutePathToAllCountriesTxtFile = GeoSetting::getAbsoluteLocalStoragePathToFile( $this->allCountriesTxtFileName,
                                                                                                $this->connectionName );
            $renameResult                      = rename( $absolutePathToAllCountriesTxtFile,
                                                         $absolutePathToMasterTxtFile );
            if ( $renameResult === FALSE ) {
                throw new Exception( "We were unable to rename the allCountries to the master file." );
            }

            return $absolutePathToMasterTxtFile;
        }

        // Create and/or truncate the master txt file before we start putting data in it.
        $masterResource = fopen( $absolutePathToMasterTxtFile, "w+" );

        if ( !file_exists( $absolutePathToMasterTxtFile ) ) {
            throw new Exception( "We were unable to create a master txt file to put all of our rows into." );
        }

        foreach ( $textFileNames as $textFile ) {
            $absolutePathToTextFile = GeoSetting::getAbsoluteLocalStoragePathToFile( $textFile, $this->connectionName );

            $this->line( "File: " . $absolutePathToTextFile );

            $inputFileSize = filesize( $absolutePathToTextFile );

            $inputResource = @fopen( $absolutePathToTextFile, 'r' );

            if ( $inputResource === FALSE ) {
                throw new Exception( "Unable to open this file in read mode " . $absolutePathToTextFile );
            }

            $bar = $this->output->createProgressBar( $inputFileSize );

            $bar->setFormat( "\nCombining %message% %current%/%max% [%bar%] %percent:3s%%\n" );

            $bar->setMessage( $textFile );

            while ( ( $buffer = fgets( $inputResource ) ) !== FALSE ) {
                $bytesWritten = fwrite( $masterResource, $buffer );
                if ( $bytesWritten === FALSE ) {
                    throw new Exception( "Unable to write " . strlen( $buffer ) . " characters from " . $absolutePathToTextFile . " to the master file." );
                }
                $this->numLinesInMasterFile++;
                $bar->advance( $bytesWritten );
            }
            if ( !feof( $inputResource ) ) {
                throw new Exception( "Error: unexpected fgets() fail on " . $absolutePathToTextFile );
            }
            fclose( $inputResource );

        }
        $closeResult = fclose( $masterResource );
        if ( $closeResult === FALSE ) {
            throw new Exception( "Unable to close the master file at " . $absolutePathToMasterTxtFile );
        }

        //$this->line("Pre-lineCount");
        //$this->line("Filesize: " . filesize($absolutePathToMasterTxtFile));
        //$this->line("Lines: " . LocalFile::lineCount($absolutePathToMasterTxtFile) );

        return $absolutePathToMasterTxtFile;
    }


    /**
     * @param $absoluteLocalFilePathOfGeonamesFile
     * @throws Exception
     */
    public function insert( $absoluteLocalFilePathOfGeonamesFile ) {
        if ( $this->isRobustDriver() ):
            $this->insertGeonamesWithLoadDataInfile( $absoluteLocalFilePathOfGeonamesFile );
        else:
            $this->insertGeonamesWithEloquent( $absoluteLocalFilePathOfGeonamesFile );
        endif;
    }


    /**
     * @param string $localFilePath
     *
     * @throws Exception
     */
    protected function insertGeonamesWithLoadDataInfile( $localFilePath ) {
        $this->line( "\nStarting to insert the records found in " . $localFilePath );
        if ( is_null( $this->numLinesInMasterFile ) ) {
            $numLines = LocalFile::lineCount( $localFilePath );
            $this->line( "We are going to try to insert " . $numLines . " geoname records from the allCountries file." );
        } else {
            $this->line( "We are going to try to insert " . $this->numLinesInMasterFile . " geoname records." );
        }

        $this->line( "Dropping the temp table named " . self::TABLE_WORKING . " (if it exists)." );
        Schema::connection( $this->connectionName )->dropIfExists( self::TABLE_WORKING );

        $this->line( "Creating the temp table named " . self::TABLE_WORKING );
        DB::connection( $this->connectionName )
          ->statement( 'CREATE TABLE ' . self::TABLE_WORKING . ' LIKE ' . self::TABLE . '; ' );

        $this->disableKeys( self::TABLE_WORKING );

        // Windows patch
        $localFilePath = $this->fixDirectorySeparatorForWindows( $localFilePath );

        $charset = config( "database.connections.{$this->connectionName}.charset", 'utf8mb4' );

        $query = "LOAD DATA LOCAL INFILE '" . $localFilePath . "'
    INTO TABLE " . self::TABLE_WORKING . " CHARACTER SET '{$charset}'
        (geonameid,
             name,
             asciiname,
             alternatenames,
             latitude,
             longitude,
             feature_class,
             feature_code,
             country_code,
             cc2,
             admin1_code,
             admin2_code,
             admin3_code,
             admin4_code,
             population,
             elevation,
             dem,
             timezone,
             modification_date,
             @created_at,
             @updated_at)
SET created_at=NOW(),updated_at=null";

        $this->line( "Running the LOAD DATA INFILE query..." );

        $rowsInserted = DB::connection( $this->connectionName )->getpdo()->exec( $query );
        if ( $rowsInserted === FALSE ) {
            throw new Exception( "Unable to execute the load data infile query. " . print_r( DB::connection( $this->connectionName )
                                                                                               ->getpdo()
                                                                                               ->errorInfo(), TRUE ) );
        }

        $this->enableKeys( self::TABLE_WORKING );

        $this->info( "Inserted text file into " . self::TABLE_WORKING );

        $this->line( "Dropping the active " . self::TABLE . " table." );
        Schema::connection( $this->connectionName )->dropIfExists( self::TABLE );


        Schema::connection( $this->connectionName )->rename( self::TABLE_WORKING, self::TABLE );
        $this->info( "Renamed " . self::TABLE_WORKING . " to " . self::TABLE );
        GeoSetting::setCountriesFromCountriesToBeAdded( $this->connectionName );
    }


    /**
     * @param $localFilePath
     * @throws \SequelONE\LocalFile\Exceptions\UnableToOpenFile
     */
    protected function insertGeonamesWithEloquent( $localFilePath ) {
        $this->line( "\nStarting to insert the records found in " . $localFilePath );
        if ( is_null( $this->numLinesInMasterFile ) ) {
            $numLines = LocalFile::lineCount( $localFilePath );
            $this->line( "We are going to try to insert " . $numLines . " geoname records from the allCountries file." );
        } else {
            $this->line( "We are going to try to insert " . $this->numLinesInMasterFile . " geoname records." );

        }

        $this->makeWorkingTable( self::TABLE, self::TABLE_WORKING );
        $this->disableKeys( self::TABLE_WORKING );

        $rows = [];
        $file = fopen( $localFilePath, 'r' );

        while ( ( $line = fgetcsv( $file, 0, "\t" ) ) !== FALSE ) {
            $modifiedLine = $this->formatLineForEloquent( $line );
            $rows[]       = $modifiedLine;
        }
        fclose( $file );


        try {
            $chunkedRows = array_chunk( $rows, self::ROWS_TO_INSERT_AT_ONCE );
            $numChunks   = count( $chunkedRows );
            $this->comment( "Split the insert file into " . $numChunks . " chuncks of " . self::ROWS_TO_INSERT_AT_ONCE . " rows per chunk." );
            $bar = $this->output->createProgressBar( $numChunks );
            foreach ( $chunkedRows as $rowsToInsert ):
                \SequelONE\Geonames\Models\GeonameWorking::insert( $rowsToInsert );
                $bar->advance();
            endforeach;
            $bar->finish();
        } catch ( \Exception $exception ) {
            Log::error( '',
                        $exception->getMessage(),
                        'database',
                        $this->connectionName );
            $this->error( $exception->getMessage() );
        }

        $this->enableKeys( self::TABLE_WORKING );
        Schema::connection( $this->connectionName )->dropIfExists( self::TABLE );
        Schema::connection( $this->connectionName )->rename( self::TABLE_WORKING, self::TABLE );
        GeoSetting::setCountriesFromCountriesToBeAdded( $this->connectionName );
    }


    /**
     * Replaces the numerical index with the Model's field name, so that I can mass insert these.
     * @param array $geonameData
     * @return array
     */
    protected function formatLineForEloquent( array $geonameData ) {

        return [
            'geonameid'         => $geonameData[ 0 ],
            'name'              => $geonameData[ 1 ],
            'asciiname'         => $geonameData[ 2 ],
            'alternatenames'    => $geonameData[ 3 ],
            'latitude'          => $geonameData[ 4 ],
            'longitude'         => $geonameData[ 5 ],
            'feature_class'     => $geonameData[ 6 ],
            'feature_code'      => $geonameData[ 7 ],
            'country_code'      => $geonameData[ 8 ],
            'cc2'               => $geonameData[ 9 ],
            'admin1_code'       => $geonameData[ 10 ],
            'admin2_code'       => $geonameData[ 11 ],
            'admin3_code'       => $geonameData[ 12 ],
            'admin4_code'       => $geonameData[ 13 ],
            'population'        => $geonameData[ 14 ],
            'elevation'         => $geonameData[ 15 ],
            'dem'               => $geonameData[ 16 ],
            'timezone'          => $geonameData[ 17 ],
            'modification_date' => $geonameData[ 18 ],
        ];
    }


    /**
     * If the allCountries file is found in the geonames storage dir on this box, then we can just use that and
     * ignore any other text files.
     *
     * @param array $txtFiles An array of text file names that we found in the geonames storage dir on this box.
     *
     * @return bool
     */
    private function allCountriesInLocalTxtFiles( array $txtFiles ): bool {
        if ( in_array( $this->allCountriesTxtFileName, $txtFiles ) ) {
            return TRUE;
        }

        return FALSE;
    }
}
