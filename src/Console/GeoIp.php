<?php

namespace SequelONE\Geonames\Console;

use GuzzleHttp\Client;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use SequelONE\Geonames\Models\GeoSetting;
use SequelONE\Geonames\Models\Log;

/**
 * Class NoCountry
 * @package SequelONE\Geonames\Console
 */
class GeoIp extends AbstractCommand {

    use GeonamesConsoleTrait;

    /**
     * @var string The name and signature of the console command.
     */
    protected $signature = 'geonames:geo-ip
        {--connection= : If you want to specify the name of the database connection you want used.}';

    /**
     * @var string The console command description.
     */
    protected $description = "Download and insert the geo IP-adresses files from github.";

    /**
     * @var string  The base download URL for the geonames.org site (this differs from other downloads).
     */
    protected static $geoIpUrl = 'https://github.com/sapics/ip-location-db/raw/main/dbip-city/';

    protected $tablePrefix;

    /**
     *
     */
    const TABLE = 'geonames_geo_ip';

    /**
     * The name of our temporary/working table in our database.
     */
    const TABLE_WORKING = 'geonames_geo_ip_working';

    /**
     *
     */
    const REMOTE_FILE_NAME = 'dbip-city-ipv4.csv.gz';

    /**
     *
     */
    const LOCAL_CSV_FILE_NAME = 'dbip-city-ipv4.csv';


    /**
     * Initialize constructor.
     */
    public function __construct() {
        parent::__construct();
        $this->tablePrefix = Config::get('database.connections.mysql.prefix', '');
    }


    /**
     * Execute the console command. This command should always be executed after the InsertGeonames command.
     * This command assumes that the geonames table has already been created and populated.
     * @return bool
     * @throws Exception
     */
    public function handle() {
        ini_set( 'memory_limit', -1 );
        $this->startTimer();

        try {
            $this->setDatabaseConnectionName();
            $this->info( "The database connection name was set to: " . $this->connectionName );
            $this->comment( "Testing database connection..." );
            $this->checkDatabase();
            $this->info( "Confirmed database connection set up correctly." );
        } catch ( \Exception $exception ) {
            $this->error( $exception->getMessage() );
            $this->stopTimer();
            throw $exception;
        }

        $this->getDownloadCsv();

        GeoSetting::init( [ GeoSetting::DEFAULT_COUNTRIES_TO_BE_ADDED ],
            [ GeoSetting::DEFAULT_LANGUAGES ],
            GeoSetting::DEFAULT_STORAGE_SUBDIR,
            $this->connectionName );

        $downloadLink = $this->getDownloadLink();

        try {
            $localZipFile = self::downloadFile( $this, $downloadLink, $this->connectionName );
        } catch ( \Exception $e ) {
            $this->error( $e->getMessage() );
            Log::error( $downloadLink, $e->getMessage(), 'remote', $this->connectionName );

            return FALSE;
        }


        try {
            $this->line( "Unzipping " . $localZipFile );
            self::unzip( $localZipFile, $this->connectionName );
        } catch ( \Exception $e ) {
            $this->error( $e->getMessage() );
            Log::error( $localZipFile, $e->getMessage(), 'local', $this->connectionName );

            return FALSE;
        }

        $localTextFile = $this->getLocalTextFilePath( $this->connectionName );

        if ( ! file_exists( $localTextFile ) ) {
            throw new Exception( "The unzipped file could not be found. We were looking for: " . $localTextFile );
        }


        $this->insertWithLoadDataInfile( $localTextFile );


        $this->info( "The postal code data was downloaded and inserted in " . $this->getRunTime() . " seconds." );
    }

    /**
     * @return string   The absolute path to the remote alternate names zip file.
     */
    protected function getDownloadLink(): string {
        return self::$geoIpUrl;
    }

    protected function getDownloadCsv() {
        $storagePath = storage_path('geonames');

        $archiveUrls = [
            'ipv4' => 'https://github.com/sapics/ip-location-db/raw/main/dbip-city/dbip-city-ipv4.csv.gz',
            'ipv6' => 'https://github.com/sapics/ip-location-db/raw/main/dbip-city/dbip-city-ipv6.csv.gz',
        ];

        foreach ($archiveUrls as $type => $archiveUrl) {
            $uncompressedFilePath = $storagePath . '/dbip-city-' . $type . '.csv';

            file_put_contents($storagePath . '/archive-' . $type . '.csv.gz', file_get_contents($archiveUrl));

            $archive = gzopen($storagePath . '/archive-' . $type . '.csv.gz', 'rb');
            $file = fopen($uncompressedFilePath, 'wb');
            while (!gzeof($archive)) {
                fwrite($file, gzread($archive, 4096));
            }
            fclose($archive);
            fclose($file);

            unlink($storagePath . '/archive-' . $type . '.csv.gz');
        }

        return $this->info("Архив успешно загружен и распакован.");
    }

    /**
     * @param string $connection
     * @return string The absolute local path to the unzipped text file.
     * @throws \Exception
     */
    protected function getLocalTextFilePath( string $connection = NULL ): string {
        return GeoSetting::getAbsoluteLocalStoragePath( $connection ) . DIRECTORY_SEPARATOR . self::LOCAL_CSV_FILE_NAME;
    }


    /**
     * @param $localFilePath
     * @throws Exception
     */
    protected function insertWithLoadDataInfile( $localFilePath ) {
        if (Schema::connection($this->connectionName)->hasTable(self::TABLE_WORKING)) {
            Schema::connection($this->connectionName)->dropIfExists(self::TABLE_WORKING);
        }
        DB::connection( $this->connectionName )
            ->statement( 'CREATE TABLE ' . $this->tablePrefix . self::TABLE_WORKING . ' LIKE ' . $this->tablePrefix . self::TABLE . ';' );

        $this->line( "\nAttempting Load Data Infile on " . $localFilePath );

        $charset = config( "database.connections.{$this->connectionName}.charset", 'utf8mb4' );

        $storagePath = storage_path('geonames');

        $files = [
            'ipv4' => $storagePath . '/dbip-city-ipv4.csv',
            'ipv6' => $storagePath . '/dbip-city-ipv6.csv',
        ];

        foreach ($files as $type => $file) {
            $query = "
                LOAD DATA LOCAL INFILE '{$file}'
                INTO TABLE {$this->tablePrefix}" . self::TABLE_WORKING . " CHARACTER SET '{$charset}'
                FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
                LINES TERMINATED BY '\\n'
                (ip_start, ip_end, isoCountry, region, area, city, street, latitude, longitude, @created_at, @updated_at)
                SET created_at=NOW(), updated_at=null
            ";

            $this->line( "Running the LOAD DATA INFILE query. This could take a good long while." );

            $rowsInserted = DB::connection( $this->connectionName )->getpdo()->exec( $query );
        }

        if ( $rowsInserted === FALSE ) {
            Log::error( '', "Unable to load data infile for postal codes.", 'database', $this->connectionName );
            throw new Exception( "Unable to execute the load data infile query. " . print_r( DB::connection( $this->connectionName )
                    ->getpdo()->errorInfo(),
                    TRUE ) );
        }

        if (Schema::connection($this->connectionName)->hasTable(self::TABLE)) {
            Schema::connection($this->connectionName)->dropIfExists(self::TABLE);
        }

        if (Schema::connection($this->connectionName)->hasTable(self::TABLE_WORKING)) {
            Schema::connection($this->connectionName)->rename(self::TABLE_WORKING, self::TABLE);
        }
    }
}
