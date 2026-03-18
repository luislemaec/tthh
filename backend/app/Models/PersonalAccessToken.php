<?php
namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumToken;

class PersonalAccessToken extends SanctumToken
{
    protected $connection = "pgsql";
    protected $table      = "public.personal_access_tokens";
}
