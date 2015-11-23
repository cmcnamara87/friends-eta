<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::get('users', function () {
    $users = \App\User::all();
    return response()->json($users, 200, [], JSON_NUMERIC_CHECK);
});

Route::get('users/{id}/etas', function ($id) {
    $user = \App\User::find($id);
    $friends = \App\User::where('id', '!=', $id)->get();
    $userCurrentLocation = \App\Location::where('user_id', '=', $user->id)->orderBy('id', 'desc')->first();
    $friendsLocations = array_map(function ($friend) {
        return \App\Location::where('user_id', '=', $friend->id)->orderBy('id', 'desc')->first();
    }, $friends->all());
    $origin = array_reduce($friendsLocations, function ($carry, $location) {
        return $carry . '|' . $location->lat . ',' . $location->long;
    }, '');
    $destination = $userCurrentLocation->lat . ',' . $userCurrentLocation->long;
    $apiKey = env("API_KEY");
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$origin&destinations=$destination&mode=driving&key=$apiKey";
    $distanceMatrix = json_decode(file_get_contents($url), true);

    $etas = [];

    foreach ($friends->all() as $index => $friend) {
        if(isset($distanceMatrix['rows'][$index]['elements'][0]['duration'])){
            $eta = $distanceMatrix['rows'][$index]['elements'][0]['duration']['value'];
            $etas[] = ['user_id' => $friend->id, 'eta' => $eta];
        }
    };
    return response()->json($etas, 200, [], JSON_NUMERIC_CHECK);
});

Route::post('users', function () {
    $data = \Illuminate\Support\Facades\Input::all();
    $user = \App\User::create($data);
    return response()->json($user);
});

Route::post('locations', function () {
    $data = \Illuminate\Support\Facades\Input::all();
    $location = \App\Location::create($data);
    return response()->json($location);
});
