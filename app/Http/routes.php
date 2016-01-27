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
Route::get('/push', function() {
    $users = \App\User::all();
    foreach($users as $user) {
        if(!$user->push_token) {
            continue;
        }
        echo $user->push_token . '<br/>';
        \Davibennun\LaravelPushNotification\Facades\PushNotification::app('appNameIOS')
            ->to($user->push_token)
            ->send('Hello World, i`m a push message');
    }
    echo 'pushed';
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
        if ($friendCurrentLocation->created_at->lt(\Carbon\Carbon::now()->subMinute(10))) {
            // the current location is old (older than 10 minutes)
            $direction = 'stationary';
        } else if (abs($friendCurrentDistance - $friendPrevDistance) < $minMoveDistance) {
            // in the last 2 readings, they havent moved 50 meters
            $direction = 'stationary';
        } else if ($friendCurrentDistance < $friendPrevDistance) {
            $direction = 'towards';
        } else {
            $direction = 'away';
        }

        if (isset($distanceMatrix['rows'][$index]['elements'][0]['duration'])) {
            $eta = $distanceMatrix['rows'][$index]['elements'][0]['duration']['value'];
            $etas[] = [
                'user_id' => $friendCurrentLocation->user_id,
                'eta' => $eta,
                'last_seen_at' => $friendCurrentLocation->created_at->timestamp,
                'last_seen' => $friendCurrentLocation->created_at,
                'direction' => $direction,
                'current_distance' => $friendCurrentDistance,
                'previous_distance' => $friendPrevDistance,
                'changed_distance' => $friendCurrentDistance - $friendPrevDistance
            ];
        }
    };
    return response()->json($etas, 200, [], JSON_NUMERIC_CHECK);
});

Route::get('users/{id}/friends', function ($userId) {
    $userIds = \App\Friendships::where('user_id', '=', $userId)->lists('friend_id');
    $users = \App\User::whereIn('id', $userIds)->get();
    return response()->json($users, 200, [], JSON_NUMERIC_CHECK);
});
Route::post('users/{id}/friends', function ($userId) {
    // delete this users current friends
    $friendships = \App\Friendships::where('user_id', '=', $userId)->get();
    foreach ($friendships as $friendship) {
        $friendship->delete();
    }
    $fbFriends = \Illuminate\Support\Facades\Input::all();
    foreach ($fbFriends as $fbFriend) {
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
    if (isset($data['fb_id'])) {
        $user = \App\User::where('fb_id', '=', $data['fb_id'])->first();
    }
    if (isset($data['id'])) {
        $user = \App\User::where('id', '=', $data['id'])->first();
    }
    if (!$user) {
        $user = new \App\User();
    }
    $user->fill($data);
    $user->save();
    return response()->json($user);
});
Route::put('users/{id}', function ($id) {
    $data = \Illuminate\Support\Facades\Input::all();
    $user = \App\User::where('id', '=', $id)->firstOrFail();
    $user->fill($data);
    $user->save();
    return response()->json($user);
});
Route::post('locations', function () {
    $data = \Illuminate\Support\Facades\Input::all();

    // last location
    $previousLocation = \App\Location::where('user_id', '=', $data['user_id'])
        ->orderBy('id', 'desc')->first();

    // new location
    $currentLocation = \App\Location::create($data);

    // check the distance between the two
    // if its less than 50 meters, delete the previous one
    // this should fix the problem of people refreshing constantly in the app
    $calculator = new \Location\Distance\Vincenty();
    $currentCoordinate = new \Location\Coordinate($currentLocation->lat, $currentLocation->long);
    $previousCoordinate = new \Location\Coordinate($previousLocation->lat, $previousLocation->long);
    $distance = $calculator->getDistance($currentCoordinate, $previousCoordinate);
    if ($distance < 50) {
        // delete the older one, we have basically replaced it, because they havent moved
        $previousLocation->delete();
    }
    return response()->json($currentLocation);
});

