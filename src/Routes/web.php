<?php

use Illuminate\Support\Facades\Route;


Route::get( '/geonames/search-all', '\SequelONE\Geonames\Controllers\GeonamesController@ajaxJquerySearchAll' );

/**
 *
 */
Route::get( '/geonames/{term}', '\SequelONE\Geonames\Controllers\GeonamesController@test' );


Route::get( '/geonames/cities/{asciiNameTerm}', '\SequelONE\Geonames\Controllers\GeonamesController@citiesUsingLocale' );

Route::get( '/geonames/{countryCode}/cities/{asciiNameTerm}', '\SequelONE\Geonames\Controllers\GeonamesController@citiesByCountryCode' );

Route::get( '/geonames/{countryCode}/schools/{asciiNameTerm}', '\SequelONE\Geonames\Controllers\GeonamesController@schoolsByCountryCode' );


/**
 * Uncomment these, but you will not be able to cache your routes as referenced below:
 * @url https://laravel.com/docs/7.x/deployment#optimizing-route-loading
 */
//Route::get( '/geonames/examples/vue/element/autocomplete', function () {
//    return view( 'geonames::vue-element-example' );
//} );

//Route::get( '/geonames/examples/jquery/autocomplete', function () {
//    return view( 'geonames::jquery-example' );
//} );