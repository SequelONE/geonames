<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGeonamesGeoIpTable extends Migration {

    const TABLE = 'geonames_geo_ip';

    /**
     * Run the migrations.
     * Source of data: https://github.com/sapics/ip-location-db/blob/main/dbip-city/dbip-city-ipv4.csv.gz
     * Source of data: https://github.com/sapics/ip-location-db/blob/main/dbip-city/dbip-city-ipv6.csv.gz
     * Sample data:
     * 81.3.16.0,81.3.16.239,DE,Baden-Wurttemberg,,Stuttgart (Sud),,48.7595,9.16183,
     *
     * @return void
     */
    public function up() {
        Schema::create( self::TABLE, function ( Blueprint $table ) {
            $table->engine = 'MyISAM';
            $table->increments('id');
            $table->string( 'ip_start', 100 )->nullable();
            $table->string( 'ip_end', 100 )->nullable();
            $table->string( 'isoCountry', 2 )->nullable();
            $table->string( 'region', 100 )->nullable();
            $table->string( 'area', 100 )->nullable();
            $table->string( 'city', 100 )->nullable();
            $table->string( 'street', 100 )->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();

            $table->index( 'isoCountry' );
        } );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists( self::TABLE );
    }
}
