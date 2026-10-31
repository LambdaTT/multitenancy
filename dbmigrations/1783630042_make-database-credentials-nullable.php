<?php

namespace Multitenancy\Migrations;

use SplitPHP\DbManager\Migration;

class MakeDatabaseCredentialsNullable extends Migration
{
  public function apply()
  {
    $this->onDatabase('multitenancy')
      ->Table('MTN_TENANT')
      ->string('ds_database_user', 100)->nullable()->setDefaultValue(null)
      ->string('ds_database_pass', 100)->nullable()->setDefaultValue(null)
      ;
  }
}
