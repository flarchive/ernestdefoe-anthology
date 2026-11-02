<?php

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Anyone may read series, members may make their own, moderators may edit
// any series and add any discussion.
return Migration::addPermissions([
    'anthology.view' => Group::GUEST_ID,
    'anthology.create' => Group::MEMBER_ID,
    'anthology.moderate' => Group::MODERATOR_ID,
]);
