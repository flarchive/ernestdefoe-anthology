<?php

use Flarum\Database\Migration;

// How many series a member has made (1.0 to 1.1.2). Superseded: the profile's
// count is now worked out per reader, and 2026_11_19_000000 drops this column
// again, so nothing of Anthology stays on the core users table.
return Migration::addColumns('users', [
    'anthology_series_count' => ['integer', 'unsigned' => true, 'default' => 0],
]);
