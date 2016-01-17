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
        return \App\Location::where('user_id', '=', $friend->id)->orderBy('id', 'desc')->take(2)->get();
    }, $friends->all());

    $origin = array_reduce($friendsLocations, function ($carry, $locations) {
        $location = $locations->first();
        return $carry . '|' . $location->lat . ',' . $location->long;
    }, '');
    $destination = $userCurrentLocation->lat . ',' . $userCurrentLocation->long;
    $apiKey = env("API_KEY");
    $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$origin&destinations=$destination&mode=driving&key=$apiKey";
    $distanceMatrix = json_decode(file_get_contents($url), true);

    $etas = [];

    foreach ($friendsLocations as $index => $locations) {
        $calculator = new \Location\Distance\Vincenty();

        $userCurrentCoordinate = new \Location\Coordinate($userCurrentLocation->lat, $userCurrentLocation->long);

        // current location
        $friendCurrentLocation = $locations->first();
        $friendCurrentCoordinate = new \Location\Coordinate($friendCurrentLocation->lat, $friendCurrentLocation->long);
        $friendCurrentDistance = $calculator->getDistance($userCurrentCoordinate, $friendCurrentCoordinate);

        // previous location
        $friendPrevLocation = $locations->last();
        $friendPrevCoordinate = new \Location\Coordinate($friendPrevLocation->lat, $friendPrevLocation->long);
        $friendPrevDistance = $calculator->getDistance($userCurrentCoordinate, $friendPrevCoordinate);

        // calculate direction
        $minMoveDistance = 50; // 50 meters
        if(abs($friendCurrentDistance - $friendPrevDistance) < $minMoveDistance) {
            // in the last 2 readings, they havent moved 50 meters
            $direction = 'stationary';
        } else if($friendCurrentDistance < $friendPrevDistance) {
            $direction = 'towards';
        } else {
            $direction = 'away';
        }

        if(isset($distanceMatrix['rows'][$index]['elements'][0]['duration'])){
            $eta = $distanceMatrix['rows'][$index]['elements'][0]['duration']['value'];
            $etas[] = [
                'user_id' => $friendCurrentLocation->user_id,
                'eta' => $eta,
                'last_seen_at' => $friendCurrentLocation->created_at->timestamp,
                'last_seen' => $friendCurrentLocation->created_at,
                'direction' => $direction,
                'current_distance' => $friendCurrentDistance,
                'previous_distance' => $friendPrevDistance
            ];
        }
    };
    return response()->json($etas, 200, [], JSON_NUMERIC_CHECK);
});

Route::get('users/{id}/friends', function($userId) {
    $userIds = \App\Friendships::where('user_id', '=', $userId)->lists('friend_id');
    $users = \App\User::whereIn('id', $userIds)->get();
    return response()->json($users, 200, [], JSON_NUMERIC_CHECK);
});
Route::post('users/{id}/friends', function($userId) {
    // delete this users current friends
    $friendships = \App\Friendships::where('user_id', '=', $userId)->get();
    foreach($friendships as $friendship) {
        $friendship->delete();
    }
    $fbFriends = \Illuminate\Support\Facades\Input::all();
    foreach($fbFriends as $fbFriend) {
        // find user with fb id
        $friend = \App\User::where('fb_id', '=', $fbFriend['fb_id'])->first();
        // save friendship
        \App\Friendships::create([
            "user_id" => $userId,
            "friend_id" => $friend->id
        ]);
    }
});

Route::post('users', function () {
    $data = \Illuminate\Support\Facades\Input::all();
    $user = \App\User::where('fb_id', '=', $data['fb_id'])->first();
    if(!$user) {
        $user = new \App\User();
    }
    $user->fill($data);
    $user->save();
    return response()->json($user);
});

Route::post('locations', function () {
    $data = \Illuminate\Support\Facades\Input::all();
    $location = \App\Location::create($data);
    return response()->json($location);
});
