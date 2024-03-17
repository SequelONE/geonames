<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateGeonamesFeatureCodesTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up() {
        Schema::create( 'geonames_feature_codes', function ( Blueprint $table ) {
            $table->engine = 'MyISAM';
            $table->increments('id');
            $table->string('language_code', 2)->nullable();;
            $table->string('feature_class', 1)->nullable();;
            $table->string('feature_code', 10)->nullable();;
            $table->string('name', 255)->nullable();;
            $table->text('description')->nullable();;
            $table->timestamps();
            $table->index(['language_code',
                           'feature_code']);
            $table->index(['language_code',
                           'feature_class']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down() {
        Schema::dropIfExists( 'geonames_feature_codes' );
    }
}
