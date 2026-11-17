<?php

use Flarum\Database\Migration;

// How many series a member has made: the profile shows its Series tab only
// when there is something in it, without a query per profile.
return Migration::addColumns('users', [
    'anthology_series_count' => ['integer', 'unsigned' => true, 'default' => 0],
]);
